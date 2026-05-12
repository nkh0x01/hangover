<?php

declare(strict_types=1);

namespace App\Modules\Communication\Sms;

use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Communication\Contracts\SmsResult;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

final class TwilioSmsGateway implements SmsGateway
{
    public function __construct(
        private readonly string $accountSid,
        private readonly string $authToken,
        private readonly string $from,
        private readonly Client $http = new Client(['timeout' => 5.0]),
    ) {}

    public function send(string $phoneE164, string $body, string $purpose): SmsResult
    {
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        try {
            $response = $this->http->post($url, [
                'auth' => [$this->accountSid, $this->authToken],
                'form_params' => [
                    'To' => $phoneE164,
                    'From' => $this->from,
                    'Body' => $body,
                ],
            ]);

            $payload = json_decode((string) $response->getBody(), true);

            return SmsResult::ok($payload['sid'] ?? null);
        } catch (GuzzleException $e) {
            Log::channel('sms')->warning('Twilio send failed', ['error' => $e->getMessage()]);

            return SmsResult::failure($e->getMessage());
        }
    }
}
