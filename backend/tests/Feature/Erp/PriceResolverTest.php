<?php

declare(strict_types=1);

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Core\Models\Brand;
use App\Modules\Erp\Inventory\Models\ProductVariant;
use App\Modules\Erp\Pricing\Models\PriceList;
use App\Modules\Erp\Pricing\Models\PriceListItem;
use App\Modules\Erp\Pricing\Services\PriceResolver;

function priceItem(ProductVariant $variant, float $price, array $listAttrs): PriceListItem
{
    $list = PriceList::create(array_merge([
        'name' => 'list',
        'type' => PriceList::TYPE_RETAIL,
        'currency' => 'GEL',
        'is_active' => true,
    ], $listAttrs));

    return PriceListItem::create([
        'price_list_id' => $list->id,
        'product_variant_id' => $variant->id,
        'price' => $price,
        'vat_included' => true,
    ]);
}

it('prefers a branch+brand price list over a global one', function (): void {
    $brand = Brand::factory()->create();
    $branch = Branch::factory()->create(['brand_id' => $brand->id]);
    $variant = ProductVariant::factory()->create();

    priceItem($variant, 100.00, ['brand_id' => null, 'branch_id' => null]);
    priceItem($variant, 90.00, ['brand_id' => $brand->id, 'branch_id' => $branch->id]);

    $resolved = app(PriceResolver::class)->resolve((int) $variant->id, (int) $brand->id, (int) $branch->id);

    expect((float) $resolved->price)->toBe(90.00);
});

it('returns null when no active list prices the variant', function (): void {
    $variant = ProductVariant::factory()->create();

    expect(app(PriceResolver::class)->resolve((int) $variant->id, null, null))->toBeNull();
});

it('ignores a list pinned to a different branch', function (): void {
    $variant = ProductVariant::factory()->create();
    $other = Branch::factory()->create();
    $mine = Branch::factory()->create();

    priceItem($variant, 70.00, ['brand_id' => null, 'branch_id' => $other->id]);
    priceItem($variant, 100.00, ['brand_id' => null, 'branch_id' => null]);

    $resolved = app(PriceResolver::class)->resolve((int) $variant->id, null, (int) $mine->id);

    expect((float) $resolved->price)->toBe(100.00);
});
