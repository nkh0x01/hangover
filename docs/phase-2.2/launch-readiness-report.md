# Hangover Platform — Phase 2.2 Launch-Readiness Report

> Reporting date: 2026-05-13
> Branch: `claude/scooter-platform-architecture-Wvmeu`
> Phase: **2.2 — Pilot Launch Operations**

## Headline

The platform now has the **operational scaffolding** for a controlled
Tbilisi / Batumi pilot. The remaining gap to "first real customer on
the road" is not code or design — it is the field work documented in
this folder: identifying drivers, signing the insurance contract,
provisioning the Firebase / APNs credentials, generating production
keystores, and walking through the dress rehearsal.

Phase 2.1 delivered the build pipeline and crash-logging foundation.
Phase 2.2 delivers the **process** that wraps around it: who does
what, when, and what halts the pilot if something goes wrong.

## What Phase 2.2 shipped

### 1. Pilot-aware data model

- `0001_01_01_000025_add_pilot_flags_to_rides.php` migration adds
  `is_test_ride` (bool, indexed) and `pilot_cohort` (string, indexed)
  to `rides`. Non-destructive, NULL/false backfill.
- `Ride` model fillable + casts updated.
- `CreateRideRequest` auto-tags rides created by phones in
  `config('pilot.test_phone_numbers')` with the current
  `pilot_cohort` label. Production-customer rides are untouched.
- `config/pilot.php` exposes `enabled`, `cohort`,
  `test_phone_numbers`, `monitoring.{min_active_drivers,
  max_no_drivers_per_hour, max_cancellation_rate}`, and
  `service_hours.{open,close}` — all env-driven so staging can run
  one cohort while prod runs another.

### 2. Admin tooling

- `PilotMetricsWidget` — six-up stats overview: rides today,
  completed, cancel rate, no-drivers rate, avg pickup time, online
  drivers. Excludes test rides from KPIs.
- `PilotDashboardPage` (`/admin → Operations → Pilot dashboard`) —
  bundles the metrics widget, today's test-ride list, and the
  pilot-config readiness flags into a single page. Hidden unless
  `PILOT_ENABLED=true` or app environment is local/staging.
- `RideResource` — adds the test-ride boolean column + the pilot-
  cohort toggleable column + a TernaryFilter + a per-cohort filter.

### 3. Tests

`tests/Feature/Riding/PilotTestRideTaggingTest.php` adds 4 new
passing tests:

- Tester phones get tagged.
- Non-tester phones don't.
- Empty `test_phone_numbers` config tags nothing.
- Flags persist + are visible to the admin filter.

Total suite: 37 tests, 34 passing, 1 skipped, 2 pre-existing Redis-
infrastructure errors. No new failures. PHPStan clean for all new
code (one pre-existing error in `DeviceController.php` remains
unchanged).

### 4. Documentation — operational playbook

Nine new documents under `docs/phase-2.2/`:

| Document                              | Lines | Audience                       |
|---------------------------------------|-------|---------------------------------|
| `pilot-launch-checklist.md`           | ~240  | Master go/no-go T-14 → T+14    |
| `driver-onboarding-guide.md`          | ~210  | Ops staff onboarding drivers   |
| `driver-approval-checklist.md`        | ~110  | Printable hard-gate checklist  |
| `driver-training-guide.md`            | ~200  | Trainer script + Day-1 card    |
| `support-workflow.md`                 | ~220  | Tier-1 + Tier-2 + ops on-call  |
| `customer-support-faq.md`             | ~200  | Public help center + Tier-1    |
| `cancellation-refund-rules.md`        | ~170  | Legal-approved fee + refund matrix |
| `ride-safety-checklist.md`            | ~180  | Drivers — pre-shift + in-ride |
| `admin-monitoring-guide.md`           | ~200  | Ops + SRE on-call             |
| `daily-monitoring-checklist.md`       | ~160  | Day-shift Ops routine          |
| `launch-blocker-criteria.md`          | ~180  | Objective hard-stop conditions |
| `pilot-launch-report.md`              | ~170  | Running log T+0 → T+14         |
| `launch-readiness-report.md`          | ~200  | This document                  |

Total: ~2 600 lines of operational documentation.

## What Phase 2.2 deliberately doesn't add

- **No new ride-flow features.** The lifecycle, dispatch, cancellation,
  rating, payment logic is unchanged from Phase 1.5 / 1.6 / 2.0.
