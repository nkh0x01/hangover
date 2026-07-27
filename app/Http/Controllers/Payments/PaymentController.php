<?php

namespace App\Http\Controllers\Payments;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Services\Channels\ChannelManager;
use App\Services\Gadget\OrderPush;
use App\Services\Sales\BogClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bank of Georgia payment endpoints.
 *
 *   POST /payments/bog/callback  — server-to-server payment notification
 *   GET  /payments/return        — browser redirect after success
 *   GET  /payments/fail          — browser redirect after failure/cancel
 *
 * Money-critical rule: a paid order is confirmed ONLY from BOG's authoritative
 * receipt endpoint (re-fetched with our own OAuth token), never from the
 * callback body alone. The Callback-Signature is a first-line authenticity
 * gate; the receipt re-fetch is the decision of record.
 */
class PaymentController extends Controller
{
    // BOG order_status.key groupings (see config + BOG docs).
    private const PAID = ['completed', 'partial_completed'];

    private const FAILED = ['rejected'];

    public function __construct(private BogClient $bog) {}

    public function bogCallback(Request $request)
    {
        $raw = $request->getContent();

        // 1) Signature gate: reject only if present AND invalid (forged/replayed).
        $sig = $this->bog->verifyCallbackSignature($raw, $request->header('Callback-Signature'));
        if ($sig === false) {
            Log::warning('bog.callback.bad_signature', ['ip' => $request->ip()]);
            AuditLog::record('system', 'payment.callback.bad_signature', 'orders', 0, ['ip' => $request->ip()]);

            return response('invalid signature', 403);
        }

        $payload = json_decode($raw, true) ?: [];
        $body = $payload['body'] ?? [];
        $extId = $body['external_order_id'] ?? null;     // our Order id
        $bogOrderId = $body['order_id'] ?? null;         // BOG order UUID

        // 2) Locate our order.
        $order = null;
        if ($extId !== null && ctype_digit((string) $extId)) {
            $order = Order::find((int) $extId);
        }
        if (! $order && $bogOrderId) {
            $order = Order::where('payment_provider_ref', $bogOrderId)->first();
        }
        if (! $order) {
            Log::warning('bog.callback.order_not_found', ['external_order_id' => $extId, 'order_id' => $bogOrderId]);

            return response('ok', 200); // ack; nothing we can do
        }

        // 3) Idempotent: already settled → ack.
        if ($order->status === Order::STATUS_PAID) {
            return response('ok', 200);
        }

        // 4) AUTHORITATIVE status from BOG (do not trust the pushed body).
        $ref = $order->payment_provider_ref ?: $bogOrderId;
        $statusKey = $ref ? $this->bog->orderStatusKey((string) $ref) : null;

        // Only if the re-fetch is unavailable (network), fall back to the body key,
        // and never let that fallback FINALIZE an unrecognized status.
        $trusted = $statusKey !== null;
        if ($statusKey === null) {
            $statusKey = $body['order_status']['key'] ?? null;
            Log::warning('bog.callback.refetch_unavailable', ['order' => $order->id, 'body_key' => $statusKey]);
        }

        if (in_array($statusKey, self::PAID, true)) {
            $this->markPaid($order, $statusKey);
        } elseif (in_array($statusKey, self::FAILED, true)) {
            $order->update(['payment_status' => 'failed']);
            AuditLog::record('system', 'payment.failed', 'orders', $order->id, ['status_key' => $statusKey, 'trusted' => $trusted]);
        } else {
            // created/processing/blocked/refunded/unknown → non-final, no fulfillment.
            AuditLog::record('system', 'payment.callback.nonfinal', 'orders', $order->id, ['status_key' => $statusKey, 'trusted' => $trusted]);
        }

        return response('ok', 200);
    }

    private function markPaid(Order $order, string $statusKey): void
    {
        try {
            $order->update([
                'status' => Order::STATUS_PAID,
                'payment_status' => 'paid',
                'payment_method' => 'card',
                'paid_at' => now(),
            ]);
            AuditLog::record('system', 'payment.paid', 'orders', $order->id, ['status_key' => $statusKey]);

            // Create the WooCommerce order (idempotent — no double writes).
            $push = app(OrderPush::class)->push($order->fresh());
            if (! ($push['ok'] ?? false)) {
                Log::warning('bog.callback.woo_push_failed', ['order' => $order->id, 'reason' => $push['reason'] ?? null]);
            }

            $this->sendChatConfirmation($order->fresh());
        } catch (Throwable $e) {
            Log::error('bog.callback.mark_paid_failed', ['order' => $order->id, 'msg' => $e->getMessage()]);
        }
    }

    private function sendChatConfirmation(Order $order): void
    {
        try {
            $conv = $order->conversation;
            if (! $conv) {
                return;
            }

            $ref = $order->external_order_id ? " #{$order->external_order_id}" : '';
            $total = number_format((float) $order->total, 0, '.', ' ');
            $text = "გადახდა მიღებულია ✅ შეკვეთა{$ref} დადასტურდა — {$total} ₾. მადლობა! მიწოდების დეტალებზე მალე დაგიკავშირდებით.";

            $driver = app(ChannelManager::class)->driver($conv->platform);
            $result = $driver->sendText($conv->thread_id, $text);

            Message::create([
                'conversation_id' => $conv->id,
                'customer_id' => $order->customer_id,
                'platform_msg_id' => $result->platformMsgId ?? null,
                'direction' => Message::DIRECTION_OUT,
                'kind' => 'text',
                'body' => $text,
                'is_ai' => true,
                'sent_at' => now(),
            ]);

            $conv->update([
                'last_outbound_at' => now(),
                'lead_status' => Conversation::STATUS_CONVERTED,
            ]);
        } catch (Throwable $e) {
            Log::warning('bog.callback.chat_confirm_failed', ['order' => $order->id, 'msg' => $e->getMessage()]);
        }
    }

    public function success(Request $request)
    {
        return response()->view('payments.return');
    }

    public function fail(Request $request)
    {
        return response()->view('payments.fail');
    }
}
