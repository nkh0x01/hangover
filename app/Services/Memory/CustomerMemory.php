<?php

namespace App\Services\Memory;

use App\Models\Customer;
use App\Services\AI\ClaudeClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Updates a customer's `profile_json` with structured facts extracted
 * from each conversation turn. Uses Haiku to keep cost negligible.
 *
 * Schema is intentionally loose — any new fact the model finds is
 * merged in; existing facts are only overwritten when the new value is
 * non-null.
 */
class CustomerMemory
{
    private const SCHEMA_HINT = <<<JSON
{
  "ecosystem": "apple|samsung|xiaomi|huawei|other|null",
  "phone_model": "string|null",
  "preferred_branch": "string|null",
  "budget_range": "e.g. 200-400|null",
  "language_style": "formal|casual|emotional|null",
  "last_categories": ["string"],
  "do_not_recommend": ["sku"],
  "vip_signal": "boolean|null",
  "notes": "short freeform string|null"
}
JSON;

    public function __construct(private ClaudeClient $claude) {}

    public function extractFromTurn(Customer $customer, string $customerText, string $assistantText): void
    {
        if (trim($customerText) === '' && trim($assistantText) === '') {
            return;
        }

        $system = "You are a memory extractor for a Georgian gadget retailer. " .
            "Read the latest customer message and the assistant's reply and emit ONLY a JSON object " .
            "matching this schema (keys may be omitted; never invent facts):\n" .
            self::SCHEMA_HINT;

        $user = "CUSTOMER: $customerText\n\nASSISTANT: $assistantText\n\nReturn JSON only.";

        try {
            $raw = $this->claude->complete($system, $user, light: true);
        } catch (Throwable $e) {
            Log::info('memory.skip', ['reason' => $e->getMessage()]);
            return;
        }

        $json = $this->extractJson($raw);
        if (! is_array($json) || $json === []) {
            return;
        }

        // Strip nulls and empty arrays — don't overwrite real values with blanks.
        $clean = array_filter($json, fn ($v) => $v !== null && $v !== '' && $v !== []);
        if ($clean === []) {
            return;
        }

        $customer->patchMemory($clean);
    }

    private function extractJson(string $raw): ?array
    {
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            return json_decode($m[0], true);
        }
        return null;
    }
}
