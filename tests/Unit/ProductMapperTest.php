<?php

namespace Tests\Unit;

use App\Services\Gadget\Mappers\ProductMapper;
use Tests\TestCase;

class ProductMapperTest extends TestCase
{
    public function test_maps_a_typical_woocommerce_product(): void
    {
        $woo = [
            'id' => 4242,
            'sku' => 'IP15-CASE-CLR',
            'name' => 'iPhone 15 გამჭვირვალე ქეისი',
            'permalink' => 'https://gadget.ge/product/ip15-case-clr/',
            'short_description' => '<p>გამძლე და მსუბუქი</p>',
            'regular_price' => '49.00',
            'sale_price' => '39.00',
            'on_sale' => true,
            'manage_stock' => true,
            'stock_quantity' => 12,
            'stock_status' => 'instock',
            'status' => 'publish',
            'currency' => 'GEL',
            'images' => [['src' => 'https://gadget.ge/img/a.jpg'], ['src' => 'https://gadget.ge/img/b.jpg']],
            'categories' => [['name' => 'Cases', 'slug' => 'cases']],
            'attributes' => [
                ['name' => 'Color', 'slug' => 'pa_color', 'options' => ['Clear']],
                ['name' => 'Brand', 'slug' => 'pa_brand', 'options' => ['Spigen']],
                ['name' => 'Compatibility', 'slug' => 'pa_compat', 'options' => ['iPhone 15', 'iPhone 15 Pro']],
            ],
            'meta_data' => [
                ['key' => 'stock_saburtalo', 'value' => '4'],
                ['key' => 'stock_vake',      'value' => '5'],
                ['key' => 'stock_gldani',    'value' => '3'],
            ],
        ];

        $row = (new ProductMapper)->fromWoo($woo);

        $this->assertSame('IP15-CASE-CLR', $row['sku']);
        $this->assertSame('4242', $row['source_id']);
        $this->assertSame('cases', $row['category']);
        $this->assertSame('Spigen', $row['brand']);
        $this->assertSame(39.0, $row['price']);          // promo wins
        $this->assertSame(39.0, $row['price_promo']);
        $this->assertTrue($row['is_promo']);
        $this->assertSame(12, $row['stock_total']);
        $this->assertSame(['Saburtalo' => 4, 'Vake' => 5, 'Gldani' => 3], $row['stock_by_branch_json']);
        $this->assertSame(['https://gadget.ge/img/a.jpg', 'https://gadget.ge/img/b.jpg'], $row['images_json']);
        $this->assertNotEmpty($row['compatibility_json']);
        $this->assertSame('GEL', $row['currency']);
        $this->assertTrue($row['is_active']);
    }

    public function test_treats_unmanaged_stock_as_in_stock_when_status_says_so(): void
    {
        $woo = [
            'id' => 1, 'sku' => 'X', 'name' => 'X', 'permalink' => '',
            'regular_price' => '10', 'manage_stock' => false,
            'stock_status' => 'instock', 'status' => 'publish',
        ];
        $row = (new ProductMapper)->fromWoo($woo);
        $this->assertGreaterThan(0, $row['stock_total']);
    }

    public function test_treats_unmanaged_stock_out_of_stock(): void
    {
        $woo = [
            'id' => 1, 'sku' => 'X', 'name' => 'X', 'permalink' => '',
            'regular_price' => '10', 'manage_stock' => false,
            'stock_status' => 'outofstock', 'status' => 'publish',
        ];
        $row = (new ProductMapper)->fromWoo($woo);
        $this->assertSame(0, $row['stock_total']);
    }
}
