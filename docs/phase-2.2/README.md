# Phase 2.2 — Pilot Launch Operations

Operational scaffolding that turns the launch-ready platform from
Phase 2.1 into a controlled Tbilisi / Batumi pilot. Mostly process,
not code.

## Documents

| File                                              | Audience                       |
|---------------------------------------------------|---------------------------------|
| [`launch-readiness-report.md`](launch-readiness-report.md) | Steering / sign-off    |
| [`pilot-launch-checklist.md`](pilot-launch-checklist.md)   | Cross-team go/no-go    |
| [`launch-blocker-criteria.md`](launch-blocker-criteria.md) | Halt conditions       |
| [`pilot-launch-report.md`](pilot-launch-report.md)         | Running log T+0 → T+14|
| [`driver-onboarding-guide.md`](driver-onboarding-guide.md) | Ops staff             |
| [`driver-approval-checklist.md`](driver-approval-checklist.md) | Printable form    |
| [`driver-training-guide.md`](driver-training-guide.md)     | Trainer script        |
| [`ride-safety-checklist.md`](ride-safety-checklist.md)     | Drivers (training)    |
| [`support-workflow.md`](support-workflow.md)               | Support team          |
| [`customer-support-faq.md`](customer-support-faq.md)       | Help center           |
| [`cancellation-refund-rules.md`](cancellation-refund-rules.md) | Legal + Support  |
| [`admin-monitoring-guide.md`](admin-monitoring-guide.md)   | Ops / SRE on-call     |
| [`daily-monitoring-checklist.md`](daily-monitoring-checklist.md) | Day-shift Ops    |

## Code changes this phase

### Backend
- `database/migrations/0001_01_01_000025_add_pilot_flags_to_rides.php`
- `app/Modules/Riding/Models/Ride.php` — fillable + boolean cast for
  the new flags.
- `app/Modules/Riding/Actions/CreateRideRequest.php` — auto-tag
  rides created by `PILOT_TEST_PHONES`.
- `app/Modules/Riding/Filament/Resources/RideResource.php` — test
  ride column + filter + cohort column + filter.
- `app/Modules/Riding/Filament/Pages/PilotDashboardPage.php` — new
  ops dashboard.
- `app/Modules/Riding/Filament/Widgets/PilotMetricsWidget.php` —
  six-up KPIs.
- `resources/views/filament/pages/pilot-dashboard.blade.php` —
  blade for the dashboard.
- `config/pilot.php` — env-driven config.
- `tests/Feature/Riding/PilotTestRideTaggingTest.php` — 4 new tests.

### Mobile + Docs
No mobile changes. The phase is operational; the apps from Phase 2.1
stay as the runtime.

## State of the test suite

```
Tests: 37 total, 34 passed, 1 skipped, 2 errored (Redis-conn, pre-existing).
PHPStan: 1 pre-existing error in DeviceController; new code clean.
```

## Gating items before T-0

See [`pilot-launch-checklist.md`](pilot-launch-checklist.md) and
[`launch-readiness-report.md`](launch-readiness-report.md) §
"Real-readiness gap" for the field-team to-do list.
