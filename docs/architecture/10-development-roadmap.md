# 10 — Phased Development Roadmap

Each phase has a single **theme**, an exit criterion, and the **minimum** items that must ship for the phase to be considered done. Anything beyond the minimum is "nice to have" and can slip to the next phase.

Estimates assume a team of: 2 backend engineers, 1 Flutter engineer, 0.5 designer, 0.25 SRE, 0.5 PM/QA. Adjust proportionally for different team shapes.

---

## Phase 0 — Foundation (2 weeks)

**Theme:** Get the empty house wired up before any furniture goes in.

**Deliverables:**

Backend
- New Laravel 11 app under `backend/`, PHP 8.3 baseline.
- Pint, Larastan level 8, Pest configured. CI runs on every PR.
- Module skeleton: directories + empty providers for every module from [03](03-laravel-backend-structure.md).
- Sanctum, Spatie permissions, Spatie activitylog, Horizon, Reverb, Filament installed.
- `config/modules.php` boots all module providers.
- Health endpoint + version endpoint.
- `App\Exceptions\Handler` JSON envelope.

Infrastructure
- Docker Compose local up; Make targets work.
- Terraform `dev` env stood up: VPC, RDS, ElastiCache, ECS cluster (no services yet), S3 buckets.
- GitHub Actions: backend lint/test, mobile lint/test, Terraform plan.
- Sentry projects, Firebase projects, Stripe test mode set up. Secrets in Secrets Manager.

Mobile
- Two Flutter apps scaffolded; three flavors each.
- Melos monorepo; shared packages skeletons.
- Riverpod, go_router, freezed, dio wired.
- Sentry, Firebase Analytics SDKs initialized.
- CI builds debug Android + iOS for both apps.

Docs
- This `docs/architecture/` tree treated as the contract.
- `CONTRIBUTING.md`, `CODEOWNERS`, PR template.

**Exit criterion:** A "Hello from Laravel" endpoint round-trips through ECS in `dev`; both Flutter apps install on a device from CI builds and show their own splash screens.

---

## Phase 1 — Identity & onboarding (3 weeks)

**Theme:** A user can register and log in. Drivers can submit documents.

Backend
- `Identity` module: phone OTP, Google, Apple, refresh, logout, device management.
- SMS provider abstraction with Twilio + a no-op driver for dev.
- `Driver` module: driver profile, vehicles CRUD, documents upload (S3 SSE-KMS), status lifecycle, approval API endpoints (no UI yet).
- Filament admin: login + 2FA, role/user resources, driver approval queue page, document review.
- Rate limiting + idempotency middleware live for these endpoints.

Mobile (customer + driver)
- Auth flows fully working: phone OTP, Google, Apple, refresh.
- Profile screen, edit name, locale, avatar.
- Driver onboarding: capture documents, status screen (pending/in_review/approved/rejected).

**Exit criterion:** A real phone can sign up; a real driver can upload docs and be approved from the admin panel; both apps reflect the new state via reconciled login.

---

## Phase 2 — Geo, pricing, and a static ride request (3 weeks)

**Theme:** The customer can request a ride; the system locks a fare estimate. No live driver yet.

Backend
- `Geo` module: cities + zones, place autocomplete proxy, reverse geocode, nearby drivers query against Redis (still empty at this stage).
- `Pricing` module: fare rules CRUD + Filament resources, fare estimate endpoint, surge model in place but disabled.
- `Riding` module: ride state machine + transitions, `POST /customer/rides` creating a `requested` ride, persisting fare lock.
- Map provider abstraction with Google Maps implementation.
- Active-driver and active-customer locks enforced at DB level.

Mobile
- Customer: map home screen, pickup/dropoff selection, fare estimate, request flow stops at "Looking for driver…" with no progress (acceptable for this phase).
- Driver: stub home screen with online/offline toggle that posts to API (no dispatching yet).

**Exit criterion:** A real customer can pick a route in Tbilisi, see a real distance/duration/fare, request a ride that lands in the DB with all the right locks.

---

## Phase 3 — Realtime + dispatch (4 weeks) — **MVP milestone**

**Theme:** End-to-end ride happens.

