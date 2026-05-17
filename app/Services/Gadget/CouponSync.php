<?php

namespace App\Services\Gadget;

use App\Models\Coupon;
use App\Services\Gadget\Exceptions\WooApiException;
use App\Services\Gadget\Mappers\CouponMapper;
use Illuminate\Support\Facades\Log;

class CouponSync
{
    public function __construct(
        private GadgetApi $api,
        private CouponMapper $mapper,
    ) {}

    public function run(): array
    {
        if (! $this->api->isConfigured()) {
            return ['ok' => false, 'reason' => 'wc_not_configured'];
        }

        $seen = [];
        $upserted = 0;

        try {
            foreach ($this->api->coupons()->each() as $c) {
                $row = $this->mapper->fromWoo($c);
                if ($row['code'] === '') {
                    continue;
                }

                Coupon::updateOrCreate(['code' => $row['code']], $row);
                $seen[] = $row['code'];
                $upserted++;
            }
        } catch (WooApiException $e) {
            Log::error('coupons.sync.failed', ['msg' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'wc_api_error', 'detail' => $e->getMessage()];
        }

        $deactivated = 0;
        if ($upserted > 0) {
            $deactivated = Coupon::whereNotIn('code', $seen)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        return ['ok' => true, 'upserted' => $upserted, 'deactivated' => $deactivated];
    }
}
