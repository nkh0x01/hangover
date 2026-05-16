<?php

return [
    'source_url'        => env('CATALOG_SOURCE_URL'),
    'sync_interval_min' => (int) env('CATALOG_SYNC_INTERVAL_MIN', 15),
];
