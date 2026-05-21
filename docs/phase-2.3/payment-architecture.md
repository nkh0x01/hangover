# Payment Architecture

> Phase 2.3 deliverable. The end-to-end shape of how money moves
> through Hangover: which modules touch which tables, how a ride's
> fare becomes a captured payment, a driver wallet credit, and
> eventually a weekly payout.

## Modules at a glance

```
┌────────────────────────┐    ┌────────────────────────┐
│  Riding                 │    │  Pricing                │
│  CompleteTrip           │    │  CommissionCalculator   │
└───────────┬────────────┘    └────────────┬───────────┘
            │                              │
            │ triggers                     │ rate split
            ▼                              ▼
┌──────────────────────────────────────────────────────┐
│  Payment                                              │
│   ├─ SettleRidePayment      ◄── ride.completed         │
│   ├─ IssueRideRefund        ◄── admin action / API     │
│   ├─ IssueCancellationFee   ◄── cancel paths           │
│   ├─ PaymentGatewayManager  → routes by method          │
│   ├─ Gateways/{Cash, Null, Wallet, Stripe, Bog, TbcPay}│
│   ├─ RideReceiptGenerator                              │
│   └─ MoneyAuditLogger       → spatie/activitylog       │
└──────────────────────────┬──────────────────────────┘
                           │
                           ▼ ledger writes
┌────────────────────────────────────────────────┐
│  Wallet                                          │
│   ├─ WalletPoster (atomic, FOR UPDATE, idempotent)│
│   ├─ Wallet model + Transaction model            │
│   └─ Filament: WalletResource, TransactionResource│
└────────────────────────────────────────────────┘
```

The Riding module never imports the Payment models directly. The
coupling point is `CompleteTrip → SettleRidePayment`, which is
constructor-injected. Refunds, cancellation fees, and the receipt
generator are all idempotent + transactional so worker retries are
safe.

## Database tables

| Table              | Purpose                                                                 |
|---------------------|--------------------------------------------------------------------------|
| `payment_methods`   | Customer-stored card / wallet tokens (Phase 3+ for cards)               |
| `payments`          | One row per attempted settlement (cash / card / wallet)                 |
| `refunds`           | One row per refund attempt against a `payments` row                     |
| `payouts`           | Weekly driver payout cycles                                             |
| `wallets`           | One per user; cached `balance_cached` + `held_amount`                   |
| `transactions`      | Append-only ledger; every row has `balance_after` for forensic auditing |
| `activity_log`      | spatie/activitylog — money-related entries use `log_name = 'money'`     |

The schema is already provisioned by the Phase 1 migrations. Phase 2.3
adds:

- `Driver.commission_rate_override` was already present.
- `Ride.commission_amount` + `Ride.driver_earnings` (also already
  present from Phase 1.5; SettleRidePayment now writes them).
- No new migrations in this phase.

## Money representation

We use the existing `App\Support\Money` value object throughout the
payment + wallet layer. It stores amounts as integer minor units
(`int $minor`) and exposes `add`, `subtract`, `multiply`, plus
`fromDecimal()` / `toDecimal()` for boundary conversions.

The `Money` object is the only thing the wallet poster + commission
calculator accept. Everywhere we cross the float/decimal boundary
(reading `final_amount` from the DB, returning the receipt as JSON)
we convert deliberately.

```php
$fare = Money::fromDecimal((float) $ride->final_amount, $ride->currency);
$split = $commission->split($fare, $driver, $ride->city);
// $split['commission'] :: Money
// $split['driverEarnings'] :: Money
```

## Commission rules

Configured in `config/commission.php` + read by
`Pricing\CommissionCalculator::resolveRate()`:

1. `Driver.commission_rate_override` if non-null.
2. `config('commission.by_city.<slug>')`.
3. `City.default_commission_rate` (DB column).
4. `config('commission.default_rate')` (default `0.15`).

Always clamped to `[min_amount, max_amount]`. The invariant
`fare = commission + driverEarnings` always holds — when the clamp
trims commission, the leftover goes to the driver, never to the
platform.

Worked example, fare = 20.00 GEL, rate 15%:

