<?php

namespace App\Services\Gadget\Mappers;

use App\Models\Customer;
use App\Models\Order;

class CustomerMapper
{
    /**
     * Build a WC customers POST/PUT payload from our internal data.
     * Only fields with a value are returned — partial updates are safe.
     */
    public function toWoo(Customer $customer, ?Order $order = null): array
    {
        $name = trim((string) ($customer->display_name ?? ''));
        [$first, $last] = $this->splitName($name);

        $email = $customer->profile_json['email']
              ?? $order?->customer_phone . '@gadget-chat.local';

        $billing = array_filter([
            'first_name' => $first,
            'last_name'  => $last,
            'phone'      => $order?->customer_phone ?? $customer->phone,
            'city'       => $order?->city,
            'address_1'  => $order?->address,
            'country'    => 'GE',
            'email'      => $email,
        ]);

        return array_filter([
            'email'      => $email,
            'first_name' => $first,
            'last_name'  => $last,
            'username'   => $this->usernameFor($customer),
            'billing'    => $billing,
            'shipping'   => $billing,
            'meta_data'  => [
                ['key' => 'gadget_chatbot_platform',        'value' => $customer->platform],
                ['key' => 'gadget_chatbot_platform_user',   'value' => $customer->platform_user_id],
                ['key' => 'gadget_chatbot_locale',          'value' => $customer->locale ?? 'ka'],
            ],
        ]);
    }

    /** Pull values from a WC customer object back onto our Customer. */
    public function fromWoo(array $wc, Customer $customer): void
    {
        if (! empty($wc['first_name']) || ! empty($wc['last_name'])) {
            $name = trim(($wc['first_name'] ?? '') . ' ' . ($wc['last_name'] ?? ''));
            if ($name && ! $customer->display_name) {
                $customer->display_name = $name;
            }
        }
        if (! empty($wc['billing']['phone']) && ! $customer->phone) {
            $customer->phone = $wc['billing']['phone'];
        }

        $patch = array_filter([
            'email'             => $wc['email'] ?? null,
            'preferred_city'    => $wc['billing']['city'] ?? null,
            'preferred_address' => $wc['billing']['address_1'] ?? null,
        ]);
        $customer->profile_json = array_replace_recursive($customer->profile_json ?? [], $patch);
        $customer->save();
    }

    private function splitName(string $name): array
    {
        $name = preg_replace('/\s+/', ' ', trim($name));
        if ($name === '') return ['', ''];
        $parts = explode(' ', $name, 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    private function usernameFor(Customer $c): string
    {
        return 'chat_' . $c->platform . '_' . $c->platform_user_id;
    }
}
