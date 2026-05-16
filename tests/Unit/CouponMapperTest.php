<?php

namespace Tests\Unit;

use App\Models\Coupon;
use App\Services\Gadget\Mappers\CouponMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponMapperTest extends TestCase
{
    use RefreshDatabase;

    public function test_maps_typical_woocommerce_coupon_and_applies_validity_logic(): void
    {
        $woo = [
            'id' => 7,
            'code' => 'NY2026',
            'discount_type' => 'percent',
            'amount' => '15',
            'minimum_amount' => '50',
            'maximum_amount' => '',
            'date_expires' => '2026-12-31T23:59:59',
            'product_ids' => [11, 12],
            'product_categories' => [['id' => 7, 'name' => 'Cases', 'slug' => 'cases']],
            'excluded_product_ids' => [],
            'individual_use' => true,
            'free_shipping' => false,
            'usage_limit' => 100,
            'usage_count' => 4,
            'description' => 'New Year discount',
        ];

        $row = (new CouponMapper)->fromWoo($woo);
        $coupon = Coupon::create($row);

        $this->assertSame('NY2026', $coupon->code);
        $this->assertSame('percent', $coupon->discount_type);
        $this->assertSame(15.0, (float) $coupon->amount);
        $this->assertSame(50.0, (float) $coupon->min_amount);
        $this->assertNull($coupon->max_amount);
        $this->assertTrue($coupon->individual_use);
        $this->assertTrue($coupon->isValid());

        $this->assertTrue($coupon->appliesToSku('IRRELEVANT', 'cases'));
        $this->assertFalse($coupon->appliesToSku('IRRELEVANT', 'audio'));
    }

    public function test_invalidates_when_usage_limit_reached(): void
    {
        $c = Coupon::create([
            'code' => 'USED', 'discount_type' => 'fixed_cart', 'amount' => 10,
            'usage_limit' => 1, 'usage_count' => 1, 'is_active' => true,
        ]);
        $this->assertFalse($c->isValid());
    }
}
