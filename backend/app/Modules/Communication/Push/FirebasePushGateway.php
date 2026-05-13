<?php

declare(strict_types=1);

namespace App\Modules\Communication\Push;

use App\Modules\Communication\Contracts\PushGateway;
use App\Modules\Communication\Contracts\PushResult;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * FCM-backed push gateway. Wraps `kreait/laravel-firebase`.
 *
 * The mobile clients read `kind` + ride identifiers from the `data`
 * map, so we always set both `notification` (for system tray rendering
 * when the app is backgrounded) and `data` (for in-app routing in
 * foreground / cold-start).
 *
 * Channels:
 *   - `hangover_rides`  customer-side, default importance
 *   - `hangover_offers` driver-side, MAX importance + sound + vibration
 *     (the offer modal must wake the device).
 */
final readonly class FirebasePushGateway implements PushGateway
{
    public function __construct(
        private Messaging $messaging,
    ) {}

    public function send(string $token, string $title, string $body, array $data = []): PushResult
    {
        try {
            $message = $this->buildMessage($title, $body, $data)
                ->toToken($token);
            $result = $this->messaging->send($message);
            $messageId = is_string($result['name'] ?? null) ? $result['name'] : 'fcm';

            return PushResult::ok(messageId: $messageId);
        } catch (MessagingException $e) {
            $errorCode = $e->errors()[0]['errorCode'] ?? $e->getCode();
            $tokenInvalid = in_array($errorCode, [
                'UNREGISTERED',
                'INVALID_ARGUMENT',
                'NOT_FOUND',
            ], true);

            Log::channel('push')->warning('FCM send failed', [
                'error_code' => $errorCode,
                'message' => $e->getMessage(),
                'token_invalid' => $tokenInvalid,
            ]);

            return PushResult::failed(
                code: (string) $errorCode,
                message: $e->getMessage(),
                tokenInvalid: $tokenInvalid,
            );
        } catch (Throwable $e) {
            Log::channel('push')->error('FCM send threw', ['error' => $e->getMessage()]);

            return PushResult::failed(code: 'UNKNOWN', message: $e->getMessage());
        }
    }

    public function multicast(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return [];
        }

        try {
            $message = $this->buildMessage($title, $body, $data);
            $report = $this->messaging->sendMulticast($message, $tokens);

            $results = [];
            $i = 0;
            foreach ($report->getItems() as $item) {
                if ($item->isSuccess()) {
                    $results[$i] = PushResult::ok(messageId: (string) $item->target()->value());
                } else {
                    $invalid = method_exists($item, 'isTokenInvalid') ? $item->isTokenInvalid() : false;
                    $err = $item->error();
                    $code = 'UNKNOWN';
                    if ($err instanceof MessagingException) {
                        $code = (string) ($err->errors()[0]['errorCode'] ?? 'UNKNOWN');
                    }
                    $results[$i] = PushResult::failed(
                        code: $code,
                        message: $err?->getMessage() ?? 'unknown',
                        tokenInvalid: $invalid,
                    );
                }
                $i++;
            }

            return $results;
        } catch (Throwable $e) {
            Log::channel('push')->error('FCM multicast threw', ['error' => $e->getMessage()]);

            return array_fill(0, count($tokens), PushResult::failed('UNKNOWN', $e->getMessage()));
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    private function buildMessage(string $title, string $body, array $data): CloudMessage
    {
        $channel = ($data['kind'] ?? '') === 'ride.offered' ? 'hangover_offers' : 'hangover_rides';
        $priority = $channel === 'hangover_offers' ? 'high' : 'normal';

        return CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => $priority,
                'notification' => [
                    'channel_id' => $channel,
                    'default_sound' => true,
                    'default_vibrate_timings' => true,
                ],
            ]))
            ->withApnsConfig(ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => $priority === 'high' ? '10' : '5',
                    'apns-push-type' => 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => $channel === 'hangover_offers' ? 'offer.caf' : 'default',
                        'content-available' => 1,
                    ],
                ],
            ]));
    }
}
