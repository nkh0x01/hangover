# Daily Monitoring Checklist

> Phase 2.2 deliverable. Pilot daily ops routine. ~10 minutes start,
> ~5 minutes end. Run by the day-shift Ops on-call. Skipped
> checkboxes get explained on the daily retro thread.

## 06:45 — Pre-flight (10 min)

Before the first rider can book.

### Platform health

- [ ] **API healthcheck** green: `curl -s https://api.hangover.app/api/v1/health` returns `200`.
- [ ] **Reverb healthcheck** green: WS endpoint responds to `ping`.
- [ ] **Horizon** running. `php artisan horizon:status` = `running`.
- [ ] **MySQL** primary CPU < 60%; replica lag < 5 s.
- [ ] **Redis** memory < 70% of plan.
- [ ] **Disk free** > 20% on every app server.
- [ ] **No P0 Sentry events** in the last 1 h.

### Data integrity

- [ ] Yesterday's rides backed up to long-term storage.
- [ ] Payout cron job ran (Mondays only).
- [ ] No orphaned `RideOffer` rows (`response = pending`, `expires_at < NOW() - INTERVAL 5 MINUTE`).

### Pilot config

- [ ] `PILOT_ENABLED=true` in `.env` confirmed.
- [ ] `PILOT_COHORT` matches the current week's label.
- [ ] `PILOT_TEST_PHONES` includes today's testers; nothing leaked
  from yesterday's batch.
- [ ] `PILOT_MIN_DRIVERS` threshold is correct (3 by default).

### Supply check

- [ ] At least 5 drivers expected online for the 07:00 window.
- [ ] Confirm with each via SMS group; one nod each is enough.
- [ ] Spare driver (on-call) available for emergencies.

### Comms

- [ ] Driver hotline answered (test ring).
- [ ] Support inbox: any overnight tickets handled.
- [ ] Slack #pilot-ops channel quiet, no unresolved threads.

### Open issues from yesterday

- [ ] Read yesterday's retro.
- [ ] Any blockers carried over? Confirm owner + status.

If any of the items above failed, **delay the 07:00 open** and post in
#pilot-ops with the reason. Don't push through a known unhealthy
state.

## 07:00 — Open service

- [ ] Tweet / post the "open" message.
- [ ] First scheduled driver goes online; confirm in the live monitor.
- [ ] First test ride executed within 10 min — Ops bookings only.

## 09:00 — Morning checkpoint (3 min)

- [ ] Pilot dashboard: `Rides today` ≥ 5 (in a healthy week 1).
- [ ] Cancel rate green.
- [ ] No-drivers rate green.
- [ ] No new P0/P1 incidents.

## 12:00 — Midday standup (5 min)

Async post in #pilot-ops:

```
Rides today: <n>   Completed: <n>   Cancel rate: <n>%
Online drivers: <n>   No-drivers today: <n>
Sentry top issue: <title or "—">
Open incidents: <list of P1+>
Notes:
```

## 15:00 — Afternoon checkpoint (3 min)

Same as 09:00.

- [ ] Driver shift handover at 14:00–15:00 went cleanly?
- [ ] Any driver below 2 rides for the day — call to check.

## 18:00 — Evening checkpoint (3 min)

Same as 09:00.

- [ ] Surge multiplier within cap (max 1.5).
- [ ] Payment failures (cash settle skipped) < 1% of rides.

## 22:00 — Pre-close (5 min)

- [ ] Close service window approaches; comms 30 min before.
- [ ] Tail rides currently in-flight — let them complete naturally.
- [ ] No new ride requests accepted after 23:00 (service-hours
  config).

## 23:15 — Close + retro (10 min)

- [ ] All in-flight rides terminated cleanly.
- [ ] Drivers all offline.
- [ ] Daily retro added to `pilot-launch-report.md` for today's
  date.
- [ ] Filament admin → export today's data (CSV download from the
  rides page) → upload to long-term storage.
- [ ] Tomorrow's preview: any known issues, schedule changes, weather
  warnings.

## Daily retro template

Paste into the launch report:

```
### YYYY-MM-DD  (Day N of pilot)

- Rides today: <n> total, <n> completed, <n> cancelled, <n> no-drivers.
- Cancel rate: <%>.
- No-drivers rate: <%>.
- Avg pickup time: <mm:ss>.
- Online drivers peak: <n> at <hh:mm>.

#### Incidents
- <severity> — <one-line summary>. <link to ticket>. Resolved / Open.

#### Anomalies (without an incident)
- ...

#### Follow-ups for tomorrow
- [ ] ...

#### What went well
- ...

#### What didn't
- ...
```

## Weekly check (Mondays, 10 min)

After the Monday morning retro:

- [ ] Payouts ran successfully (`/admin → Payouts → Last run`).
- [ ] Driver activity report distributed to drivers via SMS.
- [ ] Customer week-1-vs-week-N retention chart updated.
- [ ] Sentry weekly digest reviewed.
- [ ] Top 3 themes from the week's incidents identified.

## Escalation matrix

If you see any of these without a clear owner, escalate within 60
seconds:

- Cancel rate > 30% for any 1-hour window.
- 5xx > 10 in a 1-min window.
- Online drivers = 0 during open hours.
- Any safety event (P0).
- Any unhandled exception in `Riding\*` namespace.
- Payment failure rate > 5%.

Escalation order:
1. On-call Ops lead (call first).
2. On-call SRE.
3. CTO (if no ack from above within 5 min).
