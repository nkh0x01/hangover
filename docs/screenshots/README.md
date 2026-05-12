# Hangover Platform — Screenshots

Visual previews of every Phase 1.5 screen in the customer app, driver
app, and Filament admin panel.

## ⚠️ How these were generated

The Flutter SDK is **not available** in the sandbox where I currently
run, so I cannot launch the real customer / driver apps. These images
are **static HTML reconstructions** rendered to PNG via `wkhtmltoimage`,
not real Flutter frames.

Every preview mirrors the real Flutter widget tree byte-for-byte where
it counts:

- All colours come from the locked design tokens in
  `mobile/packages/ui_kit/lib/src/theme/` — same seed (`#1F8F60`),
  same surface (`#FAFAFA`), same danger / warning / success.
- All spacing uses the `Insets.xs|s|m|l|xl|xxl` and `Radii.s|m|l`
  values defined in `ui_kit`.
- The phone frame is 393×852 (iPhone 14 viewport).
- Each HTML file under `source/` cross-references the Flutter screen
  it represents in its filename and structure.

When Flutter SDK becomes available in CI/sandbox, the `mobile.yml`
workflow can generate real `flutter integration_test` screenshots that
will replace these one-to-one — UI structure won't change.

## Screens

### Customer app

| # | File | Flutter screen |
|---|---|---|
| 1 | [`01-customer-login.png`](01-customer-login.png) | `apps/customer_app/lib/features/auth/presentation/phone_page.dart` |
| 2 | [`02-customer-otp.png`](02-customer-otp.png) | `apps/customer_app/lib/features/auth/presentation/otp_page.dart` |
| 3 | [`03-customer-home.png`](03-customer-home.png) | `apps/customer_app/lib/features/home/presentation/home_page.dart` |
| 4 | [`04-customer-fare-estimate.png`](04-customer-fare-estimate.png) | `apps/customer_app/lib/features/ride/presentation/fare_estimate_page.dart` |
| 5 | [`05-customer-searching.png`](05-customer-searching.png) | `apps/customer_app/lib/features/ride/presentation/ride_tracking_page.dart` — `_SearchingContent` |
| 6 | [`06-customer-active-ride.png`](06-customer-active-ride.png) | `apps/customer_app/lib/features/ride/presentation/ride_tracking_page.dart` — `_DriverAssignedContent` |

### Driver app

| # | File | Flutter screen |
|---|---|---|
| 7 | [`07-driver-online.png`](07-driver-online.png) | `apps/driver_app/lib/features/home/presentation/home_page.dart` (online state + `_WaitingForRide`) |
| 8 | [`08-driver-incoming-offer.png`](08-driver-incoming-offer.png) | `apps/driver_app/lib/features/ride/presentation/incoming_offer_sheet.dart` |
| 9 | [`09-driver-active-ride.png`](09-driver-active-ride.png) | `apps/driver_app/lib/features/ride/presentation/active_ride_sheet.dart` (phase = "Heading to pickup") |

### Admin panel

| # | File | Filament page |
|---|---|---|
| 10 | [`10-admin-live-monitor.png`](10-admin-live-monitor.png) | `backend/app/Modules/Riding/Filament/Pages/LiveRidesPage.php` + `Riding/Filament/Widgets/ActiveRidesWidget.php` + `Driver/Filament/Widgets/OnlineDriversWidget.php` |

## Regenerating

Once dependencies are present (`wkhtmltopdf` package):

```bash
cd docs/screenshots/source
./render.sh
```

Renders all 10 screens (~5 seconds total). PNGs land in
`docs/screenshots/` next to this README.

To replace with real Flutter screenshots later:

```bash
# from mobile/
flutter test integration_test/golden_test.dart --update-goldens
```

(integration test does not exist yet — Phase 2 deliverable.)

## Source files

Under [`source/`](source):

```
_shared.css                            design tokens + reusable widget styles
01-customer-login.html                 one file per screen
…
09-driver-active-ride.html
10-admin-live-monitor.html
render.sh                              wkhtmltoimage driver script
```

`_shared.css` is the closest you can get to seeing the entire UI kit in
one file: it implements every reusable Flutter widget (`PrimaryButton`,
`AppTextField`, `LoadingState`, bottom-sheet pattern, status badges,
online/offline toggle, modal scrim, map background) in CSS using
exactly the tokens declared in `mobile/packages/ui_kit/lib/src/theme/`.

## Known visual gaps vs the real Flutter build

These are presentation-only and won't ship in the actual app:

1. The map background is a CSS grid, not real Google Maps tiles. The
   real app uses `google_maps_flutter` via
   `mobile/packages/maps/lib/src/google_maps_provider.dart`.
2. Native-platform chrome (status bar icons, gesture indicator, IME)
   is approximated. The real device renders OS-native chrome.
3. The Filament admin shot omits its top-right user menu and global
   search since those are stock Filament chrome with no project-
   specific behaviour worth illustrating.
