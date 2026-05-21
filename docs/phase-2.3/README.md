# Phase 2.3 — Payments, Commission, and Driver Wallets

The financial foundation: every completed ride now produces a payment
record, a commission split, and ledger entries against the driver's
wallet. Card-gateway implementations for BOG, TBC Pay, and Stripe
ship as structural stubs ready for credential bring-up.

## Documents

| File                                              | Audience          |
|---------------------------------------------------|-------------------|
| [`launch-readiness-report.md`](launch-readiness-report.md) | Steering / sign-off |
| [`payment-architecture.md`](payment-architecture.md)     | Engineering        |
| [`payment-setup-guide.md`](payment-setup-guide.md)       | SRE + Finance      |

## Code changes this phase

### Backend
- `config/commission.php`, `config/payment.php` — new env-driven config.
- `App\Modules\Pricing\Services\CommissionCalculator`
- `App\Modules\Wallet\Services\WalletPoster` (atomic ledger writes,
  idempotency, holds)
- `App\Modules\Payment\Contracts\PaymentGateway` + `GatewayResult`
  (existed; unchanged)
- `App\Modules\Payment\Gateways\{Cash, Null, Wallet, Stripe, Bog, TbcPay}PaymentGateway`
- `App\Modules\Payment\Services\PaymentGatewayManager`
- `App\Modules\Payment\Services\RideReceiptGenerator`
- `App\Modules\Payment\Services\MoneyAuditLogger`
- `App\Modules\Payment\Actions\SettleRidePayment`
- `App\Modules\Payment\Actions\IssueRideRefund`
- `App\Modules\Payment\Actions\IssueCancellationFee`
- `App\Modules\Payment\Models\{Payment, Refund, Payout}` (Payment +
  Refund touched; Payout new)
- `App\Modules\Riding\Models\Ride` — added `city()` relation, used by
  the commission calculator
- `App\Modules\Riding\Actions\CompleteTrip` — calls
  `SettleRidePayment` after the state transition; catches + logs any
  payment error
- Filament:
  - `Payment\Filament\Pages\FinanceDashboardPage`
  - `Payment\Filament\Widgets\FinanceOverviewWidget`
  - `Payment\Filament\Resources\PaymentResource`
  - `Payment\Filament\Resources\PayoutResource`
  - `Wallet\Filament\Resources\WalletResource`
  - `Wallet\Filament\Resources\TransactionResource`
  - `resources/views/filament/pages/finance-dashboard.blade.php`

### Tests
- `tests/Unit/Payment/MoneyTest.php` (4)
- `tests/Feature/Payment/CommissionCalculatorTest.php` (6)
- `tests/Feature/Payment/WalletPosterTest.php` (6)
- `tests/Feature/Payment/PaymentGatewayManagerTest.php` (5)
- `tests/Feature/Payment/SettleRidePaymentTest.php` (5)

Total: 29 new tests, all passing.

## State of the test suite

```
Tests: 63 total, 60 passed, 1 skipped, 2 errored (Redis, pre-existing).
PHPStan: 1 pre-existing error in DeviceController; new code clean.
```

## What's gated on this phase

- Bringing up real card gateways (per
  [`payment-setup-guide.md`](payment-setup-guide.md)).
- Switching the customer app to surface "Card" as a payment option
  (mobile follow-up).
- Automated weekly driver payouts (`PayoutAggregator` cron in
  Phase 2.4).
