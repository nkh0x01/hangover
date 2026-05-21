# Customer Support FAQ

> Phase 2.2 deliverable. The canonical answers Tier 1 support reads
> back. Also published verbatim to `hangover.app/help` and surfaced
> inside the customer app's Help section.
>
> Each entry has an internal note (in italics) that explains the
> reasoning and any escalation path. Strip the italics for the
> public version.

## Account & sign-in

### How do I sign up?

Open the Hangover app, tap **Continue**, enter your phone number,
and we'll text you a 6-digit code. Enter the code on the next
screen. That's it — no email, no password.

*Internal: OTP is sent via Twilio in prod; staging accepts `000000`
unless `PILOT_ENABLED=true`. Rate-limited to 5 attempts per phone
per hour by `phone_verifications.attempts`.*

### I didn't get the code.

Wait 30 seconds and tap **Resend**. Make sure your phone is in
range and SMS is on. If you still don't get it after two attempts,
contact us at +995 32 NNN NNN and we'll help you sign in manually.

*Internal: Check `phone_verifications` for the latest entry.
Twilio errors land in `storage/logs/security.log`. If Twilio is
down, fall back to manually setting a known OTP in the DB and
walking the customer through entry over the phone.*

### How do I change my phone number?

For the pilot, this requires support help. Call us. We'll verify your
identity by asking for two recent ride drop-off locations, then move
your account to the new number.

*Internal: `admin → Users → {user} → Change phone`. The action
revokes all existing Sanctum tokens and triggers a fresh OTP on
the new number.*

### My account is locked.

Most lockouts happen after too many incorrect OTP entries. Wait
30 minutes and try again. If you're still locked out, contact us.

*Internal: tier 1 has a "Reset OTP" Filament action. If `suspended_at`
is set, route to tier 2 — only a supervisor can unsuspend.*

## Booking & rides

### How do I book a ride?

Open the app, set your destination using the **Where to?** field,
confirm the pickup pin, review the fare, and tap **Confirm**. We'll
match you with the nearest driver within a few seconds.

### How long until a driver arrives?

You'll see an estimated arrival time on the screen after a driver
accepts. Most pickups during pilot are 3-6 minutes in Tbilisi and
2-4 minutes in Batumi.

### Why am I told "no drivers available"?

It means no available drivers were within range during your search
window. This can happen during weather events, late at night, or
during a city event. Try again in 5 minutes, or call the hotline
if you need a ride urgently.

*Internal: review `dispatch.log` for the ride. If no-drivers-rate is
spiking, escalate to ops on call.*

### Can I book a ride for someone else?

Yes — but the pickup pin is set from the rider's phone, not yours.
For the pilot, the easiest way is to have your friend install
the app on their own phone.

### Can I add a stop on the way?

Not yet. Phase 2.4 will add multi-stop trips.

### Why was my fare estimate higher than what I paid?

The estimate uses the typical route + traffic at the time. If the
driver takes a shorter route, or traffic clears, the final fare
is lower. We never charge more than the estimate without telling
you first.

*Internal: this is encoded in `quoted_amount` vs `final_amount` on
the ride row. If `final_amount > quoted_amount`, the surge
multiplier kicked in mid-trip — a known edge case.*

## Pricing & payment

### How is the fare calculated?

A base fare plus a per-km plus a per-minute rate. In Tbilisi:
2.50 GEL base, 0.95 GEL/km, 0.20 GEL/min. In Batumi: 2.50 GEL base,
0.85 GEL/km, 0.20 GEL/min. We round to the nearest 0.10 GEL.

### What payment methods do you accept?

For the pilot, cash only. The driver collects on completion. Card
and wallet land in Phase 3.

### Can I tip the driver?

Yes — directly in cash. There's no in-app tipping during pilot.

### Why did my fare go up during the ride?

Two possible reasons: (a) the trip took longer than estimated
because of traffic, or (b) surge pricing was active at booking
because demand was high. Surge during pilot is capped at 1.5× the
base. You see the multiplier on the fare-estimate screen before
you confirm.

## Cancellations & refunds

See [`cancellation-refund-rules.md`](cancellation-refund-rules.md)
for the full policy. The short version:

### Can I cancel a ride?

Yes, up until the driver presses **Start trip**. Tap the menu on
the ride card, then **Cancel**, then pick a reason.

### Will I be charged for cancelling?

- Within 2 minutes of booking → no.
- After the driver is already 1+ minute towards you → 2 GEL.
- After the driver has arrived → 5 GEL.

We waive the fee if the driver was running late, had the wrong
vehicle type, or never reached out.

### I cancelled but you charged me anyway. Help.

Contact us — we'll review and refund if the fee was incorrect.
Refunds settle back to your wallet (in-app credit) within 5 minutes.
For cash-payment refunds, we'll credit your next ride.

*Internal: cash-payment refunds = `wallet.credits` entry of type
`refund.cash`, expires after 60 days.*

### What happens if my driver cancels?

Your ride is automatically re-offered to the next nearest driver.
You won't be charged.

## Safety & lost items

### What if I leave something in the vehicle?

Tap **Help → Lost item** on your last ride card. We'll connect you
with the driver and arrange a pickup, usually within 24 hours.

*Internal: use the 3-way call feature in admin. We don't
indemnify against loss — make that clear before the call.*

### Is the driver verified?

Every driver completes ID verification, license check, vehicle
inspection, and a background check before going online. We collect
photos of the vehicle so you can confirm at pickup.

### What if I feel unsafe during a ride?

Tap **Safety → Call 112** in the ride card — it dials the
emergency number with your live location attached. You can also
call our 24/7 hotline at +995 32 NNN NNN.

*Internal: the in-app Safety button posts a `safety.alarm`
incident to the support inbox in real time. Tier 1 must respond
within 60 seconds — page the on-call.*

### Why does the app need my location?

To put a pin where you actually are so the driver can find you,
and to show you the driver's position so you know they're on the
way. We don't track your location when the app isn't open and you
don't have an active ride.

## App problems

### The app keeps crashing.

Sorry — please make sure you have the latest version (Settings →
About → Build). If you do, write to us with your phone model and
OS version. Crashes auto-report so we usually already know.

*Internal: Sentry. Cross-reference the user's `user_id` with the
issues list.*

### The map doesn't load.

That's usually a network issue. Toggle airplane mode on then off,
or switch between Wi-Fi and mobile data. If it still doesn't load,
restart the app.

### My location is wrong.

Background-location accuracy depends on GPS reception. Indoors or
in narrow streets, the pin can be off by 10-50 m. Step outside, give
the app 10 seconds to re-acquire, and move the pickup pin manually
if needed.

## Pilot-specific

### Is the service available everywhere?

For the pilot we're running in **Tbilisi** (mostly inside the
Mtatsminda / Vake / Saburtalo loop) and **Batumi** (the boulevard
and adjacent neighbourhoods). Outside these zones, you'll see
"Not yet in your area". Phase 3 expands the footprint.

### What are the hours?

07:00 to 23:00 local time, seven days a week, during pilot.

### How long is the pilot?

Two weeks initial, then we re-evaluate. If we expand to broader
launch we'll announce it in-app a week in advance.

### Can I be a driver?

Yes — apply at `hangover.app/drive`. Onboarding takes 3-5 days
during pilot due to manual document review.

## Couldn't find your answer?

Tap **Help → Talk to us** in the app, write to **support@hangover.app**,
or call the hotline at **+995 32 NNN NNN**. We aim to reply to the
first message within 30 minutes during pilot hours.
