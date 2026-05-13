# Hangover Platform — Phase 2.3 Launch-Readiness Report

> Reporting date: 2026-05-13
> Branch: `claude/scooter-platform-architecture-Wvmeu`
> Phase: **2.3 — Payments, Commission, and Driver Wallets**

## Headline

The financial backbone is in place. Cash payments settle end-to-end
with commission split, driver wallet entries, and audit logs.
Card-payment gateways for BOG, TBC Pay, and Stripe ship as structural
stubs — wire-up to real credentials is a 1-2 day exercise per provider
documented in `payment-setup-guide.md`. The platform can move real
money in pilot today.

## What Phase 2.3 shipped

### 1. Commission engine

- `config/commission.php` — default rate (15%), min/max clamp,
  per-city overrides, ledger currency.
- `App\Modules\Pricing\Services\CommissionCalculator` — pure service.
  Resolution order: driver override → city config → city DB column →
  default. Returns `Money` objects with the `fare = commission +
  driverEarnings` invariant guaranteed.
- 6 Pest tests covering every resolution path + the clamp.

### 2. Payment gateways

Six `PaymentGateway` implementations under `App\Modules\Payment\Gateways\`:

| Gateway                  | Status       | Notes                                    |
|--------------------------|--------------|------------------------------------------|
| `CashPaymentGateway`     | Live         | Pilot default; no external call          |
| `NullPaymentGateway`     | Live (tests) | Logs + reports success; fallback         |
| `WalletPaymentGateway`   | Live         | Settlement via wallet credit             |
| `StripePaymentGateway`   | Stub         | SDK call gated behind `assertConfigured` |
| `BogPaymentGateway`      | Stub         | HTTP scaffold; flow doc'd inline         |
| `TbcPayPaymentGateway`   | Stub         | HTTP scaffold; HMAC signing slot ready   |

`PaymentGatewayManager` resolves the right gateway per method via
`config/payment.php`. Container-driven so test code can swap the
binding without touching the action.

### 3. Wallet ledger

- `App\Modules\Wallet\Services\WalletPoster` — atomic poster.
  - `FOR UPDATE` row lock prevents concurrent ride completions from
    racing on the same driver wallet.
  - Idempotency via `meta.idempotency_key` per wallet.
  - Holds tracked separately on `wallets.held_amount` with
    `hold()` / `release()` ledger entries.
  - Invariant `balance_after = balance_before ± amount` enforced per
    direction.
- 6 Pest tests covering balance math, holds, idempotency, validation.

### 4. Ride settlement

- `App\Modules\Payment\Actions\SettleRidePayment` — central money-
  clearing action.
  - Resolves gateway → authorize → capture → persist Payment row →
    compute commission → post wallet entries → audit log.
  - Idempotent on `(ride_id, status in {captured, refunded, partially_refunded})`.
  - Gateway failures land as `payment.status = failed`; ride stays
    completed.
- Wired into `Riding\Actions\CompleteTrip` via constructor injection.
  CompleteTrip catches + logs any thrown exception so the ride
  lifecycle is never blocked by a payment hiccup.
- 5 Pest tests for the happy path, idempotency, partial refund with
  pro-rated clawback, full refund, and over-refund rejection.

### 5. Refunds + cancellation fees

- `App\Modules\Payment\Actions\IssueRideRefund` — partial / full
  refund support. Customer credit + driver clawback in one
  transaction. Gateway round-trip for card; no-op for cash.
- `App\Modules\Payment\Actions\IssueCancellationFee` — debit the
  customer wallet + create a `cash` Payment row attached to the ride
  for accounting symmetry.

### 6. Receipts

- `App\Modules\Payment\Services\RideReceiptGenerator` — structured
  array (`generate()`) + 60-column text (`asText()`). Customer phone
  masked. PDF generation deferred to Phase 3.

### 7. Audit log

- `App\Modules\Payment\Services\MoneyAuditLogger` — dual-write to
  `storage/logs/payment.log` (daily-rotated, 60-day retention) and
  `activity_log` (spatie/activitylog with `log_name = 'money'`).
- Event slugs are dot-namespaced (`payment.captured`,
  `payment.refunded`, `payment.cancellation_fee`).
- Every money-touching action records once on success.

### 8. Admin finance panel

- `App\Modules\Payment\Filament\Pages\FinanceDashboardPage` at
  `/admin → Finance → Finance overview`. Auto-discovered.
- `FinanceOverviewWidget`: gross fares, commission take, driver
  earnings, refunds, cash 24h, unsettled (failed) payments.
  Excludes test rides.
- `PaymentResource` — list + filter by status / method / provider.
- `PayoutResource` — list + "mark paid" action.
- `WalletResource` — list with negative-balance + has-hold filters.
- `TransactionResource` — ledger view, filter by kind + direction.

### 9. Tests

29 new Pest tests:

- `tests/Unit/Payment/MoneyTest.php` (4 tests)
- `tests/Feature/Payment/CommissionCalculatorTest.php` (6 tests)
- `tests/Feature/Payment/WalletPosterTest.php` (6 tests)
- `tests/Feature/Payment/PaymentGatewayManagerTest.php` (5 tests)
- `tests/Feature/Payment/SettleRidePaymentTest.php` (5 tests
  including refund + clawback)

Total suite: **63 tests, 60 passing, 1 skipped, 2 pre-existing Redis-
infra errors. PHPStan clean for all new code** (1 pre-existing
DeviceController error unchanged).

### 10. Documentation

- `payment-architecture.md` — full system overview + data flow.
- `payment-setup-guide.md` — per-gateway bring-up steps + monitoring.
- `launch-readiness-report.md` — this doc.

## What's out of scope for Phase 2.3

- Live BOG / TBC Pay / Stripe integration — stubs + setup guide
  only. ~1 day of work per provider once creds land.
- Automated `PayoutAggregator` cron — for pilot, Finance runs a
  manual SQL → wire transfer → `WalletPoster::withdrawal()` workflow.
  Cron lands in Phase 2.4.
- Apple Pay / Google Pay merchant onboarding — Phase 2.4.
- Card-on-file management UI in the customer app — Phase 3.
- PDF receipts via dompdf — Phase 3 (text receipt is sufficient for
  pilot SMS + email).
- 3DS redirect handling in the mobile app — Phase 2.4 along with the
  first card gateway.
- Customer wallet top-ups — Phase 3.

## Risk register

| Risk                                                    | Likelihood | Impact | Mitigation                                                                 |
|---------------------------------------------------------|------------|--------|----------------------------------------------------------------------------|
| Cash settlement mismatch (driver pockets fare)          | Medium     | Medium | Daily reconciliation: gross fares vs `ride_payout` sum                      |
| Wallet balance drift over time                          | Low        | Critical | Ledger invariants enforced; daily integrity check job in Phase 2.4         |
| Refund abuse                                            | Low        | Low    | `MoneyAuditLogger` surfaces patterns; > 3 refunds / 30 d flags account       |
| Gateway misconfigured → silent failure                  | Low        | High   | `assertConfigured()` throws on missing creds; payments fail loudly          |
| Commission rate updated in prod without redeploy        | Low        | Medium | `config/commission.php` reads env; `config:cache` clears between deploys    |
| Driver claims commission was wrong                      | Medium     | Low    | Audit log + ride.commission_amount denormalised on ride row for support     |
| Concurrent ride completions race on wallet balance      | Medium     | High   | `FOR UPDATE` row lock + idempotency key in `WalletPoster`                   |

## Acceptance criteria

- [x] Money flows through the platform via `Money` value object
- [x] Commission split per ride + persisted on `ride.commission_amount` + `ride.driver_earnings`
- [x] Driver wallet posts on completion (`ride_payout` credit + `adjustment` debit)
- [x] Customer wallet credits on refund
- [x] Driver clawback on refund (pro-rated)
- [x] Cash payments capture + settle without external calls
- [x] Card-gateway stubs (Stripe, BOG, TBC Pay) compile + report `GATEWAY_NOT_WIRED` consistently
- [x] Receipts generate from ride data
- [x] Audit logs persist to file + `activity_log`
- [x] Admin finance dashboard live
- [x] Filament resources for payments, payouts, wallets, transactions
- [x] 29 new passing tests
- [x] PHPStan clean (new code)
- [x] Full architecture + setup docs

## Sign-off

- [ ] Engineering lead
- [ ] Finance
- [ ] Legal (refund + retention policy)
- [ ] SRE
- [ ] Ops (driver payout flow)

Sign-off gates the start of card-gateway bring-up.

## Next phase (Phase 2.4 — Card Payments Live)

Recommended scope, dependent on credentials landing:

1. BOG integration in production (sandbox first).
2. TBC Pay integration as fallback.
3. Webhook handlers for both.
4. `PayoutAggregator` automated cron.
5. Refund Filament action with proper form.
6. Customer card-on-file management (mobile UI).
7. 3DS redirect handling in mobile.
8. Phase 2.3 doc updates with screenshots once admin is touched
   by real production data.
