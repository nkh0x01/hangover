<?php

declare(strict_types=1);

return [
    'docs_disk' => env('DRIVER_DOCS_DISK', env('FILESYSTEM_DISK', 'local')),

    'applications' => [
        'auto_approve' => (bool) env('DRIVER_AUTO_APPROVE', false),
    ],
];
