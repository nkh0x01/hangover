# Support & Incident Workflow

> Phase 2.2 deliverable. How customer + driver issues land in front of
> a human and how that human is expected to act. Designed to be the
> single source of truth for the support team during pilot.

## Channels

| Channel                | Audience | SLA target              | Owner          |
|------------------------|----------|--------------------------|----------------|
| In-app **Help** form   | Riders   | First response < 30 min  | Support tier 1 |
| In-app **Report** button (on every ride card) | Riders + drivers | Triage < 10 min for in-progress, < 30 min closed rides | Support tier 1 |
| Driver hotline (24/7)  | Drivers  | Pick-up < 30 s; resolution < 5 min for in-trip emergencies | On-call ops |
| ops@hangover.app       | Any      | < 4 h                    | Support tier 2 |
| Filament `Incidents`   | Internal | n/a                      | Ops engineer   |
| PagerDuty (P0 only)    | Internal | Page in < 60 s of trigger | SRE on-call    |

All inbound traffic funnels into the Filament `Support → Incidents`
table. The in-app reports auto-create rows; the hotline + email
routes get a row created manually by the receiving agent.

## Severity ladder

| Severity | Definition                                                                 | First-touch SLA | Escalation                          |
|----------|----------------------------------------------------------------------------|------------------|--------------------------------------|
| **P0**   | Safety event (accident, assault, missing rider), or platform outage.       | < 60 s           | PagerDuty SRE + Ops lead immediately |
| **P1**   | Active ride disruption — rider stranded, driver stuck, payment dispute > 50 GEL. | < 10 min   | Support tier 2 + Ops lead            |
| **P2**   | Closed-ride dispute, refund request < 50 GEL, account access issue.        | < 4 h            | Support tier 2 if not closed in 24 h |
| **P3**   | Feature request, general feedback, "how do I…" question.                   | < 24 h           | None                                  |

Severity is set by the **receiving agent** on intake, can be raised by
the assignee, never lowered without supervisor sign-off.

## Incident lifecycle

```
intake → triage → action → resolution → review

         ↑                         ↓
         └── reopen ←─── follow-up ┘
```

State machine:

```
new → triaging → in_progress → awaiting_user → resolved → closed
                    ↓
               escalated_p0
                    ↓
               post_mortem
```

State transitions live in the `support.incidents` table (`status`
column) with a row in `support.incident_events` per change.

## Intake script — in-app Help / Report

The mobile app prefills:

- `user_id`, `device_uuid`, `app_version`, `ride_id` (if from the
  ride card)
- The user's last 5 ride summaries
- Last 50 lines of `AppLogger` output (auto-redacted of phone numbers)

The agent does:

1. **Confirm identity** — match the phone in the user record.
2. **Set severity** using the ladder above.
3. **Categorise** with a free-text tag (`payment`, `safety`, `app-bug`,
   `driver-conduct`, `lost-item`, etc.).
4. **Capture the question** in the agent's own words.
5. Move to triage.

## Triage script

Look for the most likely cause in this order:

1. **Active ride?** Yes → P1 minimum; check `live-rides` page for the
   ride's real state. Reach the counterparty if safety is at stake.
2. **Closed ride dispute?** Pull the ride from `/admin → Rides`. Open
   the `RideStatusLog` tab to see the timeline.
3. **Payment dispute?** Pull the corresponding `payments` row and the
   driver `payouts` row.
4. **App crash?** Match the timestamp + user against Sentry. Attach the
   Sentry event id to the incident.
5. **Account locked?** Check `phone_verifications` table for rate-
   limit hits; check `users.suspended_at`.

Document what you found in the incident notes.

## Action — common scripts

### Refund / fare adjustment
Permission required: `incident.refund.issue` (Support tier 1 has it
up to 50 GEL; supervisor for higher).
1. Open the ride. Verify the dispute.
2. `admin → Rides → {ride} → Adjust fare` action. Enter the new
   `final_amount` and a reason code. The action runs `IssueRefund`,
   which reverses the wallet entry + queues the payout adjustment.
