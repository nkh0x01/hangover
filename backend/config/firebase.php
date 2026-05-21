<?php

declare(strict_types=1);

return [
    'projects' => [
        'app' => [
            'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
            'project_id' => env('FIREBASE_PROJECT_ID'),
        ],
    ],
    'default' => 'app',
];