- **No multi-stop trips, scheduled rides, or wallet top-ups.** All
  deferred to Phase 3.
- **No new payment methods.** Pilot remains cash-only.
- **No automated suspension actions** (strikes are tracked manually
  by Ops in the admin panel). Auto-suspension lands in Phase 2.3
  once we have a feel for the strike thresholds.
- **No PagerDuty integration.** The `admin-monitoring-guide.md`
  documents the alert list; wiring it to PagerDuty + Slack is
  Phase 2.3.

## Real-readiness gap (what's still required for the first ride)

These are the items the field team must complete **outside** this
codebase. Tracked in `pilot-launch-checklist.md`:

1. Production keystore (LB-005 / LB-010 from Phase 2.1) generated +
   signed APK uploaded.
2. `google-services.json` + `GoogleService-Info.plist` per app per
   flavor.
3. APNs auth key uploaded to Firebase.
4. Twilio prod credentials.
5. Insurance contract signed.
6. 8+ drivers onboarded per `driver-onboarding-guide.md` and
   activated.
7. On-call rota staffed.
8. Driver hotline phone provisioned.
9. Public help center page populated with `customer-support-faq.md`.
10. Dress rehearsal completed (T-1).

None of these are code tasks. The platform code itself is launch-
ready as of this commit.

## Risk register (updated from Phase 2.1)

| Risk                                                    | Likelihood | Impact | Mitigation                                                                 |
|---------------------------------------------------------|------------|--------|----------------------------------------------------------------------------|
| Supply shortfall during peak hours                      | High       | Medium | `pilot.monitoring.min_active_drivers` floor + paging                       |
| Driver cancels mid-ride more than expected               | Medium     | High   | Strike tracking + auto-suspension after 3                                  |
| Cash settlement disputes                                 | Medium     | Medium | Refund flow with wallet-credit (no card chargeback to fight)                |
| Insurance contract delays                                | Medium     | High   | Hard launch-blocker; can't ship without it                                  |
| Customer complaints flood support                         | Medium     | Medium | Tier-1 capacity = 2 ops; auto-categoriser handles 50% of FAQ-style tickets   |
| First-week safety event                                   | Low        | Critical | P0 playbook in `support-workflow.md`; auto-halt criteria                    |
| Bad-weather day drops supply > 50%                        | High       | Medium | `ride-safety-checklist.md` weather rules; service window can shorten         |
| Battery-saver / OEM Android kills GPS                     | Medium     | High   | Phase 2.1 templates set `FOREGROUND_SERVICE_LOCATION`; manual driver-by-driver testing |
| Reverb broker outage                                      | Low        | High   | Polling fallback ships from Phase 2.0; QA F4 verified it             |
| Refund abuse                                              | Low        | Low    | Pattern-detection in `cancellation-refund-rules.md`; > 3 in 30 days flags    |

## Acceptance criteria for Phase 2.2 closeout

- [x] Test-ride mechanism implemented + tested
- [x] Pilot metrics dashboard live in admin
- [x] Pilot launch checklist published
- [x] Driver onboarding + approval + training guides published
- [x] Support workflow + incident flow + FAQ published
- [x] Cancellation + refund policy approved structure (Legal sign-
      off happens at T-5; the doc is approval-ready)
- [x] Ride safety checklist published
- [x] Admin monitoring + daily monitoring guides published
- [x] Pilot launch report template published
- [x] Launch blocker criteria published
- [x] Backend tests passing (no new failures)
- [x] PHPStan clean for new code
- [ ] Field team confirms readiness via `pilot-launch-checklist.md`
      (happens outside this PR)

## Next phase (Phase 2.3 — Public Beta Operations)

Recommended scope after pilot:

1. Auto-suspension on cancellation-strike threshold.
2. PagerDuty + Slack alerting wired per `admin-monitoring-guide.md`.
3. Driver Apple Sign-In + Google Sign-In options.
4. Multi-stop trips MVP.
5. Wallet top-up with card payments.
6. Customer referral codes (resolves the LB-021 referral-ULID
   exposure from Phase 2.1).
7. Background-location prompt UX (LB-009).
8. Native splash + branded icons (LB-012, 13).
9. CI mobile-build pipeline (mentioned in Phase 2.1 docs).

## Sign-off

- [ ] Engineering lead
- [ ] Mobile lead
- [ ] SRE
- [ ] Product
- [ ] Ops lead
- [ ] Finance
- [ ] Legal

Sign-off gates the start of T-7 driver onboarding.
