<?php

namespace App\Console\Commands;

use App\Services\Products\CatalogSyncService;
use Illuminate\Console\Command;

class CatalogSyncCommand extends Command
{
    protected $signature = 'catalog:sync {--url= : Optional override source URL}';

    protected $description = 'Mirror the gadget.ge product catalog into the local products table.';

    public function handle(CatalogSyncService $sync): int
    {
        $result = $sync->sync($this->option('url') ?: null);
        $this->info(json_encode($result, JSON_PRETTY_PRINT));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
