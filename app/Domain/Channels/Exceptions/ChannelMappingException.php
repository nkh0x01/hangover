<?php

namespace App\Domain\Channels\Exceptions;

use App\Domain\Exceptions\DomainException;

/**
 * Thrown when an inbound reservation references an external_room_id that
 * has no mapping to one of our room_types — we cannot promote it without
 * a human deciding which RoomType it should land on.
 */
class ChannelMappingException extends DomainException
{
}
