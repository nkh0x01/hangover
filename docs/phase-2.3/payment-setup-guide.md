# Payment Setup Guide

> Phase 2.3 deliverable. How to bring up payment gateways from "no
> creds" (current state, cash-only) to "production card payments via
> BOG / TBC Pay / Stripe". Each gateway is independent — wire them
> in any order.

Audience: SRE doing the cutover; backend devs wiring the SDK call;
Finance preparing the bank account + insurance.

## 0. Verify the cash path (today's state)

Confirm the cash flow works in production before changing anything:

```bash
# 1. Complete a real test ride.
# 2. Check the payment record:
mysql> SELECT id, ride_id, method, provider, status, amount, captured_at
       FROM payments ORDER BY id DESC LIMIT 5;

# 3. Check the driver wallet:
mysql> SELECT w.id, u.phone_e164, w.balance_cached, w.currency
       FROM wallets w JOIN users u ON u.id=w.user_id
       WHERE u.type='driver' AND w.balance_cached > 0;

# 4. Check the ledger:
mysql> SELECT id, wallet_id, kind, direction, amount, balance_after, occurred_at
       FROM transactions WHERE wallet_id = <id> ORDER BY id DESC LIMIT 10;
```

You should see one `payment` row + one `ride_payout` credit + one
`adjustment` debit per ride, with `balance_after` matching the
wallet's cached balance.

If something looks wrong, do NOT enable card payments. Resolve the
cash flow first.

## 1. BOG (Bank of Georgia) Payments

BOG is our primary card-processor target for Georgian customers. They
support 3D Secure, Apple/Google Pay, and recurring tokens.

### Prerequisites
- BOG merchant account approved.
- Test environment credentials (sandbox `client_id` / `client_secret`).
- Production credentials (will be issued after the test environment
  cutover sign-off).
- Public callback URL for `payments.bog.callback` — production
  domain only.

### Bring-up steps

1. Add to `.env` (staging first, prod later):
   ```ini
   BOG_BASE_URL=https://api.bog.ge/payments/v1/sandbox
   BOG_CLIENT_ID=<test_id>
   BOG_CLIENT_SECRET=<test_secret>
   BOG_MERCHANT_ID=<test_merchant>
   PAYMENT_CARD_GATEWAY=bog
   ```
2. Wire the TODO blocks in
   `app/Modules/Payment/Gateways/BogPaymentGateway.php`:
   - Implement `accessToken()` (cache for 50 min — BOG tokens are 1 h).
   - Implement `authorize()` body (the comment block is the right
     shape, just uncomment and fill).
   - Implement `capture()` + `refund()`.
   - Add the webhook controller at
     `App\Modules\Payment\Http\Webhooks\BogWebhookController`
     handling `payment.completed` / `payment.failed` events.
3. Add the webhook route to `app/Modules/Payment/routes/api.php`:
   ```php
   Route::post('webhooks/bog', BogWebhookController::class)
       ->withoutMiddleware(['api']);
   ```
4. Run the integration test in staging — book + complete one ride
   with a sandbox card.
5. After Finance + Legal sign-off, switch BOG_BASE_URL to production
   and rotate credentials.

### Common pitfalls
- BOG expects amounts in major units, NOT minor. Convert at the
  gateway boundary, never internally — `Money` stays minor everywhere.
- BOG's 3DS redirect is required for first-time cards. The mobile
  app needs to handle the redirect URL (deferred to Phase 2.4).
- BOG sandbox sometimes returns `408` after 30 s. Wrap `authorize()`
  in the retry policy from `config('payment.retry')`.

## 2. TBC Pay

TBC is our secondary card-processor target. Same shape as BOG.

### Bring-up steps

1. Add to `.env`:
   ```ini
   TBC_PAY_BASE_URL=https://api.tbcpay.ge/v1/sandbox
   TBC_PAY_API_KEY=<test_key>
   TBC_PAY_API_SECRET=<test_secret>
   TBC_PAY_CAMPAIGN_ID=<campaign>
   PAYMENT_CARD_GATEWAY=tbc_pay     # if switching from BOG
   ```
2. Wire the TODO blocks in
   `app/Modules/Payment/Gateways/TbcPayPaymentGateway.php`.
3. TBC requires HMAC-SHA256 request signing on every call. Add the
   signer in the `client()` method.
4. Webhook controller + route, as for BOG.
5. Test in staging, switch to prod after sign-off.

### Failover strategy

We can run both BOG + TBC Pay in production. When the primary
returns `failed` with a code in `['GATEWAY_TIMEOUT', 'NETWORK', '503']`,
the SettleRidePayment action retries via the secondary. The fallback
ladder lives in `config('payment.fallback')` (Phase 2.4 addition).

## 3. Stripe (Apple Pay / Google Pay)

For international cards + Apple/Google Pay. Apple Pay needs
domain-verification + a merchant identity certificate.

### Bring-up steps

