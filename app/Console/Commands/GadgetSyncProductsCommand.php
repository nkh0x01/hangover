<?php

namespace App\Console\Commands;

use App\Services\Gadget\CatalogSync;
use Illuminate\Console\Command;

class GadgetSyncProductsCommand extends Command
{
    protected $signature   = 'gadget:sync-products';
    protected $description = 'Pull the full product catalog from gadget.ge (WooCommerce REST API) into the local mirror.';

    public function handle(CatalogSync $sync): int
    {
        $r = $sync->run();
        $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return ($r['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