Backend
- `Geo` live location ingestion endpoint + Redis hot index.
- `DispatchService` with the offer loop ([07 §dispatch](07-realtime-ride-lifecycle.md#dispatch-algorithm)).
- Reverb broker live; private + presence channels per [07 §channels](07-realtime-ride-lifecycle.md#channels).
- Ride status transitions for accept, arriving, arrived, start, complete, cancel.
- `Payment` module: Stripe integration (preauth on request, capture on complete), cash payment method.
- `Wallet` + `Rating` modules: payout to driver wallet on completion, customer-side rating, driver-side rating.
- Filament: active rides board, live ride map, live driver map.
- Trip route decimation worker, `ride_route_points` populated.

Mobile
- Customer: full ride lifecycle UI — searching, driver info card, live driver marker, ETA updates, chat, rate driver, view trip summary.
- Driver: incoming offer screen (push + PushKit on iOS), accept/reject, on-the-way → arrived → start → complete, chat, rate customer, view earnings.
- WS reconnect + reconciliation flow.

**Exit criterion:** Two real devices in Tbilisi complete a paid ride end-to-end against staging; admin live-map shows the trip moving.

---

## Phase 4 — Production hardening (3 weeks)

**Theme:** Make the MVP good enough to actually launch.

Backend
- Observability: OpenTelemetry traces on every request and job; dashboards from [09 §observability](09-deployment-architecture.md#observability) live; SLO alerts wired.
- Stripe webhooks fully handled; idempotent against replays.
- `Communication` module: notification preferences, push templates, SMS templates, in-app inbox.
- `Support` module: ticket flow, SOS endpoint and admin board.
- `Promotion` module: promo codes, redemption, eligibility validation, admin CRUD.
- `Fraud` module skeleton: flag types, rule placeholders, admin queue.
- Backups verified by a quarterly restore drill (do the first one this phase).
- DR runbook drafted.

Mobile
- Customer: promo codes UI, wallet UI (read-only top-up + balance display), support ticket flow, SOS button.
- Driver: SOS button, earnings dashboards (day/week/month), payout request flow (admin-approved), notification settings.
- Offline-safe behavior for non-critical writes; queued outbox.

Infrastructure
- Prod environment built; Cloudflare in front; WAF active.
- Mobile cert pinning live.
- Load tests against staging: 500 concurrent active rides + 2 k online drivers sustained for 1 h.

**Exit criterion:** Closed beta with 50 customers + 20 drivers in Tbilisi for two weeks with no SEV-1 incidents.

---

## Phase 5 — Launch v1.0 (2 weeks)

**Theme:** Public launch in Tbilisi.

- App store launches (both stores, both apps).
- Marketing site live (separate project, not in this repo).
- Customer support team trained; runbooks completed.
- Driver acquisition campaign live; first 500 approved drivers onboarded.
- 24/7 on-call rotation begins.

**Exit criterion:** 500 rides/day for 7 consecutive days; p95 dispatch latency < 800 ms; crash-free user rate > 99.5% on both apps.

---

## Phase 6+ — Post-launch (rolling)

Backlog, prioritized:

1. **Scheduled rides** — DB columns reserved; build the scheduling worker + UI.
2. **Multi-stop rides** — schema additions in `rides`, UI for adding stops.
3. **Corporate accounts (B2B)** — company entity, employee invitations, central billing, admin portal.
4. **Heatmaps for drivers** — analytics pipeline (Glue/Athena) feeding a tile service the driver app overlays.
5. **Local Georgian payment gateway** (BoG / TBC Pay) — implement under existing PaymentGateway abstraction.
6. **Split payments** — multiple riders splitting the fare.
7. **Loyalty program** — points engine on top of `transactions`.
8. **Vehicle telematics integration** — for owned fleet; auto-toggle online based on motion + battery.
9. **City expansion** — Batumi, then international: language packs, currency, regulatory compliance per market.
10. **Dynamic surge** — replace the manual surge multiplier with the algorithm.

Each Phase 6+ item is its own mini-roadmap with the same shape (theme, deliverables, exit criterion).

---

## Cross-cutting workstreams (always-on after Phase 0)

| Stream | Owner | Cadence |
|---|---|---|
| Security & compliance (incl. GDPR) | SRE + backend lead | Quarterly review |
| Performance budget enforcement | Backend lead | Per PR (CI gates) |
| Mobile crash triage | Mobile lead | Daily standup |
| Cost review | SRE + PM | Monthly |
| Support insight loop (ticket trends → product backlog) | PM | Weekly |
| Driver supply ops | Operations | Daily |

---

## Risks & open questions

| Risk | Mitigation |
|---|---|
| SMS deliverability in Georgia | Two providers behind abstraction; fallback path; cost tracked in `sms_log` |
| Google Maps cost at scale | Mapbox/OSRM behind same abstraction; we can swap regionally |
| Apple App Review for Sign in with Apple gating | Implement Apple Sign-In from day one; required per Apple policy |
| Battery drain on Android driver app | Adaptive cadence + foreground service tuning + field testing in Phase 3 |
| Driver fraud (location spoofing) | Speed plausibility + accuracy filters + ride-trace sanity checks; manual review queue |
| Currency / tax compliance (Georgia VAT) | Receipts include VAT line item; finance review in Phase 4 |
| Apple PushKit misuse policy | Use only for incoming-offer flow; documented; reviewed before submission |

---

## Definition of Done (any task, any phase)

- Code merged via PR with green CI.
- Migrations reversible or with documented rollback.
- Tests cover the happy path + at least one failure path.
- Observability: a log line or metric exists for any new state change.
- Localization keys added for ka/en/ru.
- Docs updated: API spec regen, ADR added for non-trivial decisions.
- No new `// TODO` or `dump()` left in.