```
fare        = 20.00 GEL  (minor: 2000)
rate        = 0.15
commission  = 3.00 GEL   (minor: 300)
driverEarn  = 17.00 GEL  (minor: 1700)
```

## Payment gateways

```
PaymentGateway (interface)
  authorize(amountMinor, currency, methodToken, rideUlid): GatewayResult
  capture(providerIntentId): GatewayResult
  refund(providerIntentId, amountMinor): GatewayResult
```

Implementations:

| Gateway                  | Live in pilot? | Notes                                                       |
|--------------------------|----------------|-------------------------------------------------------------|
| `CashPaymentGateway`     | ✅              | No external call. Reports `captured` immediately.            |
| `NullPaymentGateway`     | ✅ (tests)      | Logs + returns success. Used when card creds are absent.    |
| `WalletPaymentGateway`   | ✅              | Routes the settlement through `WalletPoster` instead.       |
| `StripePaymentGateway`   | ❌ (stub)       | Structural shell. Throws `RuntimeException` until SDK wired.|
| `BogPaymentGateway`      | ❌ (stub)       | HTTP scaffold + access-token flow doc'd inline.             |
| `TbcPayPaymentGateway`   | ❌ (stub)       | Same shape as BOG.                                          |

Routing: `config('payment.methods.<method>')` → gateway name →
`config('payment.gateways.<name>.class')` → constructor-injected via
the container. The resolver caches the instance per request.

`PaymentGatewayManager::forMethod('cash')` is the standard call. The
`payments.provider` enum value is derived from the gateway name +
the method (cash/wallet stay literal; card routes to whichever real
provider settled the transaction).

## Settlement flow (cash)

```
driver taps "Complete trip"
  └─> Riding\CompleteTrip::execute()
        ├─> transition Ride → completed
        └─> Payment\SettleRidePayment::execute()
              ├─> PaymentGatewayManager::forMethod('cash')
              ├─> CashPaymentGateway::authorize()  (synthetic captured)
              ├─> Payment::create(status=captured)
              ├─> Ride.payment_id ← Payment.id
              ├─> CommissionCalculator::split(fare, driver, city)
              ├─> WalletPoster::post(driver, fare,        kind=ride_payout, +)
              ├─> WalletPoster::post(driver, commission,  kind=adjustment, -)
              └─> MoneyAuditLogger::record('payment.captured', ...)
```

Failure modes:

- Gateway returns `ok=false` → payment row saved with `status='failed'`
  and `failure_code`. Ride is NOT failed (driver still drove).
- Wallet poster throws → whole transaction rolls back → SettleRidePayment
  re-raises → CompleteTrip catches + logs (so the ride stays completed).
- Anywhere downstream → Sentry catches via `bootstrap/app.php`.

## Settlement flow (card)

```
driver taps "Complete trip"
  └─> Payment\SettleRidePayment::execute()
        ├─> PaymentGatewayManager::forMethod('card')   → e.g. 'bog'
        ├─> BogPaymentGateway::authorize()             → ok=true, status=authorized
        ├─> BogPaymentGateway::capture(intentId)        → ok=true, status=captured
        ├─> ... same wallet posts as cash ...
```

For the pilot we never reach this branch in production — the card
method is routed to `null` via `PAYMENT_CARD_GATEWAY=null` until BOG
credentials land.

## Refund flow

```
admin clicks "Issue refund"
  └─> Payment\IssueRideRefund::execute(payment, money, reason, admin)
        ├─> validates amount ≤ remaining
        ├─> Gateway::refund()
        ├─> Refund::create(status=succeeded|failed)
        ├─> Payment.status ← refunded | partially_refunded
        ├─> WalletPoster::post(customer, amount,        kind=refund, +)
        ├─> WalletPoster::post(driver,   clawbackAmt,   kind=adjustment, -)
        └─> MoneyAuditLogger::record('payment.refunded', ...)
```

Driver clawback is pro-rated by the same ratio the original ride used.
Cash refunds become wallet credits (customer applies them to the next
ride).

## Cancellation fee flow

