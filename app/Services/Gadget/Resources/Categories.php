<?php

namespace App\Services\Gadget\Resources;

use App\Services\Gadget\WooCommerceClient;

class Categories
{
    public function __construct(private WooCommerceClient $client) {}

    public function each(): \Generator
    {
        foreach ($this->client->paginate('products/categories') as $page) {
            foreach ($page as $cat) {
                yield $cat;
            }
        }
    }
}
