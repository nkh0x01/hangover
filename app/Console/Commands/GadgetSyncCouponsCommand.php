<?php

namespace App\Console\Commands;

use App\Services\Gadget\CouponSync;
use Illuminate\Console\Command;

class GadgetSyncCouponsCommand extends Command
{
    protected $signature = 'gadget:sync-coupons';

    protected $description = 'Pull active coupons (discounts/promos) from gadget.ge into the local coupons table.';

    public function handle(CouponSync $sync): int
    {
        $r = $sync->run();
        $this->line(json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return ($r['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
