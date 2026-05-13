<?php

namespace App\Notifications;

use App\Models\ChannelConnection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Dispatched the first time a connection crosses the consecutive-failure
 * threshold so the duty manager sees it on the dashboard. Stored in the
 * `notifications` table (database channel) so the UI can render a badge
 * without re-querying the sync logs.
 */
class ChannelSyncFailed extends Notification
{
    use Queueable;

    public const FAILURE_THRESHOLD = 3;

    public function __construct(
        public readonly int $connectionId,
        public readonly string $connectionName,
        public readonly string $channel,
        public readonly string $error,
        public readonly int $errorCount,
    ) {
    }

    public static function from(ChannelConnection $connection, string $error): self
    {
        return new self(
            connectionId: $connection->id,
            connectionName: (string) $connection->name,
            channel: (string) $connection->channel,
            error: $error,
            errorCount: (int) $connection->error_count,
        );
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'connection_id' => $this->connectionId,
            'connection_name' => $this->connectionName,
            'channel' => $this->channel,
            'error' => $this->error,
            'error_count' => $this->errorCount,
        ];
    }
}
