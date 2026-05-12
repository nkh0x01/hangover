<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(TestCase::class, RefreshDatabase::class)->in('Feature/Riding');
uses(TestCase::class, RefreshDatabase::class)->in('Feature/Pricing');
uses(TestCase::class, RefreshDatabase::class)->in('Feature/Driver');
uses(TestCase::class, RefreshDatabase::class)->in('Feature/Identity');
uses(TestCase::class, RefreshDatabase::class)->in('Feature/Geo');
