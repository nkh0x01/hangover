<?php

declare(strict_types=1);

use App\Modules\Driver\Actions\ReviewDriverDocument;
use App\Modules\Driver\Actions\SubmitDriverDocument;
use App\Modules\Driver\Actions\VerifyVehicle;
use App\Modules\Driver\Models\Driver;
use App\Modules\Driver\Models\DriverDocument;
use App\Modules\Driver\Models\Vehicle;
use App\Modules\Driver\Services\DriverVerificationPresenter;
use App\Modules\Identity\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeAdmin(): User
{
    return User::factory()->create(['type' => 'admin']);
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('moves a driver from pending to in_review on first document upload', function (): void {
    $driver = Driver::factory()->create(['verification_status' => 'pending']);

    $file = UploadedFile::fake()->image('id.jpg', 800, 600);
    app(SubmitDriverDocument::class)->execute($driver, 'id_front', $file);

    expect($driver->refresh()->verification_status)->toBe('in_review');
    expect(DriverDocument::query()->where('driver_id', $driver->id)->count())->toBe(1);
});

it('replaces a rejected re-submission rather than stacking pending rows', function (): void {
    $driver = Driver::factory()->create(['verification_status' => 'in_review']);
    $admin = makeAdmin();

    $first = app(SubmitDriverDocument::class)->execute($driver, 'license_front', UploadedFile::fake()->image('a.jpg'));
    app(ReviewDriverDocument::class)->reject($first, $admin, 'blurry');

    $second = app(SubmitDriverDocument::class)->execute($driver, 'license_front', UploadedFile::fake()->image('b.jpg'));

    expect(DriverDocument::query()->where('driver_id', $driver->id)->where('doc_type', 'license_front')->count())->toBe(1);
    expect($second->status)->toBe('pending');
});

it('marks the driver verified only when all docs approved + vehicle verified', function (): void {
    $driver = Driver::factory()->create(['verification_status' => 'in_review']);
    $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);
    $driver->update(['current_vehicle_id' => $vehicle->id]);
    $admin = makeAdmin();

    // Approve every required document.
    foreach (DriverVerificationPresenter::REQUIRED_DOCUMENTS as $type) {
        $doc = app(SubmitDriverDocument::class)->execute($driver, $type, UploadedFile::fake()->image($type.'.jpg'));
        app(ReviewDriverDocument::class)->approve($doc, $admin);
    }

    // Vehicle still unverified — driver stays in_review.
    expect($driver->refresh()->verification_status)->toBe('in_review');

    // Verify the vehicle.
    app(VerifyVehicle::class)->verify($vehicle->refresh(), $admin);

    expect($driver->refresh()->verification_status)->toBe('verified');
    expect($driver->verified_at)->not->toBeNull();
});

it('rejecting any document immediately marks the driver as rejected', function (): void {
    $driver = Driver::factory()->create(['verification_status' => 'in_review']);
    $admin = makeAdmin();
    $doc = app(SubmitDriverDocument::class)->execute($driver, 'license_front', UploadedFile::fake()->image('l.jpg'));

    app(ReviewDriverDocument::class)->reject($doc, $admin, 'expired license');

    expect($driver->refresh()->verification_status)->toBe('rejected');
    expect($driver->verification_notes)->toBe('expired license');
});

it('rejection requires a notes message', function (): void {
    $driver = Driver::factory()->create();
    $admin = makeAdmin();
    $doc = app(SubmitDriverDocument::class)->execute($driver, 'id_front', UploadedFile::fake()->image('id.jpg'));

    expect(fn () => app(ReviewDriverDocument::class)->reject($doc, $admin, ''))
        ->toThrow(InvalidArgumentException::class);
});

it('presenter reports missing required documents', function (): void {
    $driver = Driver::factory()->create();
    $admin = makeAdmin();

    $doc = app(SubmitDriverDocument::class)->execute($driver, 'id_front', UploadedFile::fake()->image('id.jpg'));
    app(ReviewDriverDocument::class)->approve($doc, $admin);

    $badge = app(DriverVerificationPresenter::class)->describe($driver->refresh());

    expect($badge['verified'])->toBeFalse();
    expect($badge['missing'])->toContain('license_front');
    expect($badge['missing'])->toContain('insurance');
    expect($badge['missing'])->not->toContain('id_front');
});

it('un-verifying a vehicle bumps the driver back to in_review', function (): void {
    $driver = Driver::factory()->create(['verification_status' => 'in_review']);
    $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);
    $driver->update(['current_vehicle_id' => $vehicle->id]);
    $admin = makeAdmin();

    foreach (DriverVerificationPresenter::REQUIRED_DOCUMENTS as $type) {
        $doc = app(SubmitDriverDocument::class)->execute($driver, $type, UploadedFile::fake()->image($type.'.jpg'));
        app(ReviewDriverDocument::class)->approve($doc, $admin);
    }
    app(VerifyVehicle::class)->verify($vehicle->refresh(), $admin);
    expect($driver->refresh()->verification_status)->toBe('verified');

    app(VerifyVehicle::class)->unverify($vehicle->refresh(), $admin, 'inspection failed');

    expect($driver->refresh()->verification_status)->toBe('in_review');
});
