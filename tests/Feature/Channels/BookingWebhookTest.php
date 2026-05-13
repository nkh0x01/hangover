<?php

use App\Domain\Channels\Services\ChannelMappingService;
use App\Models\ChannelConnection;
use App\Models\ChannelReservation;
use App\Models\ChannelSyncLog;
use App\Models\Reservation;
use Tests\Support\PmsTestFactory;

beforeEach(function () {
    $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    $this->p = new PmsTestFactory();

    $this->booking = ChannelConnection::factory()->create([
        'property_id' => $this->p->property->id,
        'channel' => ChannelConnection::CHANNEL_BOOKING,
        'name' => 'Booking sandbox',
        'status' => ChannelConnection::STATUS_ACTIVE,
        'dry_run' => true,
        'credentials' => [
            'hotel_id' => '99999',
            'secret' => 'shh',
            'webhook_secret' => 'webhook-shared-secret',
        ],
        'settings' => ['sandbox' => true],
    ]);

    app(ChannelMappingService::class)->mapRoom(
        $this->booking,
        $this->p->standardType,
        'BKG-STD-WH',
        'Standard',
    );
});

function postBookingWebhook(ChannelConnection $c, array $payload, ?string $signOverride = null): \Illuminate\Testing\TestResponse
{
    $body = json_encode($payload);
    $secret = $c->credentials['webhook_secret'] ?? '';
    $sig = $signOverride ?? hash_hmac('sha256', $body, $secret);

    return test()->withHeaders([
        'X-Booking-Signature' => $sig,
        'Content-Type' => 'application/json',
    ])->postJson(route('webhooks.booking', $c), $payload);
}

it('rejects a webhook with an invalid signature', function () {
    $response = postBookingWebhook($this->booking, [
        'event' => 'reservation.created',
        'reservation' => ['reservation_id' => 'BKG-WH-1'],
    ], signOverride: 'not-the-right-mac');

    $response->assertStatus(401);
    expect(ChannelReservation::count())->toBe(0);
    expect(
        ChannelSyncLog::where('channel_connection_id', $this->booking->id)
            ->where('status', ChannelSyncLog::STATUS_FAILED)
            ->count()
    )->toBe(1);
});

it('accepts a signed reservation.created webhook and stages + processes it', function () {
    $response = postBookingWebhook($this->booking, [
        'event' => 'reservation.created',
        'reservation' => [
            'reservation_id' => 'BKG-WH-CREATE-1',
            'room_id' => 'BKG-STD-WH',
            'arrival_date' => '2026-12-01',
            'departure_date' => '2026-12-03',
            'adults' => 2,
            'children' => 0,
            'guest' => ['first_name' => 'Webhook', 'last_name' => 'Guest', 'email' => 'wh1@booking.test'],
            'total' => 300.0,
            'currency' => 'GEL',
        ],
    ]);

    $response->assertOk();
    $row = ChannelReservation::where('external_id', 'BKG-WH-CREATE-1')->first();
    expect($row->status)->toBe(ChannelReservation::STATUS_PROCESSED);
    expect($row->reservation_id)->not->toBeNull();
});

it('a reservation.cancelled webhook cancels the linked reservation via CancelReservation', function () {
    // First, create the reservation through a "created" webhook.
    postBookingWebhook($this->booking, [
        'event' => 'reservation.created',
        'reservation' => [
            'reservation_id' => 'BKG-WH-CXL-1',
            'room_id' => 'BKG-STD-WH',
            'arrival_date' => '2026-12-10',
            'departure_date' => '2026-12-12',
            'adults' => 1,
            'guest' => ['first_name' => 'WH', 'last_name' => 'Cxl', 'email' => 'wh-cxl@booking.test'],
        ],
    ]);

    $row = ChannelReservation::where('external_id', 'BKG-WH-CXL-1')->firstOrFail();
    $localId = $row->reservation_id;
    expect(Reservation::find($localId)->status)->toBe(Reservation::STATUS_CONFIRMED);

    postBookingWebhook($this->booking, [
        'event' => 'reservation.cancelled',
        'reservation' => ['reservation_id' => 'BKG-WH-CXL-1'],
    ])->assertOk();

    expect(Reservation::find($localId)->status)->toBe(Reservation::STATUS_CANCELLED);
});
