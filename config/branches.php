<?php

/*
|--------------------------------------------------------------------------
| Physical store branches
|--------------------------------------------------------------------------
| Source of truth for location / address / working-hours answers in chat.
| Consumed by App\Services\AI\PromptBuilder — the bot answers ONLY from
| this list and must escalate rather than invent an address or hours.
| Data from https://gadget.ge/branches (all branches: daily 10:00–22:00).
*/

return [
    'phone' => '0322 003303',
    'hours_note' => 'ყოველდღე 10:00–22:00',

    'list' => [
        ['name' => 'თბილისი — ისთ ფოინთი',            'address' => 'ა. თვალჭრელიძის 2, ისთ ფოინთი, თბილისი',              'hours' => '10:00–22:00 (ყოველდღე)'],
        ['name' => 'თბილისი — ისთ ფოინთი (პრემიუმ)',   'address' => 'ა. თვალჭრელიძის 2, ისთ ფოინთი — პრემიუმ სივრცე, თბილისი', 'hours' => '10:00–22:00 (ყოველდღე)'],
        ['name' => 'ბათუმი — Black Sea Mall',          'address' => 'თბელ აბუსერიძის 20, შავი ზღვის მოლი, ბათუმი',          'hours' => '10:00–22:00 (ყოველდღე)'],
        ['name' => 'ბათუმი — Grand Mall',              'address' => 'შერიფ ხიმშიაშვილის 29, გრანდ მოლი, ბათუმი',           'hours' => '10:00–22:00 (ყოველდღე)'],
        ['name' => 'ქუთაისი — West Pointi',            'address' => 'ჯულია შარტავას 2, ქუთაისი',                            'hours' => '10:00–22:00 (ყოველდღე)'],
        ['name' => 'რუსთავი — Rustavi Mall',           'address' => 'მეგობრობის გამზირი 1ა, რუსთავი მოლი, რუსთავი',         'hours' => '10:00–22:00 (ყოველდღე)'],
        ['name' => 'ზუგდიდი — Zugdidi Mall',           'address' => 'კოსტავას 28, ზუგდიდი მოლი, ზუგდიდი',                   'hours' => '10:00–22:00 (ყოველდღე)'],
        ['name' => 'თელავი — Telavi Mall',             'address' => 'ალაზნის გამზირი 115, თელავი მოლი, თელავი',             'hours' => '10:00–22:00 (ყოველდღე)'],
    ],
];