3. Notify the customer in-app + by SMS.
4. Move incident to `resolved`.
See `cancellation-refund-rules.md` for the policy matrix.

### Driver no-show
1. Confirm via the live map that the driver did not approach the pin.
2. If the driver is online → call them via the hotline. Get the story.
3. If they were genuinely unreachable, mark the cancellation
   `driver_fault`. Customer cancellation fee is waived. Driver gets a
   strike (3 strikes in 14 days → suspension).
4. If their explanation is reasonable (flat tire), waive the strike
   but explain the threshold.

### Account locked / OTP not arriving
1. `admin → Users → {user}`. Check `phone_verifications` table:
   - If `attempts >= max_attempts` → reset via `Reset OTP` action.
   - If the latest entry shows a Twilio failure → re-send manually
     and capture the error in the incident.
2. If the user is in `suspended` state, only a supervisor can unsuspend.

### Lost item
Tier 1 has read access to the driver's phone in admin. Initiate a
3-way call. Item handover is at the depot the same day or next
morning. We don't take responsibility for items left in the vehicle
but we do facilitate.

### Driver conduct complaint
1. Tier 1: collect the rider's statement verbatim.
2. Tier 2: pull the ride from live monitor + GPS trace.
3. Tier 2 lead: 1-on-1 call with the driver.
4. If the complaint is substantiated:
   - First infraction → warning + retraining session.
   - Second infraction → 7-day suspension.
   - Third infraction → permanent off-board.
5. Document every step in the incident + the driver's notes.

## Resolution + follow-up

Before closing an incident:

- [ ] The user has been told what happened, in writing (in-app message
  or SMS).
- [ ] If money was moved, the user has been told the amount and the
  ETA for it to land.
- [ ] If the issue suggests a code or process bug, a GitHub issue or
  Linear ticket has been filed and the incident links to it.
- [ ] Severity hasn't crept down without supervisor sign-off.

After 7 days, if the user hasn't replied to "anything else?" the
incident auto-closes from `awaiting_user`.

## P0 incident — accident / safety

Strict 9-step playbook. Practiced in dress rehearsal.

1. **0–60 s**: receiving agent confirms it's a safety event, sets P0,
   triggers PagerDuty.
2. **60 s – 5 min**: Ops lead reaches both rider and driver by phone.
   If injuries are reported → ensure 112 has been called. If not,
   advise calling 112 and stay on the line.
3. **5–15 min**: SRE pulls the GPS trace, ride state log, and any
   nearby drivers' positions. Saves to an incident artifact bucket.
4. **15–30 min**: Ops lead reaches the on-site investigator
   (police / first responders) and coordinates witness statements.
5. **30 min – 4 h**: Insurance contact informed. Pending the formal
   police report, driver is auto-suspended.
6. **4–24 h**: Public-facing comms drafted by PR if anyone outside the
   parties is asking.
7. **T+1 day**: Initial post-mortem written.
8. **T+5 days**: Full post-mortem reviewed by Legal + Ops + Eng.
9. **T+7 days**: Action items filed; relevant SOP updates merged.

The driver returns to the platform only after Legal sign-off.

## Off-boarding a driver (§6)

When a driver must leave the platform (voluntary or forced):

1. Set `Driver.status = suspended` + reason code.
2. Cancel any in-flight rides assigned to them — `admin → Live rides →
   force cancel`.
3. Settle outstanding earnings on the next regular payout cycle
   (or earlier on request). Withhold for active investigation.
4. Send an off-board message via SMS with appeal instructions.
5. Remove their phone from `PILOT_TEST_PHONES` if listed.
6. Archive their `driver_documents` rows. Don't delete — we need the
   record for audit and potential reinstatement.

## Metrics

Surface in `admin → Operations → Pilot dashboard` once the support
table fills up:

- New incidents / day, by severity.
- Median first-response time, by channel.
- Median time-to-resolution, by severity.
- Top 5 categories.
- % of incidents that produce a code/process fix.

Weekly review on Mondays; full retro every two weeks.
