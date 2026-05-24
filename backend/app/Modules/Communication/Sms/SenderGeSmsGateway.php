<?php

declare(strict_types=1);

namespace App\Modules\Communication\Sms;

use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Communication\Contracts\SmsResult;
use App\Support\Phone\GeorgianPhoneNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class SenderGeSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $sender,
        private readonly string $baseUrl,
        private readonly Client $http = new Client(['timeout' => 15.0]),
    ) {}

    public function send(string $phoneE164, string $body, string $purpose): SmsResult
    {
        if ($this->apiKey === '') {
            return SmsResult::failure('Sender.ge API key is not configured.');
        }

        try {
            $phone = GeorgianPhoneNumber::normalize($phoneE164);
        } catch (InvalidArgumentException $e) {
            Log::channel('sms')->warning('Sender.ge phone normalization failed', [
                'phone' => GeorgianPhoneNumber::mask($phoneE164),
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);

            return SmsResult::failure($e->getMessage());
        }

        $url = $this->endpointUrl();
        $destination = substr($phone, 4);

        try {
            $response = $this->http->post($url, [
                'http_errors' => false,
                'form_params' => [
                    'apikey' => $this->apiKey,
                    'smsno' => $this->sender,
                    'destination' => $destination,
                    'content' => $body,
                ],
            ]);

            $status = $response->getStatusCode();
            $raw = trim((string) $response->getBody());
            $ok = $this->isSuccessfulResponse($status, $raw);
            $responseId = $raw === '' ? null : $this->truncate($raw, 120);

            $context = [
                'endpoint' => $url,
                'phone' => GeorgianPhoneNumber::mask($phone),
                'purpose' => $purpose,
                'sender' => $this->sender,
                'http_status' => $status,
                'response' => $responseId,
            ];

            if ($ok) {
                Log::channel('sms')->info('Sender.ge send completed', $context);

                return SmsResult::ok($responseId);
            }

            Log::channel('sms')->warning('Sender.ge send failed', $context);

            return SmsResult::failure(
                sprintf('Sender.ge did not confirm delivery. HTTP %d: %s', $status, $responseId ?? '(empty response)'),
                $responseId,
            );
        } catch (GuzzleException $e) {
            Log::channel('sms')->warning('Sender.ge send failed', [
                'endpoint' => $url,
                'phone' => GeorgianPhoneNumber::mask($phone),
                'purpose' => $purpose,
                'sender' => $this->sender,
                'error' => $e->getMessage(),
            ]);

            return SmsResult::failure($e->getMessage());
        }
    }

    private function endpointUrl(): string
    {
        $base = rtrim($this->baseUrl, '/');

        if ($base === '') {
            return 'https://sender.ge/api/send.php';
        }

        if (str_ends_with($base, '.php')) {
            return $base;
        }

        return $base.'/api/send.php';
    }

    private function isSuccessfulResponse(int $status, string $body): bool
    {
        if ($status < 200 || $status >= 300 || $body === '') {
            return false;
        }

        return preg_match('/(error|invalid|fail|denied|incorrect|balance|blocked)/i', $body) !== 1;
    }

    private function truncate(string $value, int $limit): string
    {
        if (strlen($value) <= $limit) {
            return $value;
        }

        return substr($value, 0, $limit - 3).'...';
    }
}
