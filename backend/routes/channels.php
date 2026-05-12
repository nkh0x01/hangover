<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Broadcast channels
|--------------------------------------------------------------------------
|
| Module-owned channel auth callbacks are registered from inside each
| module via require base_path('routes/channels.php'). To avoid spaghetti,
| this file delegates to each module's own routes/channels.php file.
|
*/

foreach ((array) config('modules.enabled', []) as $provider) {
    if (! is_string($provider) || ! class_exists($provider)) {
        continue;
    }

    $reflection = new ReflectionClass($provider);
    $dir = dirname((string) $reflection->getFileName(), 2);  // Providers/.. -> module root
    $channelFile = $dir.DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'channels.php';

    if (file_exists($channelFile)) {
        require $channelFile;
    }
}
