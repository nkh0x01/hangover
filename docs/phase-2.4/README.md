# Phase 2.4 — Driver Verification, Safety, and Trust

Backend layer + admin tooling for making the platform safe and
trustworthy before public launch. Mobile UI for the customer/driver
safety screens lands in Phase 2.5.

## Documents

| File                                              | Audience          |
|---------------------------------------------------|-------------------|
| [`launch-readiness-report.md`](launch-readiness-report.md) | Steering / sign-off |
| [`verification-workflow.md`](verification-workflow.md) | Engineering, Ops  |
| [`safety-features.md`](safety-features.md)        | Cross-team         |
| [`admin-safety-tools.md`](admin-safety-tools.md)  | Ops on-call        |
| [`testing-checklist.md`](testing-checklist.md)    | QA                 |
| [`known-limitations.md`](known-limitations.md)    | Steering           |

## Code changes this phase

### Backend
- Migration `0001_01_01_000026_add_verification_and_suspension_columns.php`.
- `App\Modules\Driver\Actions\{SubmitDriverDocument, ReviewDriverDocument, VerifyVehicle}`.
- `App\Modules\Driver\Services\DriverVerificationPresenter`.
- `App\Modules\Driver\Http\Controllers\DocumentController` +
  routes.
- `App\Modules\Support\Models\{SosEvent, FraudFlag}`.
- `App\Modules\Support\Actions\{RaiseSosEvent, SubmitComplaint, RaiseFraudFlag, SuspendUser}`.
- `App\Modules\Support\Services\{FraudDetector, IncidentTimelineService}`.
- `App\Modules\Support\Http\Controllers\{SosController, ComplaintController}` + routes.
- `App\Modules\Support\Filament\Pages\SafetyDashboardPage` +
  `Widgets\SafetyOverviewWidget`.
- `App\Modules\Support\Filament\Resources\{SosEventResource, FraudFlagResource}`.
- `App\Http\Middleware\EnsureNotBlocked` (alias `not_blocked`).
- `config/safety.php` — env-driven thresholds.
- Driver + Vehicle + User models gained the new fillable + casts.
- `Driver\Providers\DriverServiceProvider`, `Support\Providers\SupportServiceProvider`
  wire the singletons + routes.
- `Identity\Models\User` gains `fraudFlags()` relation and `isBlocked()` predicate.

### Tests
- `tests/Feature/Driver/DriverVerificationTest.php` (7)
- `tests/Feature/Support/SafetyActionsTest.php` (10)
- `tests/Feature/Support/FraudDetectorTest.php` (6)
- `tests/Feature/Support/BlockedUserMiddlewareTest.php` (3)

Total: 26 new tests.

## State of the test suite

```
Tests: 89 total, 86 passed, 1 skipped, 2 errored (Redis, pre-existing).
PHPStan: 1 pre-existing error in DeviceController; new code clean.
```

## Not in this phase

- Flutter UI for the safety screens (Phase 2.5).
- Push notifications on document approval / rejection (Phase 2.5).
- PagerDuty / Slack page-on-SOS listener (Phase 2.5).
- Auto-suspend on `severity = block` fraud flag (Phase 2.5).
- Sanctum token revocation on user suspension (Phase 2.5).
- Real fraud-detection wiring into `IngestLocationHeartbeat` (Phase 2.5).
- Package / parcel delivery (out of scope for the platform).

Detailed list in [`known-limitations.md`](known-limitations.md).
