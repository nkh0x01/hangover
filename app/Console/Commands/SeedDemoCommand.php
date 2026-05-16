<?php

namespace App\Console\Commands;

use App\Models\AiPrompt;
use App\Models\Employee;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SeedDemoCommand extends Command
{
    protected $signature   = 'demo:seed';
    protected $description = 'Seed an owner user, a few products, and the default system prompt.';

    public function handle(): int
    {
        Employee::updateOrCreate(
            ['email' => 'owner@gadget.ge'],
            [
                'name'           => 'Owner',
                'password'       => Hash::make('password'),
                'whatsapp_phone' => env('ESCALATION_WHATSAPP_TO'),
                'role'           => 'owner',
                'is_active'      => true,
            ],
        );

        $demo = [
            ['sku' => 'IP15PRO-CASE-CLR',     'name' => 'iPhone 15 Pro გამჭვირვალე ქეისი', 'brand' => 'Apple', 'category' => 'cases',     'price' => 35],
            ['sku' => 'MAGSAFE-CHRG-2',       'name' => 'MagSafe დამტენი 2',                'brand' => 'Apple', 'category' => 'chargers',  'price' => 89],
            ['sku' => 'GALS24-CASE-BLK',      'name' => 'Galaxy S24 შავი ქეისი',            'brand' => 'Samsung','category'=> 'cases',     'price' => 29],
            ['sku' => 'CABLE-USBC-1M',        'name' => 'USB-C კაბელი 1მ',                  'brand' => 'Anker','category'=> 'cables',    'price' => 19],
            ['sku' => 'AIRPODS-PRO-2',        'name' => 'AirPods Pro 2',                    'brand' => 'Apple', 'category' => 'audio',    'price' => 689],
            ['sku' => 'POWER-BANK-10K',       'name' => 'პორტატული დამტენი 10000mAh',       'brand' => 'Anker','category'=> 'powerbanks','price' => 79],
        ];

        foreach ($demo as $p) {
            Product::updateOrCreate(
                ['sku' => $p['sku']],
                array_merge($p, [
                    'stock_total'         => 20,
                    'stock_by_branch_json'=> ['Saburtalo' => 7, 'Vake' => 8, 'Gldani' => 5],
                    'images_json'         => ['https://gadget.ge/_demo/' . strtolower($p['sku']) . '.jpg'],
                    'is_active'           => true,
                    'currency'            => 'GEL',
                ]),
            );
        }

        // Default system prompt.
        $existing = AiPrompt::where('slug', 'system')->where('is_active', true)->exists();
        if (! $existing) {
            $prompt = AiPrompt::create([
                'slug'      => 'system',
                'version'   => 1,
                'is_active' => true,
                'body'      => "You are Gadget's senior digital sales consultant for customers in Georgia. " .
                    "Speak Georgian, briefly and warmly. Help the customer choose the right product, " .
                    "answer questions using ONLY the tool calls available, keep the conversation in chat, " .
                    "collect order details when the customer is ready, and escalate hard cases to a human.",
                'notes'     => 'auto-seeded',
            ]);
            $this->info("Seeded prompt v{$prompt->version}");
        }

        $this->info('Demo seed complete. Login: owner@gadget.ge / password');
        return self::SUCCESS;
    }
}
