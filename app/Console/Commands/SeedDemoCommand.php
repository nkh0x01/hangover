<?php

namespace App\Console\Commands;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'Seed the full demo dataset (owner + agent users, ~20 products across 8 categories, demo coupons, brand-voice system prompt).';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);
        $this->info('Demo seed complete.');
        $this->info('Admin login: owner@gadget.ge / password');
        $this->info('Agent login: agent@gadget.ge / password');
        $this->info('System prompt (slug=system) is active. Edit it from /admin or via the seeder file.');

        return self::SUCCESS;
    }
}
