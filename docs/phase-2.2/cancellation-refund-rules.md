# Cancellation & Refund Rules

> Phase 2.2 deliverable. Authoritative pilot policy for who pays
> what when a ride doesn't complete cleanly. Approved by Legal +
> Finance + Support before public launch.
>
> Currency: GEL throughout (Tbilisi + Batumi pilot). Numbers are
> conservative — designed to be expanded in Phase 3, not shrunk.

## Decision matrix

### Customer cancels

| When customer cancels                                | Fee     | Refundable? | Notes                                            |
|------------------------------------------------------|---------|-------------|--------------------------------------------------|
| Before any driver is matched (status `requested` / `searching`) | 0 GEL   | n/a         | Silent — driver never saw the request           |
| < 2 minutes after a driver accepts                   | 0 GEL   | n/a         | Goodwill window                                  |
| ≥ 2 min, driver still en route                       | 2 GEL   | Yes         | Compensates driver for time + fuel               |
| Driver has arrived (`driver_arrived`)                | 5 GEL   | Yes         | Compensates driver for arrival + ≤ 5 min wait    |
| After **Start trip**                                 | N/A     | n/a         | Cancellation is **disabled** in the app          |

Refundable = "if support determines the fee was unfair, we'll
credit the user". See "Refund eligibility" below.

### Driver cancels

| When driver cancels             | Customer charge | Driver consequence       |
|---------------------------------|------------------|---------------------------|
| Any time before start           | 0 GEL            | Strike (see "Strikes")    |
| After **Start trip**            | Pro-rated fare for the distance covered | Strike + manual review |

A "strike" is a counter on `drivers.cancellation_strikes`. The
counter resets every 14 days. Three strikes in any 14-day window =
automatic 24 h suspension + retraining call. Five strikes = manual
off-board review.

### No-show (driver-initiated)

| Scenario                                              | Customer charge | Driver consequence       |
|-------------------------------------------------------|------------------|---------------------------|
| Driver arrived, waited ≥ 5 min, customer never appeared | 5 GEL no-show fee paid to driver | None |
| Driver "arrived" but GPS shows they were > 200 m away | 0 GEL            | Strike + manual review    |

### System cancels

| Scenario                              | Customer charge | Resolution                         |
|---------------------------------------|------------------|-------------------------------------|
| Offer expired with no driver response (status `no_drivers`) | 0 GEL | Customer prompted to re-request    |
| Realtime broker dropped mid-ride      | 0 GEL            | Manual review by ops within 1 h    |
| Driver app crashed mid-ride           | Pro-rated fare for distance covered | Manual review by ops within 1 h |

### Force-cancelled by ops

| Scenario                                | Driver fee paid | Customer charge | Logged as       |
|-----------------------------------------|------------------|------------------|------------------|
| Safety incident                         | 0 GEL            | 0 GEL            | Safety event     |
| Vehicle breakdown                       | 5 GEL (no-show fee) | 0 GEL            | Mechanical issue |
| Driver fraud detected                   | 0 GEL            | 0 GEL            | Fraud            |
| Customer abuse detected                 | Pro-rated        | Pro-rated        | Customer abuse   |

## Refund eligibility

Auto-approve in support tier 1 if:

- Customer was charged a cancellation fee but `RideStatusLog` shows
  the driver was ≥ 5 min late to the ETA window.
- Customer was charged a cancellation fee for an arrival they
  couldn't see in the app (Sentry shows the customer app crashed
  during the arriving phase).
- Driver cancelled before arrival but the fee was charged
  incorrectly (data bug).
- Wrong vehicle / driver at the pickup point (the customer reports
  the plate; we verify against the booking).

Send to tier 2 if:

- Customer disputes the final fare amount > 10 GEL.
- "I was charged but never rode" claim with > 24 h delay.
- "Driver took the wrong route" — needs GPS-trace review.
- Lost item not yet retrieved.

Send to Finance if:

- Refund > 100 GEL in a single ride.
- Pattern of refunds from one user > 3 in 30 days (potential abuse).

## How refunds settle

Pilot is cash-only. Refunds therefore land as **in-app wallet
credit**:

- Table: `wallet.credits` (`amount`, `reason`, `expires_at`,
  `source_ride_id`).
- Default expiry: **60 days** from issue.
- Visible to the user in `Profile → Wallet → Credits`.
- Auto-applied to the next ride at booking time, oldest first.

Filament action `admin → Rides → {ride} → Issue refund`:
- Captures: amount, reason code, agent.
- Creates the wallet credit.
- Sends an SMS + in-app notification to the customer.
- Logs the change to `support.incident_events`.
- If `amount > 50 GEL`, requires supervisor approval (handled by
  the action's Filament authorization rule).

## Driver payout adjustments

Cancellation fees + no-show fees flow into `payouts` against the
**driver** at the standard 15% platform commission. The Monday
payout includes them automatically.

If a strike is later reversed (manual review), the corresponding
fee is reversed:
- Customer's wallet credit is voided (or already paid out as
  credit usage — in which case Finance absorbs).
- Driver's payout for that day is adjusted on the next cycle.

## Disputes

If the customer disputes the refund decision:

1. Tier 1 captures the dispute and escalates to tier 2.
2. Tier 2 reviews the full ride context: GPS, broadcasts, app logs,
   prior history.
3. Decision communicated in-app within 24 h.
4. If the customer remains dissatisfied → escalated to Ops lead +
   Legal.

Repeated unsubstantiated disputes (> 3 in 30 days) can result in
account suspension (`Users.suspended_at`), with appeal route via
**ops@hangover.app**.

## Audit + record retention

- All refund actions log to `support.incident_events`.
- The corresponding `payments.adjustments` row stores the new
  `final_amount` so the ride row remains immutable from a financial
  perspective.
- Records retained for 5 years (Georgian tax requirement). After
  5 years, PII is anonymised; financial totals remain.

## Policy review cadence

- **Weekly** during pilot week 1: review every refund > 30 GEL.
  Owner: Finance lead.
- **Bi-weekly**: review aggregate refund rate. Target < 1% of
  completed rides. Above 2% triggers a root-cause investigation.
- **Quarterly post-pilot**: policy revisions based on accumulated
  data + legal updates.

## Customer-facing summary

The summary the customer sees in the app's Help section:

> **Cancellations**
>
> Free up to 2 min after booking. Then **2 GEL** if your driver is on
> the way, or **5 GEL** if they've arrived. Cancellations after Start
> are not possible.
>
> **Refunds**
>
> If you think a charge was wrong, tap **Help** on the ride and
> we'll review it. Refunds land in your in-app wallet as credit
> within minutes; we use the credit automatically on your next ride.
> Credits expire after 60 days.
>
> **Drivers cancelling on you**
>
> If your driver cancels, we re-match you with someone nearby — no
> charge to you. If that takes too long, we'll send you a goodwill
> credit.