1. `composer require stripe/stripe-php`
2. Add to `.env`:
   ```ini
   STRIPE_SECRET_KEY=sk_test_...
   STRIPE_WEBHOOK_SECRET=whsec_...
   PAYMENT_APPLE_PAY_GATEWAY=stripe
   PAYMENT_GOOGLE_PAY_GATEWAY=stripe
   ```
3. Wire the TODO blocks in
   `app/Modules/Payment/Gateways/StripePaymentGateway.php`. Use
   PaymentIntents with `capture_method = 'manual'` so we can confirm
   the fare at ride end.
4. Webhook controller at `StripeWebhookController` handling
   `payment_intent.succeeded` / `.payment_failed` / `.requires_action`.
5. For Apple Pay specifically:
   - Add the merchant identifier in App Store Connect.
   - Generate the merchant identity certificate via Apple.
   - Upload it to Stripe → Settings → Apple Pay → Add domain.
   - Verify the domain via Stripe's hosted file challenge.
6. Test on a real iPhone + Apple Pay sandbox card.

## 4. Switching the active card gateway

Live switch (no code change required):

```ini
# /etc/hangover/.env
PAYMENT_CARD_GATEWAY=tbc_pay   # was: bog
```

Then:

```bash
php artisan config:cache
php artisan queue:restart
```

The next ride that settles with `method = card` will route to TBC Pay
without redeploying. Already in-flight authorizations against BOG
will complete cleanly because the gateway is resolved per-action.

## 5. Driver payout cycle

For pilot, payouts are manual. Weekly query:

```sql
SELECT
  u.id,
  u.name,
  u.phone_e164,
  d.id AS driver_id,
  w.balance_cached,
  w.currency,
  d.iban_encrypted IS NOT NULL AS has_iban
FROM wallets w
JOIN users u ON u.id = w.user_id
JOIN drivers d ON d.user_id = u.id
WHERE u.type = 'driver'
  AND w.balance_cached > 0
ORDER BY w.balance_cached DESC;
```

Finance:
1. Exports to CSV.
2. Initiates wire transfers via the bank's batch upload.
3. After confirmation, posts a `withdrawal` debit per driver via
   `WalletPoster::post()` — leaving balance at 0.
4. (Phase 2.4) Automated `PayoutAggregator` reconciles + creates
   `payouts` rows in `pending` for SRE to mark `paid` after the bank
   confirms.

The withdrawal debit is what zeroes the driver wallet. The audit
trail keeps the full history of ride_payout + commission +
withdrawal forever.

## 6. Refund flow (operations)

In Filament admin:

1. `Finance → Payments → click the payment`.
2. Click the `Issue refund` action (Phase 2.4 wires the form;
   for now use a tinker session).

From a tinker session (current state):

```php
$payment = \App\Modules\Payment\Models\Payment::find(123);
$admin = auth()->user();
app(\App\Modules\Payment\Actions\IssueRideRefund::class)->execute(
    $payment,
    \App\Support\Money::fromDecimal('5.00', $payment->currency),
    'driver took the long way',
    $admin,
);
```

The action handles:
- Gateway refund call (cash → no-op; card → real API call).
- Customer wallet credit.
- Driver wallet clawback (pro-rated).
- Payment status transition.
- Audit log entry.

If the gateway returns `failed`, the refund row is created with
`status = failed` and no wallet movement happens — Finance retries
or escalates.

## 7. Monitoring + alerts

Watch these signals in production:

| Signal                                                   | Threshold | Response                                          |
|----------------------------------------------------------|-----------|---------------------------------------------------|
| `payments.status = failed` rate                          | > 2% / h  | Page on-call SRE                                  |
| `refunds.amount` daily sum                               | > 5% of gross daily | Investigate; page Finance lead        |
| `wallets.balance_cached < 0` count                       | > 10      | Customer bookings are running ahead of settlement |
| Sentry events tagged `payment.gateway.error`             | any spike | Investigate; switch gateway if persistent         |
| `transactions` count divergent from `payments * 2`       | divergent | Ledger inconsistency — escalate immediately       |
| `MoneyAuditLogger` writes per minute                     | 0 for > 5 min during open hours | Audit pipeline broken    |

All visible from `/admin → Finance → Finance overview` + Sentry.

## 8. Roll back a gateway

If a newly-enabled gateway misbehaves:

```ini
PAYMENT_CARD_GATEWAY=null
```

```bash
php artisan config:cache
php artisan queue:restart
```

All subsequent `card` settlements will fail loudly with
`GATEWAY_NOT_WIRED`. The mobile app's card option must be hidden
until you re-enable — talk to mobile lead before a long rollback.

## 9. Data retention

- `payments`, `refunds`, `payouts`: keep forever (Georgian tax law:
  5 years minimum).
- `transactions`: keep forever — the ledger is the source of truth.
- `activity_log` (money entries): keep 3 years, then archive.
- `storage/logs/payment.log`: rotated daily, retained 60 days.

Anonymisation after 5 years: customer PII can be redacted from the
denormalised `customer_id` joins; the ledger numbers stay.
