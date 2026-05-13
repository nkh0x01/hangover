<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests at the root of tests/Feature (e.g. HealthCheckTest)
// don't need a database. Sub-directories that do, layer RefreshDatabase
// on top via the specific declarations below.
uses(TestCase::class)->in(__DIR__.'/Feature');

uses(RefreshDatabase::class)->in(
    __DIR__.'/Feature/Riding',
    __DIR__.'/Feature/Pricing',
    __DIR__.'/Feature/Driver',
    __DIR__.'/Feature/Identity',
    __DIR__.'/Feature/Geo',
    __DIR__.'/Feature/Payment',
);
