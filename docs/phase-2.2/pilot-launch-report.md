# Pilot Launch Report

> Phase 2.2 deliverable. The running log of the pilot. One daily
> entry; one weekly summary; one final retro at T+14. Paste the
> template under each section as the days unfold.

Cohort: `____________`  (e.g. `tbilisi-w1`)
Cities: ⬜ Tbilisi   ⬜ Batumi
Launch date: ____ / ____ / ______
Pilot end (planned): ____ / ____ / ______

## Approvals (at launch)

| Role                     | Name        | Sign-off date |
|--------------------------|-------------|----------------|
| Engineering              |             |                |
| Mobile                   |             |                |
| SRE                      |             |                |
| Product                  |             |                |
| Ops                      |             |                |
| Finance                  |             |                |
| Legal                    |             |                |

## Pre-launch baselines

| Metric                          | Baseline (staging QA) | Pilot target           |
|---------------------------------|------------------------|------------------------|
| Avg pickup time                | < 6 min                | < 7 min (Tbilisi)       |
| Cancel rate                    | < 15%                  | < 20%                   |
| No-drivers rate                | < 3%                   | < 5%                    |
| Mobile crash rate              | < 0.1%                 | < 0.5%                  |
| API p95 latency                | < 250 ms               | < 400 ms                |
| Push delivery success           | > 98%                  | > 95%                   |
| Driver retention (w1 → w2)     | n/a                    | > 60% (target)          |
| Customer retention (w1 → w2)   | n/a                    | > 30% (target)          |

## Daily retros

> Paste a copy of the daily retro template (`daily-monitoring-
> checklist.md`) for each day. Keep them inline below for the
> first 14 days then archive to `docs/phase-2.2/retros/`.

### Day 0 — YYYY-MM-DD

- Status: ⬜ launched as planned   ⬜ delayed   ⬜ postponed
- Notes: <one paragraph>
- Open follow-ups: ...

### Day 1 — YYYY-MM-DD
*(use the daily retro template)*

### Day 2 — YYYY-MM-DD
…

## Weekly summaries

### Week 1 — YYYY-MM-DD to YYYY-MM-DD

| Metric                | Value       | vs target  |
|-----------------------|-------------|------------|
| Total rides           |             |            |
| Completed rides       |             |            |
| Cancel rate           |             |            |
| No-drivers rate       |             |            |
| Avg pickup time       |             |            |
| Avg trip distance     |             |            |
| Avg fare              |             |            |
| Online drivers / day  |             |            |
| Unique riders         |             |            |
| Repeat riders         |             |            |
| Mobile crashes        |             |            |
| API errors (5xx)      |             |            |
| P0 incidents          |             |            |
| P1 incidents          |             |            |
| Refunds issued        |             |            |
| Refund GEL paid       |             |            |
| Driver payouts        |             |            |

**Top 3 themes from incidents:**
1.
2.
3.

**Top 3 features missed (from rider feedback):**
1.
2.
3.

**Top 3 changes shipped during week 1:**
1.
2.
3.

### Week 2 — YYYY-MM-DD to YYYY-MM-DD

(same template as Week 1)

## Cohort retention

| Cohort       | Members | Drivers w1 | Drivers w2 | Retention | Riders w1 | Riders w2 | Retention |
|--------------|---------|------------|------------|-----------|------------|------------|-----------|
| `tbilisi-w1` |         |            |            |           |            |            |           |
| `batumi-w1`  |         |            |            |           |            |            |           |

## Quality & safety

| Date       | Severity | Category          | Summary                                | Resolution                              |
|------------|----------|--------------------|-----------------------------------------|------------------------------------------|
|            |          |                    |                                          |                                          |

## Halts & resumes

| Date / time | Trigger                                | Duration | Resolution                                                  |
|--------------|----------------------------------------|----------|--------------------------------------------------------------|
|              |                                        |          |                                                              |

## Lessons (running list)

Captured as discovered; consolidated at T+14.

- ...

## T+14 — go / no-go on expansion

### Quantitative pass-criteria

- [ ] Cancel rate ≤ 20% (week 2)
- [ ] No-drivers rate ≤ 5% (week 2)
- [ ] Driver retention w1→w2 ≥ 50%
- [ ] Customer retention w1→w2 ≥ 30%
- [ ] Zero unresolved P0 safety incidents
- [ ] Mobile crash rate ≤ 0.5%
- [ ] API uptime ≥ 99.5% (excluding planned maintenance)

### Qualitative pass-criteria

- [ ] At least one rider testimonial captured for marketing
- [ ] At least one driver testimonial captured for hiring
- [ ] No outstanding Legal items
- [ ] No outstanding regulatory items
- [ ] Documented backlog of follow-up engineering work

### Recommendation

⬜ **Expand** — proceed to Phase 2.3 (next-city onboarding +
broader-public beta)
⬜ **Iterate** — extend pilot 2 weeks with named fixes
⬜ **Halt + retro** — pause, rework, plan a fresh pilot in Phase 2.4

Recommendation by: ____________________ (Ops lead)
Concurrence: ____________________ (CTO)  ____________________ (PM)

Date: ____ / ____ / ______

## Appendix — raw exports

Each day's CSV export from `/admin → Rides → Export (filter: today)`
is uploaded to the long-term storage bucket. Keys:

```
s3://hangover-pilot/exports/YYYY-MM-DD/rides.csv
s3://hangover-pilot/exports/YYYY-MM-DD/incidents.csv
s3://hangover-pilot/exports/YYYY-MM-DD/drivers.csv
```

These exports are immutable once written; corrections happen via
addenda, never overwrites.
