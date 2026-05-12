<?php

declare(strict_types=1);

return [
    'domain' => env('HORIZON_DOMAIN'),
    'path' => env('HORIZON_PATH', 'horizon'),

    'use' => 'default',

    'prefix' => env('HORIZON_PREFIX', 'horizon:'),

    'middleware' => ['web'],

    'waits' => [
        'redis:realtime' => 10,
        'redis:default'  => 60,
        'redis:low'      => 300,
    ],

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10_080,
        'failed' => 10_080,
        'monitored' => 10_080,
    ],

    'silenced' => [],

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    'fast_termination' => false,
    'memory_limit' => 128,

    'defaults' => [
        'realtime' => [
            'connection' => 'redis',
            'queue' => ['realtime'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 30,
            'nice' => 0,
        ],
        'default' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => 1,
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
        'low' => [
            'connection' => 'redis',
            'queue' => ['low'],
            'balance' => 'auto',
            'maxProcesses' => 1,
            'memory' => 256,
            'tries' => 2,
            'timeout' => 300,
        ],
    ],

    'environments' => [
        'production' => [
            'realtime' => ['maxProcesses' => 10, 'minProcesses' => 4],
            'default'  => ['maxProcesses' => 10, 'minProcesses' => 2],
            'low'      => ['maxProcesses' => 4,  'minProcesses' => 1],
        ],
        'staging' => [
            'realtime' => ['maxProcesses' => 4, 'minProcesses' => 2],
            'default'  => ['maxProcesses' => 4, 'minProcesses' => 1],
            'low'      => ['maxProcesses' => 2, 'minProcesses' => 1],
        ],
        'local' => [
            'realtime' => ['maxProcesses' => 2, 'minProcesses' => 1],
            'default'  => ['maxProcesses' => 2, 'minProcesses' => 1],
            'low'      => ['maxProcesses' => 1, 'minProcesses' => 1],
        ],
    ],
];
