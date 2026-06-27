<?php

declare(strict_types=1);

use App\Modules\Erp\Core\Models\Branch;
use App\Modules\Erp\Core\Models\Brand;
use App\Modules\Erp\Integration\Contracts\IntegrationLogger;
use App\Modules\Erp\Integration\DatabaseIntegrationLogger;
use App\Modules\Erp\Integration\Models\IntegrationLog;

it('creates brands and branches with the multi-brand structure', function (): void {
    $brand = Brand::factory()->flagship()->create(['code' => 'EASTPOINT']);
    $branch = Branch::factory()->create(['brand_id' => $brand->id, 'code' => 'TBS-01']);

    expect($branch->brand->is_flagship)->toBeTrue()
        ->and($brand->branches)->toHaveCount(1);
});

it('binds the database integration logger and persists an audit row', function (): void {
    $logger = app(IntegrationLogger::class);
    expect($logger)->toBeInstanceOf(DatabaseIntegrationLogger::class);

    $logger->log('rs_fiscal', 'create_receipt', ['total' => 50], ['status' => 'ok'], true, true, 'idem-1', 'FR-9');

    $row = IntegrationLog::firstOrFail();
    expect($row->provider)->toBe('rs_fiscal')
        ->and($row->success)->toBeTrue()
        ->and($row->verified)->toBeTrue()
        ->and($row->reference)->toBe('FR-9');
});
