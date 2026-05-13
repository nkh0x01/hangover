<?php

declare(strict_types=1);

use App\Modules\Identity\Models\User;
use App\Modules\Support\Actions\RaiseFraudFlag;
use App\Modules\Support\Actions\RaiseSosEvent;
use App\Modules\Support\Actions\SubmitComplaint;
use App\Modules\Support\Actions\SuspendUser;
use App\Modules\Support\Models\FraudFlag;
use App\Modules\Support\Models\SosEvent;
use App\Modules\Support\Models\SupportTicket;
use App\Support\Geo\Point;

it('opens an SOS event in `open` state', function (): void {
    $user = User::factory()->create();

    $event = app(RaiseSosEvent::class)->execute(
        user: $user,
        ride: null,
        location: new Point(41.71, 44.82),
        body: 'help',
    );

    expect($event)->toBeInstanceOf(SosEvent::class);
    expect($event->status)->toBe('open');
    expect($event->user_id)->toBe($user->id);
});

it('SOS acknowledge + resolve flow', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->create(['type' => 'admin']);
    $action = app(RaiseSosEvent::class);

    $event = $action->execute($user, null, null, 'test');
    $event = $action->acknowledge($event, $admin);
    expect($event->status)->toBe('acknowledged');
    expect($event->acknowledged_by_user_id)->toBe($admin->id);

    $event = $action->resolve($event, $admin, 'resolved');
    expect($event->status)->toBe('resolved');

    $false = $action->execute($user, null, null, null);
    $false = $action->resolve($false, $admin, 'false_alarm');
    expect($false->status)->toBe('false_alarm');
});

it('safety complaints are bumped to urgent priority', function (): void {
    $reporter = User::factory()->create();

    $ticket = app(SubmitComplaint::class)->execute(
        reporter: $reporter,
        category: 'safety',
        subject: 'unsafe driving',
        body: 'driver was speeding through residential areas',
    );

    expect($ticket)->toBeInstanceOf(SupportTicket::class);
    expect($ticket->priority)->toBe('urgent');
    expect($ticket->status)->toBe('open');
    expect($ticket->messages()->count())->toBe(1);
});

it('other complaint categories use default priorities', function (): void {
    $reporter = User::factory()->create();

    $payment = app(SubmitComplaint::class)->execute($reporter, 'payment', 's', 'b');
    $bug = app(SubmitComplaint::class)->execute($reporter, 'app_bug', 's', 'b');
    $other = app(SubmitComplaint::class)->execute($reporter, 'other', 's', 'b');

    expect($payment->priority)->toBe('high');
    expect($bug->priority)->toBe('normal');
    expect($other->priority)->toBe('normal');
});

it('rejects unknown complaint categories', function (): void {
    $reporter = User::factory()->create();
    expect(fn () => app(SubmitComplaint::class)->execute($reporter, 'unknown', 's', 'b'))
        ->toThrow(InvalidArgumentException::class);
});

it('raises a fraud flag with the right shape', function (): void {
    $subject = User::factory()->create();
    $admin = User::factory()->create(['type' => 'admin']);

    $flag = app(RaiseFraudFlag::class)->execute(
        subject: $subject,
        kind: 'multi_account',
        severity: 'warn',
        evidence: ['phone' => '+9955...'],
        raisedBy: $admin,
    );

    expect($flag)->toBeInstanceOf(FraudFlag::class);
    expect($flag->raised_by)->toBe('admin');
    expect($flag->raised_by_user_id)->toBe($admin->id);
    expect($flag->evidence)->toMatchArray(['phone' => '+9955...']);
});

it('rejects unknown fraud kinds / severities', function (): void {
    $subject = User::factory()->create();
    expect(fn () => app(RaiseFraudFlag::class)->execute($subject, 'unknown', 'warn'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(RaiseFraudFlag::class)->execute($subject, 'multi_account', 'maximum'))
        ->toThrow(InvalidArgumentException::class);
});

it('suspend → reinstate flow clears the suspension columns', function (): void {
    $user = User::factory()->create(['status' => 'active']);
    $admin = User::factory()->create(['type' => 'admin']);
    $action = app(SuspendUser::class);

    $user = $action->suspend($user, $admin, 'cancellation storm');
    expect($user->status)->toBe('suspended');
    expect($user->suspension_reason)->toBe('cancellation storm');
    expect($user->suspended_by_user_id)->toBe($admin->id);
    expect($user->suspended_at)->not->toBeNull();

    $user = $action->reinstate($user, $admin, 'manual review cleared');
    expect($user->status)->toBe('active');
    expect($user->suspended_at)->toBeNull();
    expect($user->suspension_reason)->toBeNull();
});

it('ban is its own terminal state', function (): void {
    $user = User::factory()->create(['status' => 'active']);
    $admin = User::factory()->create(['type' => 'admin']);

    $user = app(SuspendUser::class)->ban($user, $admin, 'fraud confirmed');
    expect($user->status)->toBe('banned');
    expect($user->isBlocked())->toBeTrue();
});

it('suspend requires a non-empty reason', function (): void {
    $user = User::factory()->create();
    $admin = User::factory()->create();

    expect(fn () => app(SuspendUser::class)->suspend($user, $admin, ''))
        ->toThrow(InvalidArgumentException::class);
});
