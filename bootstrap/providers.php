<?php

use App\Providers\AIServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\ChannelServiceProvider;
use App\Providers\GadgetServiceProvider;

return [
    AppServiceProvider::class,
    ChannelServiceProvider::class,
    AIServiceProvider::class,
    GadgetServiceProvider::class,
];
