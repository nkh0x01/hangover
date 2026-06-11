<?php

declare(strict_types=1);

/*
 * Marketplace business knobs. Tweak via .env where possible so production
 * deploys do not require code changes.
 */

return [
    'name' => [
        'ka' => env('MARKETPLACE_NAME_KA', 'ქართული წარმოება'),
        'en' => env('MARKETPLACE_NAME_EN', 'Made in Georgia Market'),
    ],

    'currency' => env('MARKETPLACE_CURRENCY', 'GEL'),

    'commission_pct' => (float) env('MARKETPLACE_COMMISSION_PCT', 0),

    'cod_enabled' => (bool) env('MARKETPLACE_COD_ENABLED', true),

    'default_shipping_gel' => (float) env('MARKETPLACE_DEFAULT_SHIPPING_GEL', 10.00),

    'order_number' => [
        'prefix' => 'M',
        'pad' => 6,
    ],

    'regions' => [
        'tbilisi' => 'თბილისი',
        'adjara' => 'აჭარა',
        'guria' => 'გურია',
        'imereti' => 'იმერეთი',
        'kakheti' => 'კახეთი',
        'mtskheta_mtianeti' => 'მცხეთა-მთიანეთი',
        'racha_lechkhumi' => 'რაჭა-ლეჩხუმი და ქვემო სვანეთი',
        'samegrelo_zemo_svaneti' => 'სამეგრელო-ზემო სვანეთი',
        'samtskhe_javakheti' => 'სამცხე-ჯავახეთი',
        'kvemo_kartli' => 'ქვემო ქართლი',
        'shida_kartli' => 'შიდა ქართლი',
        'abkhazia' => 'აფხაზეთი',
    ],

    'mountainous_regions' => [
        'racha_lechkhumi',
        'samtskhe_javakheti',
        'mtskheta_mtianeti',
    ],

    'production_types' => [
        'handmade' => 'ხელნაკეთი',
        'small_batch' => 'მცირე პარტია',
        'local_production' => 'ადგილობრივი წარმოება',
        'organic' => 'ბიო / ორგანული',
        'other' => 'სხვა',
    ],

    'seller_sectors' => [
        'food_and_drink' => 'საკვები და სასმელი',
        'agriculture' => 'სოფლის მეურნეობა',
        'cosmetics' => 'კოსმეტიკა',
        'fashion' => 'მოდა და ტანსაცმელი',
        'jewelry' => 'სამკაული',
        'home_and_interior' => 'სახლი და ინტერიერი',
        'kids' => 'ბავშვებისთვის',
        'crafts' => 'ხელნაკეთობა',
        'print_and_design' => 'ბეჭდვა და დიზაინი',
        'eco' => 'ეკო პროდუქცია',
        'other' => 'სხვა',
    ],

    'legal_forms' => [
        'individual' => 'ფიზიკური პირი',
        'solo_entrepreneur' => 'ინდივიდუალური მეწარმე',
        'small_business' => 'მცირე ბიზნესის სტატუსი',
        'llc' => 'შპს',
        'other' => 'სხვა',
    ],
];
