# Launch Blocker Criteria

> Phase 2.2 deliverable. The objective conditions under which the
> pilot must NOT launch (or must halt). No discretion, no
> negotiation in the room at T-0 — the criteria below decide.
>
> Approved by Eng + Ops + Legal before the first ride.

## Hard stop — pre-launch

Any single item below is a no-go. Postpone until cleared.

### Code / infra

1. **API healthcheck red** in prod for any of the 24 hours
   preceding T-0.
2. **Reverb broker** not online OR TLS expiring within 7 days.
3. **MySQL** primary failover not exercised within the last 30 days.
4. **Redis** memory utilisation > 80% before pilot starts.
5. **Sentry DSN not configured** on either backend or mobile.
6. **Crash report verification** never done — the test exception
   thrown in staging never reached Sentry.
7. **Mobile app crash rate > 0.5%** in staging QA on the device
   matrix.
8. **Critical Sentry issue open** in `Riding\Actions\*` or
   `Riding\Services\*`.

### Drivers

9. **Fewer than 8 fully-approved drivers** for Tbilisi, or fewer
   than 4 for Batumi if launching both.
10. **Any driver in `pending_approval`** but online for a real ride.
11. **Background check overdue** for any driver going online.

### Permissions / legal

12. **Customer data PII flow not reviewed** by Legal for the cohort.
13. **Insurance contract** not signed by the day of launch.
14. **Service hours posted publicly** but the
    `pilot.service_hours` config not actually enforced.

### Operations

15. **No on-call rota** for the first 7 days.
16. **Hotline number** untested (no test calls).
17. **Refund flow** never end-to-end tested with a real refund.
18. **PILOT_TEST_PHONES** empty when it should contain at least
    the ops + driver-tester numbers.
19. **Driver day-1 card** not printed.

## Hard stop — running pilot (auto-halt)

Each trigger pauses the pilot. The platform is taken into
maintenance mode (`php artisan down --secret=<token>`) and customers
+ drivers are messaged. Resumes only after the listed mitigation +
ops-lead sign-off.

### Safety

| Trigger                                                 | Halt scope        | Mitigation gate                                             |
|---------------------------------------------------------|--------------------|--------------------------------------------------------------|
| Confirmed accident with injury                          | The driver involved | Police report + Legal sign-off + driver re-training         |
| Confirmed serious assault on either party               | Full pilot         | Police + Legal + full incident debrief                        |
| Pattern (≥ 2 in 24h) of "unsafe driver" complaints      | Full pilot         | Cohort retraining + Ops review                                 |

### Platform

| Trigger                                                 | Halt scope                                | Mitigation gate                                            |
|---------------------------------------------------------|--------------------------------------------|-------------------------------------------------------------|
| API 5xx > 2% of requests over 5 min                     | Full pilot                                 | Root cause identified + hotfix deployed                     |
| Reverb broker outage > 2 min                            | Full pilot                                 | Broker restored + healthy 5 min                              |
| Dispatch loop stuck (any ride in `searching` > 3 min)   | Affected city                              | Root cause + DispatchService deployed                        |
| Payment dispute rate > 5% of completed rides            | Full pilot                                 | Finance review + Ops sign-off                                |
| Mobile crash rate > 1% over 30 min                      | Full pilot                                 | Patched build via TestFlight + Play internal track            |
| FCM delivery success rate < 90% over 30 min             | Full pilot                                 | FCM creds re-checked / Firebase project health verified      |

### Supply

| Trigger                                                 | Halt scope                                | Mitigation gate                                            |
|---------------------------------------------------------|--------------------------------------------|-------------------------------------------------------------|
| Online drivers = 0 during open hours > 2 min           | Affected city                              | Driver back online + diagnose what happened                  |
| Online drivers < pilot floor for 10 consecutive min     | Affected city                              | Recover supply + Ops post-mortem on cause                     |
| > 25% of online drivers go offline simultaneously       | Affected city                              | Root cause investigation; comms outage suspected             |

### Quality

| Trigger                                                 | Halt scope         | Mitigation gate                                            |
|---------------------------------------------------------|---------------------|-------------------------------------------------------------|
| Cancel rate > 30% over rolling 1 h                      | Full pilot          | Root cause + Ops sign-off                                     |
| No-drivers rate > 20% over rolling 1 h                   | Affected city       | Supply intervention + Ops sign-off                            |
| Avg pickup time > 12 min for 30 min                     | Affected city       | Supply check + reset                                          |

### Compliance

| Trigger                                                 | Halt scope          | Mitigation gate                                            |
|---------------------------------------------------------|----------------------|-------------------------------------------------------------|
| Data breach (PII exfiltration / suspected SQLi)         | Full pilot           | Legal + SRE + Eng head — full incident response             |
| Regulatory cease-and-desist                              | Full pilot           | Legal — comply, then reassess                                  |
| Severe DSAR backlog (> 5 unresolved > 14 days)         | None (not halting) but escalate | Legal + Ops                                              |

## Soft warnings (do not halt, but escalate)

When any of these fire, the on-call Ops lead is paged. They decide
whether to escalate to a hard-halt or accept-and-monitor.

- Cancel rate 20-30% over rolling 1 h.
- No-drivers rate 10-20% over rolling 1 h.
- A driver hits 2 strikes in 24 h.
- A customer hits 3 cancellations in 1 day.
- Sentry sees a new fingerprint with > 10 occurrences in 1 h.
- WS reconnection rate > 20% over 30 min (indicates broker flakiness).
- Memory pressure > 80% on any app server for > 10 min.

## Halt procedure (the runbook)

1. **Decide**: Ops lead OR SRE on-call can call a halt unilaterally
   based on the criteria. No committee needed.
2. **Maintenance mode**: `php artisan down --secret=<token>
   --render="resources/views/maintenance.blade.php"` on the API.
3. **Comms — customers**: SMS + in-app notification:
   "Hangover is temporarily unavailable while we investigate an
   issue. Active rides will complete normally."
4. **Comms — drivers**: SMS + in-app + hotline:
   "Hangover is paused. Stay online to complete your current ride;
   no new offers will reach you. We'll send an update soon."
5. **Investigate**: SRE + Eng war room. Sentry, logs, dashboards.
6. **Mitigate** per the mitigation gate.
7. **Verify**: smoke ride in staging that reproduces the trigger
   conditions; passes.
8. **Resume**: lift maintenance mode. SMS + in-app:
   "We're back. Thanks for your patience."
9. **Retro**: post-mortem document in `docs/phase-2.2/retros/`
   within 24 hours.

## Resume criteria

For a resume to be authorised:

- [ ] Root cause identified and documented.
- [ ] Mitigation deployed AND verified in staging.
- [ ] Ops lead sign-off.
- [ ] SRE on-call sign-off.
- [ ] (For safety triggers) Legal sign-off.
- [ ] Comms plan ready (re-open message drafted).

If the cause is unclear after 4 hours of investigation, the halt
is extended into the next morning's pre-flight window so a fresh
team can re-evaluate. The pilot is NOT resumed on a tired team's
hunch.

## Permanent stop

If any of the following hold simultaneously, the pilot is **ended**
(not halted) pending board review:

- 3+ separate safety incidents within 14 days.
- Regulatory action that requires re-architecting the platform.
- Refund rate > 10% sustained over 7 days.
- Driver retention < 20% week-1-to-week-2.

Ending the pilot is a strategic decision; halting is a tactical one.
This document covers tactics. Strategy lives with leadership.
