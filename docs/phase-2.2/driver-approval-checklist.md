# Driver Approval Checklist

> Phase 2.2 deliverable. The hard-gates a candidate must clear before
> Ops flips them to `active`. Designed to be **printed**, ticked off
> in pen, and kept in the driver's physical folder alongside the
> photocopied documents.
>
> A single missing tick blocks activation. No exceptions during pilot.

## Driver

Full name: __________________________________________

Phone: __________________________

City: ⬜ Tbilisi    ⬜ Batumi

Onboarded by: __________________________   Date: ____ / ____ / ______

### Identity

- [ ] Government ID original sighted (front + back).
- [ ] ID expiry > 6 months.
- [ ] Face matches the headshot captured.
- [ ] Date of birth verified — candidate is 21+.

### Driving record

- [ ] Driver license original sighted; class covers the vehicle.
- [ ] License expiry > 6 months.
- [ ] At least 2 years since first license issue (sticker on back).
- [ ] No license suspensions in the last 24 months (driver attests).

### Background

- [ ] Consent form for background check signed.
- [ ] Background check result received and stored in admin.
- [ ] Result is **CLEARED** — no DUI / violent offence / open
  criminal case in the last 5 years.

### Vehicle

- [ ] Registration document sighted.
- [ ] Owner matches the driver OR an immediate family member at the
  same address (proof of address sighted).
- [ ] Technical inspection certificate valid > 60 days.
- [ ] Insurance certificate valid > 30 days.
- [ ] Insurance covers commercial passenger transport (or driver has
  acknowledged in writing the gap and accepted personal liability —
  acceptable only during pilot, NOT for GA).

### Vehicle fitness inspection

- [ ] Brakes engage smoothly without grinding or pulling.
- [ ] Both indicators functional.
- [ ] Brake light functional.
- [ ] Headlight high + low beam functional.
- [ ] Rear-view mirror present + adjusted.
- [ ] Tires: tread ≥ 2 mm on both, no visible damage.
- [ ] No fluid leaks.
- [ ] No structural damage that could compromise passenger safety.
- [ ] Helmet for the **driver**: DOT or ECE certified, < 5 years
  old, no visible cracks or impact damage.
- [ ] Helmet for the **passenger**: same standard, present and clean.
- [ ] Reflective vest worn by the driver.

### App readiness

- [ ] Hangover Driver app installed.
- [ ] Driver signed in with their phone number.
- [ ] Location permission granted (While using).
- [ ] Notification permission granted.
- [ ] Phone holder mounted within reach (recommended, not required).
- [ ] Driver has practiced toggling Online/Offline twice.

### Training ride

- [ ] Watched the trainer's full ride end-to-end.
- [ ] Completed at least one own test ride (`is_test_ride = true`).
- [ ] Followed every state transition in the correct order.
- [ ] Demonstrated cash settlement.
- [ ] Asked a clarifying question (proves engagement).

### Logistics

- [ ] Bank account / mobile-wallet number captured for payouts.
- [ ] Tax ID captured if registered as sole trader.
- [ ] Emergency contact name + phone captured.
- [ ] Day-1 quick-reference card handed over (laminated).
- [ ] Driver hotline saved as a contact in their phone.

### Sign-off

Trainer (full name): __________________________

Trainer signature: ___________________________   Date: ____ / ____ / ______

Ops lead (full name): __________________________

Ops lead signature: ___________________________   Date: ____ / ____ / ______

Once both signatures are on file, the Ops lead flips:

```
admin → Drivers → {driver}
  Driver.status               = active
  Vehicle.is_active           = true
  Driver.current_vehicle_id   = <vehicle id>
```

The driver can now go online and accept real offers.

---

## Audit trail

Photocopy this completed form and store it with the original document
photocopies. The digital trail in admin records:

- `driver_documents` rows with `verified_at` timestamps.
- `background_check_status = cleared` on the driver.
- `Driver.status` transition from `pending_approval` to `active` in
  the activity log.

For audits, the paper folder + the activity log + the Filament
documents tab together form the complete approval record.
