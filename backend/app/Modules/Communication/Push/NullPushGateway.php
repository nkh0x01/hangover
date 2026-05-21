<?php

declare(strict_types=1);

namespace App\Modules\Communication\Push;

use App\Modules\Communication\Contracts\PushGateway;
use App\Modules\Communication\Contracts\PushResult;
use Illuminate\Support\Facades\Log;

/**
 * No-op push gateway used by tests + the local dev flavour.
 *
 * Every send is logged at debug-level so the test suite can assert
 * push delivery semantics without touching Firebase.
 */
final readonly class NullPushGateway implements PushGateway
{
    public function send(string $token, string $title, string $body, array $data = []): PushResult
    {
        Log::channel('push')->debug('NullPushGateway::send', [
            'token' => substr($token, 0, 8).'…',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        return PushResult::ok(messageId: 'null-'.bin2hex(random_bytes(8)));
    }

    public function multicast(array $tokens, string $title, string $body, array $data = []): array
    {
        return array_map(
            fn (string $token): PushResult => $this->send($token, $title, $body, $data),
            $tokens,
        );
    }
}
