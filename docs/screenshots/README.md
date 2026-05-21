# Hangover Platform — Screenshots

Visual previews of every Phase 1.6 → 2.0 screen in the customer app,
driver app, and Filament admin panel.

**Premium polish update.** The screens have been redesigned to a
fintech / premium-mobility bar (gradient heroes, glass overlays,
animated map markers, splash + destination search + ride-complete as
their own screens). The previous Phase 1.6 v1 baselines live under
[`before-premium/`](before-premium/); the Phase 1.5 / pre-redesign
baselines under [`before-phase-1.6/`](before-phase-1.6/).

Reports:
- [`../phase-1.6/premium-polish.md`](../phase-1.6/premium-polish.md) — design system, animations, production-readiness checklist, push setup, build instructions.
- [`../phase-1.6/ux-report.md`](../phase-1.6/ux-report.md) — Phase 1.6 v1 brand/bilingual/state-widgets pass.

## ⚠️ How these were generated

The Flutter SDK is **not available** in the sandbox where I run, so
I cannot launch the real apps. These images are **static HTML
reconstructions** rendered to PNG via `wkhtmltoimage`, not real
Flutter frames. Every preview mirrors the actual Flutter widget tree
using the locked design tokens in `mobile/packages/ui_kit/lib/src/`.

When Flutter SDK becomes available in CI, `mobile.yml` will swap
these for real `flutter integration_test` goldens.

## Screens

### Customer app

| # | File | Flutter source |
|---|---|---|
| 00 | [`00-splash.png`](00-splash.png) | `apps/customer_app/lib/features/splash/presentation/splash_page.dart` |
| 01 | [`01-customer-login.png`](01-customer-login.png) | `apps/customer_app/lib/features/auth/presentation/phone_page.dart` |
| 02 | [`02-customer-otp.png`](02-customer-otp.png) | `apps/customer_app/lib/features/auth/presentation/otp_page.dart` |
| 03 | [`03-customer-home.png`](03-customer-home.png) | `apps/customer_app/lib/features/home/presentation/home_page.dart` |
| 03b | [`03b-customer-destination-search.png`](03b-customer-destination-search.png) | `apps/customer_app/lib/features/ride/presentation/destination_search_page.dart` |
| 04 | [`04-customer-fare-estimate.png`](04-customer-fare-estimate.png) | `apps/customer_app/lib/features/ride/presentation/fare_estimate_page.dart` |
| 05 | [`05-customer-searching.png`](05-customer-searching.png) | `apps/customer_app/lib/features/ride/presentation/ride_tracking_page.dart` (searching phase) |
| 06 | [`06-customer-active-ride.png`](06-customer-active-ride.png) | `apps/customer_app/lib/features/ride/presentation/ride_tracking_page.dart` (driver-assigned phase) |
| 06b | [`06b-customer-ride-complete.png`](06b-customer-ride-complete.png) | `apps/customer_app/lib/features/ride/presentation/ride_tracking_page.dart` (completed phase) |

### Driver app

| # | File | Flutter source |
|---|---|---|
| 07 | [`07-driver-online.png`](07-driver-online.png) | `apps/driver_app/lib/features/home/presentation/home_page.dart` |
| 08 | [`08-driver-incoming-offer.png`](08-driver-incoming-offer.png) | `apps/driver_app/lib/features/ride/presentation/incoming_offer_sheet.dart` |
| 09 | [`09-driver-active-ride.png`](09-driver-active-ride.png) | `apps/driver_app/lib/features/ride/presentation/active_ride_sheet.dart` |

### Admin panel

| # | File | Filament source |
|---|---|---|
| 10 | [`10-admin-live-monitor.png`](10-admin-live-monitor.png) | `backend/app/Modules/Riding/Filament/Pages/LiveRidesPage.php` + widgets |

## Baselines

- [`before-premium/`](before-premium/) — Phase 1.6 v1 (brand + bilingual). Use this for the Phase 1.6 v2 (premium polish) before/after comparison.
- [`before-phase-1.6/`](before-phase-1.6/) — original Phase 1.5 prototype. Use this to see the full design-language transformation since Phase 1.5.

## Regenerating

```bash
cd docs/screenshots/source && ./render.sh
```

`render.sh` re-renders all 13 PNGs in about 5 s once `wkhtmltopdf`
is installed. PNGs land next to this README.

## Source files

```
source/
  _shared.css                            tokens + reusable styles
  00-splash.html                         splash with gradient + brand
  01-customer-login.html                 gradient brand hero
  02-customer-otp.html                   6-cell OTP with active halo
  03-customer-home.html                  map + glass live pill + gradient FAB
  03b-customer-destination-search.html   search + saved + recents
  04-customer-fare-estimate.html         gradient fare hero card
  05-customer-searching.html             pulsing concentric pins
  06-customer-active-ride.html           dark ETA banner + driver card
  06b-customer-ride-complete.html        success state + rating + share
  07-driver-online.html                  gradient online + earnings card
  08-driver-incoming-offer.html          urgency glow + hero fare
  09-driver-active-ride.html             gradient nav banner + quick actions
  10-admin-live-monitor.html             live-map preview + filters
  render.sh                              wkhtmltoimage driver
```

`_shared.css` is the closest thing to seeing the entire UI kit in one
file. It implements every reusable Flutter widget
(`PrimaryButton`, `GradientButton`, `GlassCard`, `FareHeroCard`,
`StatusPill`, `RideStatusChip`, `BottomSheetCard`, `PulsePin`,
brand mark, online toggle, modal scrim, map background) in CSS using
exactly the tokens declared in
`mobile/packages/ui_kit/lib/src/theme/`.
