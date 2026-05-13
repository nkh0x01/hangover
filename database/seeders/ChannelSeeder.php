<?php

namespace Database\Seeders;

use App\Domain\Channels\Providers\MockChannelService;
use App\Domain\Channels\Services\ChannelMappingService;
use App\Models\ChannelConnection;
use App\Models\ChannelSyncLog;
use App\Models\Property;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $property = Property::query()->first();
        if (! $property) {
            return;
        }

        $mock = ChannelConnection::firstOrCreate(
            [
                'property_id' => $property->id,
                'channel' => ChannelConnection::CHANNEL_MOCK,
                'name' => 'Mock OTA (sandbox)',
            ],
            [
                'status' => ChannelConnection::STATUS_ACTIVE,
                'credentials' => ['token' => 'mock-demo-token'],
                'settings' => ['currency' => $property->base_currency, 'timezone' => $property->timezone],
                'error_count' => 0,
            ],
        );

        // Always have at least one paused real-OTA connection visible in the UI
        // so the channels list shows the multi-channel intent — even though
        // their providers throw NotImplemented.
        ChannelConnection::firstOrCreate(
            [
                'property_id' => $property->id,
                'channel' => ChannelConnection::CHANNEL_BOOKING,
                'name' => 'Booking.com (stub)',
            ],
            [
                'status' => ChannelConnection::STATUS_PAUSED,
                'credentials' => null,
                'settings' => ['currency' => $property->base_currency],
                'error_count' => 0,
            ],
        );

        $mapper = app(ChannelMappingService::class);

        $i = 0;
        foreach ($property->roomTypes as $type) {
            $extId = 'EXT-'.strtoupper(substr($type->slug, 0, 8)).'-'.str_pad((string) (++$i), 3, '0', STR_PAD_LEFT);
            $mapper->mapRoom($mock, $type, $extId, $type->name.' (channel)');
            $mapper->mapRate(
                $mock,
                $type,
                'RATE-'.strtoupper(substr($type->slug, 0, 6)).'-BAR',
                'Best Available Rate',
                markupPercent: 5,
            );
        }

        // Seed inbound mock reservations: one that imports cleanly, one that
        // hits a conflict because the rooms are already taken. The conflicting
        // payload is also persisted as a staged ChannelReservation so the
        // /channels/conflicts page has a row to render without anyone hitting
        // the "Pull" button first.
        $checkIn  = now()->copy()->addDays(14)->toDateString();
        $checkOut = now()->copy()->addDays(17)->toDateString();
        MockChannelService::reset();
        MockChannelService::seedDefaultInbox($mock, $checkIn, $checkOut, 2);

        if ($mock->channelReservations()->where('status', \App\Models\ChannelReservation::STATUS_CONFLICT)->doesntExist()) {
            // Block every standard room across the conflict dates so the
            // demonstration is deterministic.
            $standard = $property->roomTypes()->first();
            $rooms = $standard?->rooms()->limit(3)->get() ?? collect();
            $payload = [
                'guest' => ['first_name' => 'Nino', 'last_name' => 'Kapanadze', 'email' => 'nino.k@mock.test'],
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'adults' => 2, 'children' => 0,
                'external_room_id' => app(ChannelMappingService::class)
                    ->externalForRoomType($mock, $standard),
                'total' => 270.0, 'currency' => $property->base_currency,
            ];
            \App\Models\ChannelReservation::firstOrCreate(
                ['channel_connection_id' => $mock->id, 'external_id' => 'MOCK-CONFLICT-1'],
                [
                    'hash' => hash('sha256', json_encode($payload)),
                    'raw_payload' => $payload,
                    'status' => \App\Models\ChannelReservation::STATUS_CONFLICT,
                    'received_at' => now()->subMinutes(5),
                    'processed_at' => now()->subMinutes(4),
                    'error' => 'Room not available for the requested period — clashes with a direct booking.',
                ],
            );
        }

        // A few historical log rows for the detail/log screenshots.
        if ($mock->syncLogs()->count() === 0) {
            ChannelSyncLog::factory()->count(3)->create([
                'channel_connection_id' => $mock->id,
            ]);
            ChannelSyncLog::factory()->failed()->create([
                'channel_connection_id' => $mock->id,
            ]);
        }
    }
}
