<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Push\FirebasePushGateway;
use App\Modules\Communication\Push\NullPushGateway;

/**
 * Outbound mobile-push contract. The Riding module dispatches pushes
 * through this seam so concrete delivery (FCM, APNs, mock) is swappable
 * without touching ride lifecycle code.
 *
 * Implementations:
 *   - {@see FirebasePushGateway}  prod / kreait
 *   - {@see NullPushGateway}      tests + local dev
 */
interface PushGateway
{
    /**
     * Send a single message to one device token.
     *
     * The `$data` map is the FCM `data` payload — kept flat (string/string)
     * so iOS background delivery doesn't drop fields. Map mirrors
     * `IncomingPush.fromFcmData` on the mobile side.
     *
     * @param array<string, string> $data
     */
    public function send(string $token, string $title, string $body, array $data = []): PushResult;

    /**
     * Multicast same payload to many tokens (used for system broadcasts
     * — never for ride offers, which target a single driver token).
     *
     * @param array<int, string> $tokens
     * @param array<string, string> $data
     * @return array<int, PushResult>
     */
    public function multicast(array $tokens, string $title, string $body, array $data = []): array;
}
