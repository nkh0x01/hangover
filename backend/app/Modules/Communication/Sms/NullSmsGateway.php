<?php

declare(strict_types=1);

namespace App\Modules\Communication\Sms;

use App\Modules\Communication\Contracts\SmsGateway;
use App\Modules\Communication\Contracts\SmsResult;
use Illuminate\Support\Facades\Log;

/**
 * Dev-friendly gateway: logs the SMS body instead of sending. Use only
 * in local/testing environments — SMS_DRIVER=null.
 */
final class NullSmsGateway implements SmsGateway
{
    public function send(string $phoneE164, string $body, string $purpose): SmsResult
    {
        Log::channel('sms')->info('SMS (null driver)', [
            'phone' => $phoneE164,
            'purpose' => $purpose,
            'body' => $body,
        ]);

        return SmsResult::ok('null-'.bin2hex(random_bytes(8)), 0.0);
    }
}
