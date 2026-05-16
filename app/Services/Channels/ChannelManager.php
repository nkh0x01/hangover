<?php

namespace App\Services\Channels;

use App\Services\Channels\Contracts\ChannelDriver;
use InvalidArgumentException;

class ChannelManager
{
    /** @var array<string, ChannelDriver> */
    private array $drivers = [];

    public function driver(string $platform): ChannelDriver
    {
        if (isset($this->drivers[$platform])) {
            return $this->drivers[$platform];
        }

        $config = config("channels.$platform");
        if (! $config) {
            throw new InvalidArgumentException("Unknown channel: $platform");
        }

        $class = $config['driver'];
        return $this->drivers[$platform] = new $class($config);
    }

    public function platforms(): array
    {
        return array_keys(config('channels'));
    }
}
