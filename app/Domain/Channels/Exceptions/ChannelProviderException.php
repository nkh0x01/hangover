<?php

namespace App\Domain\Channels\Exceptions;

use App\Domain\Exceptions\DomainException;

/**
 * Thrown by a channel provider when it cannot talk to the remote side —
 * network failure, auth rejection, malformed response, etc.
 */
class ChannelProviderException extends DomainException
{
}
