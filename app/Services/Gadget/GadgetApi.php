<?php

namespace App\Services\Gadget;

use App\Services\Gadget\Resources\Categories;
use App\Services\Gadget\Resources\Coupons;
use App\Services\Gadget\Resources\Customers;
use App\Services\Gadget\Resources\Orders;
use App\Services\Gadget\Resources\Products;

/**
 * Convenience facade — `app(GadgetApi::class)->products()->each(...)`.
 */
class GadgetApi
{
    public function __construct(
        private WooCommerceClient $client,
    ) {}

    public function client(): WooCommerceClient { return $this->client; }

    public function products(): Products { return new Products($this->client); }
    public function coupons(): Coupons   { return new Coupons($this->client); }
    public function customers(): Customers { return new Customers($this->client); }
    public function orders(): Orders     { return new Orders($this->client); }
    public function categories(): Categories { return new Categories($this->client); }

    public function isConfigured(): bool
    {
        return ! empty(config('gadget.consumer_key')) && ! empty(config('gadget.consumer_secret'));
    }
}
