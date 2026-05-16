<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->catalog() as $p) {
            Product::updateOrCreate(
                ['sku' => $p['sku']],
                array_merge($p, [
                    'is_active' => true,
                    'currency' => 'GEL',
                    'synced_at' => now(),
                ]),
            );
        }
    }

    /**
     * Sample demo catalog covering the categories Gadget actually
     * sells: phones, cases, chargers, cables, audio, powerbanks,
     * wearables, laptops. Image URLs are placeholders pointing at the
     * real product photo paths used on gadget.ge (the demo seeder is
     * for local dev; real photos come from gadget:sync-products).
     */
    private function catalog(): array
    {
        return [
            // Phones
            [
                'sku' => 'IP15-128-BLU',
                'source_id' => '101',
                'name' => 'iPhone 15 128GB ლურჯი',
                'brand' => 'Apple', 'model' => 'iPhone 15', 'category' => 'phones',
                'description' => 'A16 Bionic, Dynamic Island, 48MP კამერა, USB-C.',
                'price' => 2799, 'price_promo' => 2599, 'is_promo' => true,
                'stock_total' => 8,
                'stock_by_branch_json' => ['Saburtalo' => 3, 'Vake' => 3, 'Gldani' => 2],
                'attributes_json' => ['Color' => 'Blue', 'Storage' => '128GB'],
                'images_json' => ['https://gadget.ge/img/iphone-15-blue.jpg'],
                'url' => 'https://gadget.ge/product/iphone-15-128-blue/',
            ],
            [
                'sku' => 'IP15PRO-256-NAT',
                'source_id' => '102',
                'name' => 'iPhone 15 Pro 256GB Natural Titanium',
                'brand' => 'Apple', 'model' => 'iPhone 15 Pro', 'category' => 'phones',
                'description' => 'A17 Pro, ტიტანის კორპუსი, 48MP Pro კამერა, Action button.',
                'price' => 3699, 'stock_total' => 5,
                'stock_by_branch_json' => ['Saburtalo' => 2, 'Vake' => 2, 'Gldani' => 1],
                'attributes_json' => ['Color' => 'Natural Titanium', 'Storage' => '256GB'],
                'images_json' => ['https://gadget.ge/img/iphone-15-pro-natural.jpg'],
                'url' => 'https://gadget.ge/product/iphone-15-pro-natural/',
            ],
            [
                'sku' => 'GAL-S24-256-BLK',
                'source_id' => '103',
                'name' => 'Samsung Galaxy S24 256GB შავი',
                'brand' => 'Samsung', 'model' => 'Galaxy S24', 'category' => 'phones',
                'description' => 'Snapdragon 8 Gen 3, AI ფუნქციები, 50MP კამერა.',
                'price' => 2299, 'stock_total' => 6,
                'stock_by_branch_json' => ['Saburtalo' => 2, 'Vake' => 2, 'Gldani' => 2],
                'attributes_json' => ['Color' => 'Black', 'Storage' => '256GB'],
                'images_json' => ['https://gadget.ge/img/galaxy-s24-black.jpg'],
                'url' => 'https://gadget.ge/product/galaxy-s24-black/',
            ],

            // Cases
            [
                'sku' => 'IP15PRO-CASE-CLR',
                'source_id' => '201',
                'name' => 'iPhone 15 Pro გამჭვირვალე ქეისი',
                'brand' => 'Spigen', 'category' => 'cases',
                'description' => 'MagSafe-თან თავსებადი, არ ყვითლდება.',
                'price' => 49, 'stock_total' => 32,
                'compatibility_json' => ['iPhone 15 Pro'],
                'stock_by_branch_json' => ['Saburtalo' => 12, 'Vake' => 14, 'Gldani' => 6],
                'images_json' => ['https://gadget.ge/img/case-ip15pro-clear.jpg'],
                'url' => 'https://gadget.ge/product/ip15pro-case-clear/',
            ],
            [
                'sku' => 'IP15-CASE-BLK',
                'source_id' => '202',
                'name' => 'iPhone 15 ტყავის ქეისი - შავი',
                'brand' => 'Nomad', 'category' => 'cases',
                'description' => 'ნამდვილი ტყავი, MagSafe.',
                'price' => 159, 'stock_total' => 10,
                'compatibility_json' => ['iPhone 15'],
                'stock_by_branch_json' => ['Saburtalo' => 4, 'Vake' => 4, 'Gldani' => 2],
                'images_json' => ['https://gadget.ge/img/case-ip15-leather-black.jpg'],
                'url' => 'https://gadget.ge/product/ip15-case-leather-black/',
            ],
            [
                'sku' => 'GAL-S24-CASE-CLR',
                'source_id' => '203',
                'name' => 'Galaxy S24 გამჭვირვალე ქეისი',
                'brand' => 'Spigen', 'category' => 'cases',
                'description' => 'Air Cushion ტექნოლოგია.',
                'price' => 39, 'stock_total' => 18,
                'compatibility_json' => ['Galaxy S24'],
                'stock_by_branch_json' => ['Saburtalo' => 6, 'Vake' => 8, 'Gldani' => 4],
                'images_json' => ['https://gadget.ge/img/case-s24-clear.jpg'],
                'url' => 'https://gadget.ge/product/s24-case-clear/',
            ],

            // Chargers
            [
                'sku' => 'MAGSAFE-CHRG-2',
                'source_id' => '301',
                'name' => 'Apple MagSafe დამტენი 2',
                'brand' => 'Apple', 'category' => 'chargers',
                'description' => '15W უსადენო დატენვა iPhone-ისთვის.',
                'price' => 89, 'stock_total' => 15,
                'compatibility_json' => ['iPhone 12+', 'AirPods Pro 2'],
                'stock_by_branch_json' => ['Saburtalo' => 6, 'Vake' => 5, 'Gldani' => 4],
                'images_json' => ['https://gadget.ge/img/magsafe-charger.jpg'],
                'url' => 'https://gadget.ge/product/magsafe-charger/',
            ],
            [
                'sku' => 'GAN-65W-3PORT',
                'source_id' => '302',
                'name' => 'Anker GaN 65W 3-პორტიანი დამტენი',
                'brand' => 'Anker', 'category' => 'chargers',
                'description' => 'PD 3.0, ლეპტოპის დატენვის შესაძლებლობით.',
                'price' => 79, 'price_promo' => 65, 'is_promo' => true,
                'stock_total' => 22,
                'stock_by_branch_json' => ['Saburtalo' => 8, 'Vake' => 8, 'Gldani' => 6],
                'images_json' => ['https://gadget.ge/img/anker-gan-65.jpg'],
                'url' => 'https://gadget.ge/product/anker-gan-65/',
            ],

            // Cables
            [
                'sku' => 'CABLE-USBC-1M',
                'source_id' => '401',
                'name' => 'Anker USB-C → USB-C კაბელი 1მ',
                'brand' => 'Anker', 'category' => 'cables',
                'description' => '60W PD, ნეილონის წნული.',
                'price' => 19, 'stock_total' => 60,
                'stock_by_branch_json' => ['Saburtalo' => 25, 'Vake' => 20, 'Gldani' => 15],
                'images_json' => ['https://gadget.ge/img/cable-usbc-1m.jpg'],
                'url' => 'https://gadget.ge/product/cable-usbc-1m/',
            ],
            [
                'sku' => 'CABLE-LIGHT-1M',
                'source_id' => '402',
                'name' => 'Anker Lightning კაბელი 1მ',
                'brand' => 'Anker', 'category' => 'cables',
                'description' => 'MFi სერტიფიცირებული.',
                'price' => 25, 'stock_total' => 40,
                'compatibility_json' => ['iPhone 5–14'],
                'stock_by_branch_json' => ['Saburtalo' => 15, 'Vake' => 15, 'Gldani' => 10],
                'images_json' => ['https://gadget.ge/img/cable-lightning-1m.jpg'],
                'url' => 'https://gadget.ge/product/cable-lightning-1m/',
            ],

            // Audio
            [
                'sku' => 'AIRPODS-PRO-2',
                'source_id' => '501',
                'name' => 'AirPods Pro 2 (USB-C)',
                'brand' => 'Apple', 'category' => 'audio',
                'description' => 'Active Noise Cancellation, Adaptive Audio, MagSafe ქეისი.',
                'price' => 689, 'stock_total' => 11,
                'stock_by_branch_json' => ['Saburtalo' => 4, 'Vake' => 4, 'Gldani' => 3],
                'images_json' => ['https://gadget.ge/img/airpods-pro-2.jpg'],
                'url' => 'https://gadget.ge/product/airpods-pro-2/',
            ],
            [
                'sku' => 'SONY-WH1000XM5',
                'source_id' => '502',
                'name' => 'Sony WH-1000XM5 ყურსასმენი',
                'brand' => 'Sony', 'category' => 'audio',
                'description' => 'წამყვანი ANC, 30 საათი ბატარეა.',
                'price' => 899, 'price_promo' => 799, 'is_promo' => true,
                'stock_total' => 4,
                'stock_by_branch_json' => ['Saburtalo' => 2, 'Vake' => 2, 'Gldani' => 0],
                'images_json' => ['https://gadget.ge/img/sony-wh1000xm5.jpg'],
                'url' => 'https://gadget.ge/product/sony-wh1000xm5/',
            ],

            // Powerbanks
            [
                'sku' => 'POWER-BANK-10K',
                'source_id' => '601',
                'name' => 'Anker PowerCore 10000mAh',
                'brand' => 'Anker', 'category' => 'powerbanks',
                'description' => 'PD 22.5W, კომპაქტური ფორმა.',
                'price' => 79, 'stock_total' => 25,
                'stock_by_branch_json' => ['Saburtalo' => 10, 'Vake' => 9, 'Gldani' => 6],
                'images_json' => ['https://gadget.ge/img/anker-10k.jpg'],
                'url' => 'https://gadget.ge/product/anker-10k/',
            ],
            [
                'sku' => 'POWER-BANK-20K',
                'source_id' => '602',
                'name' => 'Anker PowerCore 20000mAh',
                'brand' => 'Anker', 'category' => 'powerbanks',
                'description' => '65W გამოსასვლელი, ლეპტოპის დატენვის შესაძლებლობით.',
                'price' => 149, 'stock_total' => 12,
                'stock_by_branch_json' => ['Saburtalo' => 4, 'Vake' => 4, 'Gldani' => 4],
                'images_json' => ['https://gadget.ge/img/anker-20k.jpg'],
                'url' => 'https://gadget.ge/product/anker-20k/',
            ],

            // Wearables
            [
                'sku' => 'APPLE-WATCH-S9-45',
                'source_id' => '701',
                'name' => 'Apple Watch Series 9 45mm',
                'brand' => 'Apple', 'category' => 'wearables',
                'description' => 'Always-On Retina, Double-Tap ჟესტი, 18 საათი.',
                'price' => 1199, 'stock_total' => 7,
                'stock_by_branch_json' => ['Saburtalo' => 3, 'Vake' => 2, 'Gldani' => 2],
                'images_json' => ['https://gadget.ge/img/aw-s9-45.jpg'],
                'url' => 'https://gadget.ge/product/aw-s9-45/',
            ],
            [
                'sku' => 'GAL-WATCH-7-44',
                'source_id' => '702',
                'name' => 'Samsung Galaxy Watch 7 44mm',
                'brand' => 'Samsung', 'category' => 'wearables',
                'description' => 'BioActive სენსორი, Wear OS.',
                'price' => 899, 'stock_total' => 5,
                'stock_by_branch_json' => ['Saburtalo' => 2, 'Vake' => 2, 'Gldani' => 1],
                'images_json' => ['https://gadget.ge/img/galaxy-watch-7.jpg'],
                'url' => 'https://gadget.ge/product/galaxy-watch-7/',
            ],

            // Laptops
            [
                'sku' => 'MBA-M3-13-256',
                'source_id' => '801',
                'name' => 'MacBook Air M3 13" 256GB',
                'brand' => 'Apple', 'category' => 'laptops',
                'description' => 'M3 ჩიპი, 8GB RAM, 18 საათი ბატარეა.',
                'price' => 3299, 'stock_total' => 3,
                'stock_by_branch_json' => ['Saburtalo' => 1, 'Vake' => 1, 'Gldani' => 1],
                'images_json' => ['https://gadget.ge/img/mba-m3-13.jpg'],
                'url' => 'https://gadget.ge/product/mba-m3-13/',
            ],
            [
                'sku' => 'ASUS-VIVOBOOK-15',
                'source_id' => '802',
                'name' => 'ASUS Vivobook 15 i5/16GB/512GB',
                'brand' => 'ASUS', 'category' => 'laptops',
                'description' => 'Intel i5, 16GB DDR4, 512GB SSD.',
                'price' => 2099, 'price_promo' => 1899, 'is_promo' => true,
                'stock_total' => 4,
                'stock_by_branch_json' => ['Saburtalo' => 2, 'Vake' => 1, 'Gldani' => 1],
                'images_json' => ['https://gadget.ge/img/asus-vivobook-15.jpg'],
                'url' => 'https://gadget.ge/product/asus-vivobook-15/',
            ],

            // Storage / misc
            [
                'sku' => 'SANDISK-128',
                'source_id' => '901',
                'name' => 'SanDisk microSD 128GB',
                'brand' => 'SanDisk', 'category' => 'storage',
                'description' => 'A2, U3, 4K ვიდეო.',
                'price' => 49, 'stock_total' => 50,
                'stock_by_branch_json' => ['Saburtalo' => 20, 'Vake' => 18, 'Gldani' => 12],
                'images_json' => ['https://gadget.ge/img/sandisk-128.jpg'],
                'url' => 'https://gadget.ge/product/sandisk-128/',
            ],
            [
                'sku' => 'OUT-OF-STOCK-1',
                'source_id' => '999',
                'name' => 'iPhone 15 Pro Max 1TB (გათავებული)',
                'brand' => 'Apple', 'category' => 'phones',
                'description' => 'მოლოდინი — დემო პროდუქტი out-of-stock ფლოუს შესამოწმებლად.',
                'price' => 5499, 'stock_total' => 0,
                'stock_by_branch_json' => ['Saburtalo' => 0, 'Vake' => 0, 'Gldani' => 0],
                'images_json' => [],
                'url' => 'https://gadget.ge/product/ip15-pro-max-1tb/',
            ],
        ];
    }
}