```
customer cancels after grace window
  └─> Riding\CancelRide  (creates Payment via)
        └─> Payment\IssueCancellationFee::execute(ride, money, reason)
              ├─> Payment::create(method=cash, provider=cash, status=captured)
              ├─> Ride.payment_id ← Payment.id
              ├─> WalletPoster::post(customer, fee, kind=ride_charge, -)
              └─> MoneyAuditLogger::record('payment.cancellation_fee', ...)
```

Customer wallet may go negative — the next ride collects the
outstanding via wallet auto-debit.

## Receipts

`Payment\RideReceiptGenerator::generate($ride)` returns a structured
array with the ride, customer (phone masked), driver, timeline,
amounts (fare, commission, driver earnings, total), payment, and
refunds. `asText()` flattens it to a 60-column text receipt. PDF
generation is deferred to Phase 3.

## Audit trail

Every money-touching action calls `MoneyAuditLogger::record()` with:

- An event slug (`payment.captured`, `payment.refunded`, `payment.cancellation_fee`).
- The subject model (Payment / Refund).
- Amount in minor + currency.
- Metadata (gateway, ride_ulid, idempotency keys, rate, etc.).

Dual-write strategy:

1. `storage/logs/payment.log` (daily rotation, 60 day retention) for
   SRE incident response.
2. `activity_log` table via spatie/activitylog — queryable + joinable
   to the actor user. Filterable from `/admin → System → Activity`
   (Filament plugin lands in 2.4).

## Wallet ledger invariants

For every row in `transactions`:

- `direction = credit` → `balance_after = balance_before + amount`
- `direction = debit`  → `balance_after = balance_before - amount`
- `currency = wallet.currency`
- `occurred_at >= prior row's occurred_at` (timestamp monotonicity)

The `WalletPoster::post()` method takes a `FOR UPDATE` row lock so
two concurrent ride completions for the same driver can never both
read `balance_before` and write conflicting `balance_after` values.

Idempotency is keyed off `meta.idempotency_key` per wallet — re-posts
of the same key return the existing transaction without writing a
new row.

Holds use `wallets.held_amount` as a separate float column.
`hold() + release()` are no-op for the balance but produce `hold` /
`release` ledger rows for audit.

## Payouts

`payouts` table is provisioned but the actual cron + computation is
out of scope for Phase 2.3 (drivers are settled via wallet ledger
immediately; the weekly payout will be a separate `PayoutAggregator`
in Phase 2.4 that snapshots a week of `ride_payout` minus
`adjustment` rows per driver and produces a `payouts` row in
`status = pending` for Finance to disburse).

For pilot, manual wire transfers using the wallet balance read-out:

```sql
SELECT u.name, u.phone_e164, w.balance_cached, w.currency
FROM wallets w
JOIN users u ON u.id = w.user_id
WHERE u.type = 'driver'
  AND w.balance_cached > 0
ORDER BY w.balance_cached DESC;
```

## Configuration env vars

```ini
# config/commission.php
COMMISSION_DEFAULT_RATE=0.15
COMMISSION_MIN_AMOUNT=0.10
COMMISSION_MAX_AMOUNT=50.00
LEDGER_CURRENCY=GEL

# config/payment.php
PAYMENT_DEFAULT=cash
PAYMENT_CURRENCY=GEL
PAYMENT_CARD_GATEWAY=null         # or 'bog' / 'tbc_pay' / 'stripe'
PAYMENT_APPLE_PAY_GATEWAY=stripe
PAYMENT_GOOGLE_PAY_GATEWAY=stripe
PAYMENT_RETRY_ATTEMPTS=3

# Stripe (when wired)
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...

# BOG (when wired)
BOG_BASE_URL=https://api.bog.ge/payments/v1
BOG_CLIENT_ID=...
BOG_CLIENT_SECRET=...
BOG_MERCHANT_ID=...

# TBC Pay (when wired)
TBC_PAY_BASE_URL=https://api.tbcpay.ge/v1
TBC_PAY_API_KEY=...
TBC_PAY_API_SECRET=...
TBC_PAY_CAMPAIGN_ID=...
```

Without credentials, the apps continue to function — cash settlement
keeps working and any explicit "card" request fails loudly via the
gateway's `assertConfigured()` rather than silently using a sandbox.
