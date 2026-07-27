<?php

namespace App\Services\Sales;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin Bank of Georgia (BOG) e-commerce API client used by the payment
 * callback flow. Handles OAuth2 (client_credentials), the authoritative
 * "receipt" status re-fetch, and Callback-Signature verification.
 *
 * Trust model: the payment callback body is NOT trusted on its own — we
 * always re-fetch the order status from BOG (receipt endpoint) with our
 * own Bearer token and decide from that.
 */
class BogClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 20, 'http_errors' => false]);
    }

    /** OAuth2 client_credentials → Bearer access token (or null). */
    public function accessToken(): ?string
    {
        $key = config('payments.api_key');
        $secret = config('payments.api_secret');
        if (! $key || ! $secret) {
            return null;
        }

        try {
            $res = $this->http->post(config('payments.bog.oauth_url'), [
                'auth' => [$key, $secret],
                'form_params' => ['grant_type' => 'client_credentials'],
            ]);

            return json_decode((string) $res->getBody(), true)['access_token'] ?? null;
        } catch (Throwable $e) {
            Log::error('bog.oauth.failed', ['msg' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Authoritative payment details from BOG's receipt endpoint.
     * GET https://api.bog.ge/payments/v1/receipt/{order_id}
     *
     * @return array|null decoded JSON, or null on failure
     */
    public function orderDetails(string $bogOrderId): ?array
    {
        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        try {
            $url = rtrim((string) config('payments.bog.receipt_url'), '/') . '/' . rawurlencode($bogOrderId);
            $res = $this->http->get($url, [
                'headers' => ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'],
            ]);

            if ($res->getStatusCode() >= 400) {
                Log::warning('bog.receipt.http_error', ['order' => $bogOrderId, 'status' => $res->getStatusCode()]);

                return null;
            }

            return json_decode((string) $res->getBody(), true) ?: null;
        } catch (Throwable $e) {
            Log::error('bog.receipt.exception', ['order' => $bogOrderId, 'msg' => $e->getMessage()]);

            return null;
        }
    }

    /** Authoritative status key (order_status.key) for a BOG order, or null. */
    public function orderStatusKey(string $bogOrderId): ?string
    {
        $details = $this->orderDetails($bogOrderId);

        return $details['order_status']['key'] ?? null;
    }

    /**
     * Verify the Callback-Signature header (base64 RSA-SHA256 over the RAW
     * request body) against BOG's fixed public key.
     *
     * @return bool|null  true=valid, false=present-but-invalid, null=no
     *                    signature/key to check (caller falls back to re-fetch)
     */
    public function verifyCallbackSignature(string $rawBody, ?string $signatureB64): ?bool
    {
        if ($signatureB64 === null || $signatureB64 === '') {
            return null; // header optional; nothing to verify
        }

        $pem = config('payments.bog.public_key');
        if (! $pem) {
            return null;
        }

        $sig = base64_decode($signatureB64, true);
        if ($sig === false) {
            return false;
        }

        $ok = openssl_verify($rawBody, $sig, $pem, OPENSSL_ALGO_SHA256);

        return $ok === 1;
    }
}
