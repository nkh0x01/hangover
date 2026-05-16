<?php

namespace App\Services\Gadget;

use App\Models\Customer;
use App\Models\Order;
use App\Services\Gadget\Mappers\CustomerMapper;
use Illuminate\Support\Facades\Log;

/**
 * Links a chat Customer to a WooCommerce customer record. The match
 * is by phone first, then by email, then by username `chat_<platform>_<id>`.
 * If nothing matches we create a new WC customer.
 *
 * The WC id is stored in `customers.external_id`.
 */
class CustomerLink
{
    public function __construct(
        private GadgetApi $api,
        private CustomerMapper $mapper,
    ) {}

    public function syncToWoo(Customer $customer, ?Order $order = null): ?int
    {
        if (! $this->api->isConfigured()) {
            return null;
        }

        try {
            // 1. Already linked?
            if ($customer->external_id) {
                $wc = $this->api->customers()->get((int) $customer->external_id);
                if ($wc) {
                    $this->api->customers()->update((int) $customer->external_id, $this->mapper->toWoo($customer, $order));

                    return (int) $customer->external_id;
                }
            }

            // 2. Match by phone.
            $phone = $order?->customer_phone ?? $customer->phone;
            if ($phone) {
                $wc = $this->api->customers()->findByPhone($phone);
                if ($wc) {
                    $customer->update(['external_id' => $wc['id']]);
                    $this->mapper->fromWoo($wc, $customer);
                    $this->api->customers()->update((int) $wc['id'], $this->mapper->toWoo($customer, $order));

                    return (int) $wc['id'];
                }
            }

            // 3. Match by email if we have one.
            $email = $customer->profile_json['email'] ?? null;
            if ($email) {
                $wc = $this->api->customers()->findByEmail($email);
                if ($wc) {
                    $customer->update(['external_id' => $wc['id']]);
                    $this->mapper->fromWoo($wc, $customer);

                    return (int) $wc['id'];
                }
            }

            // 4. Create new.
            $created = $this->api->customers()->create($this->mapper->toWoo($customer, $order));
            if (! empty($created['id'])) {
                $customer->update(['external_id' => $created['id']]);

                return (int) $created['id'];
            }
        } catch (\Throwable $e) {
            Log::warning('gadget.customer_link.failed', [
                'customer' => $customer->id,
                'msg' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
