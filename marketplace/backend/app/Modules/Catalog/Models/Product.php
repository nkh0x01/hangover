<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Review\Models\Favorite;
use App\Modules\Review\Models\Review;
use App\Modules\Seller\Models\Seller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Product extends Model
{
    use Searchable, SoftDeletes;

    protected $fillable = [
        'seller_id',
        'category_id',
        'slug',
        'title_ka',
        'title_en',
        'description_ka',
        'description_en',
        'materials',
        'dimensions',
        'weight_grams',
        'price_gel',
        'compare_at_price_gel',
        'stock',
        'is_made_to_order',
        'lead_time_days',
        'production_type',
        'country_of_production',
        'status',
        'published_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'materials' => 'array',
            'dimensions' => 'array',
            'is_made_to_order' => 'boolean',
            'price_gel' => 'decimal:2',
            'compare_at_price_gel' => 'decimal:2',
            'rating_avg' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeInStock(Builder $q): Builder
    {
        return $q->where(function (Builder $q) {
            $q->where('stock', '>', 0)->orWhere('is_made_to_order', true);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function coverImage(): ?ProductImage
    {
        return $this->images->firstWhere('is_cover', true) ?? $this->images->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['category', 'seller']);

        return [
            'id' => $this->id,
            'title_ka' => $this->title_ka,
            'description_ka' => $this->description_ka,
            'materials' => implode(' ', (array) ($this->materials ?? [])),
            'category_name' => $this->category?->name_ka,
            'seller_name' => $this->seller?->business_name,
            'status' => $this->status,
            'category_id' => $this->category_id,
            'seller_region' => $this->seller?->region,
            'price_gel' => (float) $this->price_gel,
            'rating_avg' => (float) $this->rating_avg,
            'published_at_ts' => $this->published_at?->getTimestamp() ?? 0,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return $this->status === 'published';
    }
}
