<?php

namespace App\Domain\Channels\Services;

use App\Domain\Channels\Exceptions\ChannelMappingException;
use App\Models\ChannelConnection;
use App\Models\ChannelRateMapping;
use App\Models\ChannelRoomMapping;
use App\Models\RoomType;

/**
 * Translates between our internal IDs (room_type_id, rate_plan_id) and the
 * provider's external IDs. Every other service in the channel domain
 * goes through this layer — providers never see our DB IDs and we never
 * persist their IDs anywhere outside the mapping tables.
 */
class ChannelMappingService
{
    public function mapRoom(ChannelConnection $connection, RoomType $roomType, string $externalRoomId, ?string $externalRoomName = null): ChannelRoomMapping
    {
        return ChannelRoomMapping::updateOrCreate(
            [
                'channel_connection_id' => $connection->id,
                'external_room_id' => $externalRoomId,
            ],
            [
                'room_type_id' => $roomType->id,
                'external_room_name' => $externalRoomName,
            ],
        );
    }

    public function unmapRoom(ChannelConnection $connection, string $externalRoomId): void
    {
        ChannelRoomMapping::where('channel_connection_id', $connection->id)
            ->where('external_room_id', $externalRoomId)
            ->delete();
    }

    public function roomTypeForExternal(ChannelConnection $connection, string $externalRoomId): RoomType
    {
        $mapping = ChannelRoomMapping::where('channel_connection_id', $connection->id)
            ->where('external_room_id', $externalRoomId)
            ->first();

        if (! $mapping) {
            throw new ChannelMappingException(
                "No room mapping for external_room_id={$externalRoomId} on connection {$connection->id}.",
            );
        }

        return $mapping->roomType()->firstOrFail();
    }

    public function externalForRoomType(ChannelConnection $connection, RoomType $roomType): ?string
    {
        return ChannelRoomMapping::where('channel_connection_id', $connection->id)
            ->where('room_type_id', $roomType->id)
            ->value('external_room_id');
    }

    public function mapRate(
        ChannelConnection $connection,
        RoomType $roomType,
        string $externalRateId,
        ?string $externalRateName = null,
        ?float $markupPercent = null,
        ?float $markupAbs = null,
    ): ChannelRateMapping {
        return ChannelRateMapping::updateOrCreate(
            [
                'channel_connection_id' => $connection->id,
                'external_rate_id' => $externalRateId,
            ],
            [
                'room_type_id' => $roomType->id,
                'external_rate_name' => $externalRateName,
                'markup_percent' => $markupPercent,
                'markup_abs' => $markupAbs,
            ],
        );
    }

    public function applyMarkup(float $base, ?ChannelRateMapping $rate): float
    {
        if (! $rate) {
            return round($base, 2);
        }

        $amount = $base;
        if ($rate->markup_percent !== null) {
            $amount = $amount * (1 + ((float) $rate->markup_percent / 100));
        }
        if ($rate->markup_abs !== null) {
            $amount += (float) $rate->markup_abs;
        }

        return round($amount, 2);
    }
}
