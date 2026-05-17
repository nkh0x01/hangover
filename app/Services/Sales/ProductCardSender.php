<?php

namespace App\Services\Sales;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Product;
use App\Services\Channels\ChannelManager;
use App\Services\Channels\DTO\MediaPayload;

/**
 * Renders a real product card for the customer's channel:
 *   - WhatsApp: interactive button message with image header + 3 reply buttons
 *   - Messenger / Instagram: text + quick_replies (mobile-friendly chips)
 *   - any other channel: falls back to plain text (degraded by AbstractMetaDriver)
 *
 * Buttons map to canonical payload ids the chat understands:
 *   buy_{sku}            → customer wants to buy this exact item
 *   alts_{sku}           → recommend alternatives
 *   moreinfo_{sku}       → ask for more details
 *   pickup_{sku}         → reserve for branch pickup
 */
class ProductCardSender
{
    public function __construct(private ChannelManager $channels) {}

    public function send(Conversation $conversation, Customer $customer, Product $product, array $opts = []): void
    {
        $driver = $this->channels->driver($conversation->platform);

        $price = $this->formatPrice($product);
        $body = $this->renderBody($product, $price);

        $image = $product->primaryImage();
        $header = $image ? new MediaPayload('image', $image, $product->name) : null;

        $buttons = $this->buttonsFor($product, $opts);
        $footer = $product->isInStock() ? 'საწყობში: ' . (int) $product->stock_total . ' ცალი' : 'მიმდინარეობს მოლოდინი';

        $result = $driver->sendInteractiveButtons(
            $conversation->thread_id,
            $body,
            $buttons,
            $header,
            $footer,
        );

        Message::create([
            'conversation_id' => $conversation->id,
            'customer_id' => $customer->id,
            'platform_msg_id' => $result->platformMsgId,
            'direction' => Message::DIRECTION_OUT,
            'kind' => 'interactive',
            'body' => $body,
            'media_json' => $image ? ['url' => $image] : null,
            'tool_calls_json' => ['product_card' => ['sku' => $product->sku, 'buttons' => $buttons]],
            'is_ai' => true,
            'sent_at' => now(),
        ]);

        $conversation->update([
            'last_outbound_at' => now(),
            'lead_status' => Conversation::STATUS_PRODUCT_RECOMMENDED,
        ]);
    }

    private function renderBody(Product $product, string $price): string
    {
        $name = $product->name;
        $desc = $product->description ? mb_substr(strip_tags($product->description), 0, 200) : null;

        $lines = ["*$name*", $price];
        if ($desc) {
            $lines[] = $desc;
        }

        return implode("\n", $lines);
    }

    private function formatPrice(Product $product): string
    {
        $price = (float) $product->effectivePrice();
        $formatted = number_format($price, 0, '.', ' ') . ' ₾';

        if ($product->price_promo && $product->price > $product->price_promo) {
            $old = number_format((float) $product->price, 0, '.', ' ');

            return "ფასი: *{$formatted}* (იყო ~{$old} ₾~) 🎉";
        }

        return "ფასი: *{$formatted}*";
    }

    /** @return array<int, array{id: string, title: string}> */
    private function buttonsFor(Product $product, array $opts): array
    {
        // Tailor button set based on stock.
        if (! $product->isInStock()) {
            return [
                ['id' => "alts_{$product->sku}",     'title' => 'ალტერნატივა'],
                ['id' => "notify_{$product->sku}",   'title' => 'შემატყობინე'],
                ['id' => 'talk_human',                'title' => 'ოპერატორი'],
            ];
        }

        return [
            ['id' => "buy_{$product->sku}",       'title' => 'შეკვეთა'],
            ['id' => "pickup_{$product->sku}",    'title' => 'ფილიალში'],
            ['id' => "alts_{$product->sku}",      'title' => 'სხვა ვარიანტი'],
        ];
    }
}
