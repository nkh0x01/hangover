<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\AiSuggestionService;
use App\Services\Channels\ChannelManager;
use App\Services\Gadget\ProductSearchService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ProductController extends Controller
{
    public function __construct(
        private ProductSearchService $products,
        private ChannelManager $channels,
    ) {}

    /**
     * Live product search from WooCommerce.
     * GET /api/admin/products/search?q=iphone&category=case&sku=&limit=20
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:200',
            'category' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $result = $this->products->search(
            query: (string) $request->input('q', ''),
            filters: array_filter([
                'category' => $request->input('category'),
                'sku' => $request->input('sku'),
            ]),
            limit: (int) $request->input('limit', 20),
        );

        return response()->json($result);
    }

    /**
     * Send a product to the customer's chat. Fetches the LIVE WC product
     * (single source of truth — no caller can pass fake data), sends the
     * image as an attachment when supported, then the text card.
     *
     * Body fields (all optional except product_id):
     *   product_id   (required) — WC product id
     *   extra_text   prepended to the formatted card
     *   override_text replaces the formatted card entirely (still uses WC image)
     *   skip_image   true to send text only
     */
    public function sendProduct(Request $request, int $id)
    {
        $request->validate([
            'product_id' => 'required|integer',
            'extra_text' => 'nullable|string|max:1000',
            'override_text' => 'nullable|string|max:4000',
            'skip_image' => 'sometimes|boolean',
        ]);

        $conv = Conversation::with('customer')->findOrFail($id);

        // Fetch live from WC — only place product facts come from.
        try {
            $raw = app(\App\Services\Gadget\WooCommerceClient::class)
                ->get('products/'.((int) $request->input('product_id')));
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'wc_fetch_failed: '.$e->getMessage()], 502);
        }

        if (empty($raw) || ! is_array($raw) || empty($raw['id'])) {
            return response()->json(['ok' => false, 'error' => 'product_not_found'], 404);
        }

        $product = [
            'id' => (int) $raw['id'],
            'sku' => $raw['sku'] ?? null,
            'name' => (string) ($raw['name'] ?? ''),
            'price' => (string) ($raw['price'] ?? ''),
            'regular_price' => $raw['regular_price'] ?? null,
            'sale_price' => $raw['sale_price'] ?? null,
            'on_sale' => (bool) ($raw['on_sale'] ?? false),
            'stock_status' => (string) ($raw['stock_status'] ?? 'instock'),
            'short_description' => trim(html_entity_decode(strip_tags($raw['short_description'] ?? ''))),
            'description' => trim(html_entity_decode(strip_tags($raw['description'] ?? ''))),
            'permalink' => $raw['permalink'] ?? null,
            'image' => $raw['images'][0]['src'] ?? null,
        ];

        // Build text body
        $body = (string) $request->input('override_text', '');
        if ($body === '') {
            $body = $this->products->formatForChat($product);
        }
        $extra = trim((string) $request->input('extra_text', ''));
        if ($extra !== '') {
            $body = $extra."\n\n".$body;
        }

        $driver = $this->channels->driver($conv->platform);
        $imageSent = false;
        $textResult = null;

        // 1. Try to send image first (Messenger supports attachments)
        if ($product['image'] && ! $request->boolean('skip_image') && method_exists($driver, 'sendImage')) {
            $imgResult = $driver->sendImage($conv->thread_id, $product['image']);
            $imageSent = $imgResult->ok;
            // Don't hard-fail on image — fall through to text if image failed
        }

        // 2. Always send the text card
        $textResult = $driver->sendText($conv->thread_id, $body);
        if (! $textResult->ok) {
            return response()->json([
                'ok' => false,
                'error' => $textResult->error ?? 'send_failed',
                'detail' => $textResult->raw ?? null,
                'image_sent' => $imageSent,
            ], 422);
        }

        $msg = Message::create([
            'conversation_id' => $conv->id,
            'customer_id' => $conv->customer_id,
            'platform_msg_id' => $textResult->platformMsgId,
            'direction' => Message::DIRECTION_OUT,
            'kind' => 'text',
            'body' => $body,
            'media_json' => $product['image'] ? [['url' => $product['image'], 'sent' => $imageSent]] : null,
            'is_ai' => false,
            'author_employee_id' => $request->user()?->id,
            'sent_at' => now(),
        ]);
        $conv->update(['last_outbound_at' => now(), 'last_read_at' => now()]);

        AuditLog::record('employee', 'product.send', 'conversation', $conv->id, [
            'product_id' => $product['id'],
            'sku' => $product['sku'],
            'image_sent' => $imageSent,
            'msg_id' => $msg->id,
        ], $request->user()?->id);

        return response()->json([
            'ok' => true,
            'image_sent' => $imageSent,
            'message' => [
                'id' => $msg->id,
                'direction' => $msg->direction,
                'kind' => $msg->kind,
                'body' => $msg->body,
                'is_ai' => false,
                'author' => $request->user()?->only(['id', 'name']),
                'created_at' => $msg->created_at,
            ],
        ]);
    }

    /**
     * AI-driven recommendation: analyze last customer messages, search WC,
     * return up to 3 candidate products + a Georgian draft. Does NOT send.
     */
    public function recommend(int $id, AiSuggestionService $svc)
    {
        $conv = Conversation::with('customer')->findOrFail($id);
        $result = $svc->recommendProducts($conv);
        if ($result['ok']) {
            AuditLog::record('employee', 'product.recommend', 'conversation', $conv->id, [
                'query' => $result['query'],
                'count' => count($result['products']),
            ], request()->user()?->id);
        }
        return response()->json($result);
    }
}
