<?php

declare(strict_types=1);

use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Watchers;

return [
    'domain' => env('TELESCOPE_DOMAIN'),
    'path' => env('TELESCOPE_PATH', 'telescope'),
    'driver' => env('TELESCOPE_DRIVER', 'database'),

    'enabled' => env('TELESCOPE_ENABLED', false),

    'storage' => [
        'database' => [
            'connection' => env('DB_CONNECTION', 'mysql'),
            'chunk' => 1000,
        ],
    ],

    'middleware' => ['web', Authorize::class],

    'only_paths' => [],
    'ignore_paths' => ['nova-api*'],
    'ignore_commands' => [],

    'watchers' => [
        Watchers\CacheWatcher::class => ['enabled' => true, 'hidden' => []],
        Watchers\CommandWatcher::class => ['enabled' => true, 'ignore' => []],
        Watchers\DumpWatcher::class => ['enabled' => true],
        Watchers\EventWatcher::class => ['enabled' => true, 'ignore' => []],
        Watchers\ExceptionWatcher::class => ['enabled' => true],
        Watchers\GateWatcher::class => ['enabled' => true],
        Watchers\ClientRequestWatcher::class => ['enabled' => true],
        Watchers\JobWatcher::class => ['enabled' => true],
        Watchers\LogWatcher::class => ['enabled' => true],
        Watchers\MailWatcher::class => ['enabled' => true],
        Watchers\ModelWatcher::class => ['enabled' => true, 'events' => ['eloquent.*']],
        Watchers\NotificationWatcher::class => ['enabled' => true],
        Watchers\QueryWatcher::class => [
            'enabled' => true,
            'slow' => 100,
        ],
        Watchers\RedisWatcher::class => ['enabled' => true],
        Watchers\RequestWatcher::class => ['enabled' => true, 'size_limit' => 64],
        Watchers\ScheduleWatcher::class => ['enabled' => true],
        Watchers\ViewWatcher::class => ['enabled' => true],
    ],
];
