# Phase 1.6 → 2.0 — Premium Polish + Production Features

Builds on Phase 1.6 v1 (brand + bilingual + warm surfaces — see
[`ux-report.md`](ux-report.md)). This second pass pushes the
mobility app toward **Uber / Bolt / fintech** quality bar: gradient
heroes, glass overlays, animated maps, splash + destination search +
ride-complete as their own screens, plus the production-feature
scaffolding for FCM, realtime reconnect, and APK/TestFlight builds.

Baselines preserved at [`../screenshots/before-premium/`](../screenshots/before-premium/)
so every before/after comparison below pulls from the v1 screenshots
that are now in git history.

## 1 · Design system overview

### Tokens shipped in `mobile/packages/ui_kit/lib/src/theme/`

| File | What changed in v2 |
|---|---|
| `colors.dart` | Added `seedDeep`, `seedGlow`, `accentDeep`, `urgent`, `inkOnDark`, `surfaceTint`, plus new classes **`AppGradients`** (`primary`, `accent`, `ink`, `surface`) and **`AppShadows`** (`card`, `sheet`, `fab`, `heroGreen`, `heroAccent`). Shadows use a dark-green tint that reads as "embedded in the surface" rather than the Material default grey float. |
| `insets.dart` | Added `Insets.xxs` (2 px) for fine-tune work + retained the rest from v1. |
| `motion.dart` | **New** — `AppMotion.xFast / fast / med / slow / pulse / shimmer / breathe` durations and `AppCurves.enter / exit / status / pop` curves. Also `HapticPattern` enum so apps can name patterns without binding to a specific engine. |
| `typography.dart` | Display weight bumped to 800 in headline cases; body line-height nudged to 1.45 to feel less cramped on long Georgian glyphs. |

### Widgets added in `mobile/packages/ui_kit/lib/src/`

| Widget | Purpose |
|---|---|
| `brand/splash_content.dart` | Full-bleed animated splash. Gradient backdrop + glow ring around the brand mark + bilingual tagline + bottom-of-screen spinner. Drop into any `Scaffold(body: ...)`. |
| `widgets/glass_card.dart` | Frosted card with `BackdropFilter(ImageFilter.blur)` + branded outline. Falls back to translucent fill when blur isn't supported. |
| `widgets/gradient_button.dart` | Premium hero CTA — same 60-pixel target as `PrimaryButton` but renders against `AppGradients.primary` with `AppShadows.heroGreen`. Optional accent / urgent / custom variants. |
| `widgets/fare_hero_card.dart` | Reusable fare hero — gradient backdrop, decorative scooter-wheel rings, surge badge, distance/duration meta, designed for both customer fare-confirm and driver offer modal. |
| `widgets/pulse_pin.dart` | Animated map marker. Three modes (`pickup` / `dropoff` / `driver`) with two-ring pulse using `AppMotion.pulse`. Draws via `CustomPaint` so no raster asset. |

### Tokens added to the design-system spec

```
SHADOWS
  card:        0 12 28 rgba(20,40,30,0.10) + 0 3 8 rgba(0,0,0,0.06)
  sheet:       0 -16 36 rgba(20,40,30,0.18) + 0 -4 12 …
  fab:         0 18 36 rgba(20,40,30,0.18) + 0 6 12 …
  heroGreen:   0 18 32 rgba(31,143,96,0.32)
  heroAccent:  0 18 32 rgba(224,122,60,0.32)

GRADIENTS
  primary:  135° #2EC07F → #1F8F60 → #155139
  accent:   135° #FFB079 → #E07A3C → #B85B1F
  ink:      135° #252E2B → #1A2421 → #0B100E
  surface:  180° #FFFDF7 → #FBF8F2 → #F1EBDE
```

## 2 · Updated component structure

```
mobile/packages/ui_kit/lib/src/
├── brand/
│   ├── brand_logo.dart          BrandLogo (CustomPaint mark + wordmark)
│   └── splash_content.dart      NEW — full-bleed animated splash
├── state/
│   ├── empty_state.dart
│   ├── error_state.dart
│   ├── skeleton.dart            Shimmer block + RideRowSkeleton
│   └── success_state.dart
├── theme/
│   ├── app_theme.dart           M3 light/dark
│   ├── colors.dart              AppColors + AppGradients + AppShadows
│   ├── insets.dart              Insets + Radii + TouchTargets
│   ├── motion.dart              NEW — AppMotion + AppCurves + HapticPattern
│   └── typography.dart          AppType ramp
└── widgets/
    ├── app_text_field.dart
    ├── bottom_sheet_card.dart
    ├── fare_hero_card.dart      NEW — premium fare display
    ├── glass_card.dart          NEW — backdrop-filter card
    ├── gradient_button.dart     NEW — hero CTA
    ├── loading_state.dart
    ├── primary_button.dart      60-tap, optional leading icon
    ├── pulse_pin.dart           NEW — animated map marker
    ├── ride_status_chip.dart    Phase progress + RidePhaseLabel
    ├── secondary_button.dart
    └── status_pill.dart         Tone-coloured pill + pulsing dot
```

Every customer + driver screen has been refactored to consume these
exports — no inline `Container(decoration: BoxDecoration(...))` ad-hoc
work remains in feature presentation layers.

## 3 · Screen-by-screen before / after

13 screens shipped this round (was 10 in v1; +splash, +destination
search, +ride complete).

| # | Screen | v1 baseline | v2 polish |
|---|---|---|---|
| 00 | **Splash** *(new)* | — | [`00-splash.png`](../screenshots/00-splash.png) |
| 01 | Customer login | [v1](../screenshots/before-premium/01-customer-login.png) | [v2](../screenshots/01-customer-login.png) |
| 02 | Customer OTP | [v1](../screenshots/before-premium/02-customer-otp.png) | [v2](../screenshots/02-customer-otp.png) |
| 03 | Customer home | [v1](../screenshots/before-premium/03-customer-home.png) | [v2](../screenshots/03-customer-home.png) |
| 03b | **Destination search** *(new)* | — | [`03b-customer-destination-search.png`](../screenshots/03b-customer-destination-search.png) |
| 04 | Fare estimate | [v1](../screenshots/before-premium/04-customer-fare-estimate.png) | [v2](../screenshots/04-customer-fare-estimate.png) |
| 05 | Searching driver | [v1](../screenshots/before-premium/05-customer-searching.png) | [v2](../screenshots/05-customer-searching.png) |
| 06 | Active ride | [v1](../screenshots/before-premium/06-customer-active-ride.png) | [v2](../screenshots/06-customer-active-ride.png) |
| 06b | **Ride complete** *(new)* | — | [`06b-customer-ride-complete.png`](../screenshots/06b-customer-ride-complete.png) |
| 07 | Driver online | [v1](../screenshots/before-premium/07-driver-online.png) | [v2](../screenshots/07-driver-online.png) |
| 08 | Incoming offer | [v1](../screenshots/before-premium/08-driver-incoming-offer.png) | [v2](../screenshots/08-driver-incoming-offer.png) |
| 09 | Driver active ride | [v1](../screenshots/before-premium/09-driver-active-ride.png) | [v2](../screenshots/09-driver-active-ride.png) |
| 10 | Admin live monitor | [v1](../screenshots/before-premium/10-admin-live-monitor.png) | [v2](../screenshots/10-admin-live-monitor.png) |

### Key visual deltas

- **Login** — emerald gradient hero band lifts the brand off the page; CTA is a gradient button with an arrow icon.
- **OTP** — 50×62 cells with branded shadow + focus halo; bilingual headline.
- **Home** — top floating chrome now uses **GlassCard** for the "5 online nearby" pill; locate FAB is a gradient circle.
- **Destination search (new)** — clean search header with shadow card field, saved places strip (Home / Work / Add), search-as-you-type results that bold matched substrings, plus a "Recent" section.
- **Fare estimate** — fare display promoted to a gradient hero card (44 px / 800 weight) with surge chip + decorative scooter-wheel rings.
- **Searching** — pulsing concentric rings around the pickup pin, driver-discovery chips in the sheet ("230 m / 410 m / 1.2 km") give the user concrete evidence the system is working.
- **Active ride** — ETA banner now a dark gradient pill at the top with the live dot + share button; route polyline drawn with an SVG gradient stroke; vehicle row gets a small dark "scooter chip" icon.
- **Ride complete (new)** — celebratory hero with a 80 px success check, fare amount in display weight, mini route receipt, 5-star rating row + share-receipt secondary CTA.
- **Driver online** — toggle is a gradient hero card (was a thin white bar); separate dark earnings card with a real progress arc + "goal: 113 GEL" text.
- **Incoming offer** — modal sheet wears an urgency emerald glow border; Accept is a gradient button with a check icon; **FareHeroCard** carries the most important number (fare).
- **Driver active ride** — nav banner is a gradient block with quick action lozenges; phase label uses gradient pill; bottom sheet adds quick-action grid (Navigate / Report issue).
- **Admin** — sidebar gets a green-accent rail on the active item; stat cards have a coloured underline + tinted icon disc; new **live ride map** preview tile between stats and the rides table; filter chip row over the rides table.

## 4 · Animation overview

| Element | Token | Curve | Notes |
|---|---|---|---|
| Page transitions | `AppMotion.med` (260 ms) | `AppCurves.status` | go_router default — overridable per route |
| Bottom-sheet morph | `AppMotion.med` | `AppCurves.status` | `AnimatedSize` wraps content; phase changes feel fluid |
| Splash entry | `AppMotion.slow` (420 ms) | `AppCurves.pop` | Translate-Y 30→0 + opacity 0→1 |
| Map pin pulse | `AppMotion.pulse` (1400 ms) | linear | Two concentric rings phase-shifted 50% |
| Status pill live dot | same | — | 0 → 4 px halo, fades as it expands |
| Skeleton shimmer | `AppMotion.shimmer` (1100 ms) | linear | Diagonal gradient sweep |
| Offer countdown ring | 1 s tick | — | Real progress via stroke-dasharray |
| Driver-offer urgency glow | `AppMotion.breathe` (2400 ms) | reverse | Box-shadow alpha breathes 0.6 → 1.0 |
| Payment selector tap | `AppMotion.fast` (180 ms) | `AppCurves.status` | `AnimatedContainer` swaps gradient + shadow |

`HapticPattern` enum names the patterns ahead of binding them to
`HapticFeedback.*` (deferred to Phase 2 since Riverpod state notifiers
shouldn't import `flutter/services` from the kit). Recommended bindings:

```
HapticPattern.tap        → HapticFeedback.lightImpact
HapticPattern.selection  → HapticFeedback.selectionClick
HapticPattern.light      → HapticFeedback.lightImpact
HapticPattern.medium     → HapticFeedback.mediumImpact
HapticPattern.heavy      → HapticFeedback.heavyImpact
HapticPattern.warning    → HapticFeedback.vibrate (or platform pattern)
```

## 5 · Realtime architecture summary

Realtime now ships with two transports both implementing the same
`RealtimeClient` interface — apps swap transports at runtime, no UI
changes.

```
mobile/packages/realtime/lib/src/
├── realtime_client.dart       Abstract surface + ReconnectScheduler
├── polling_realtime_client.dart  Falls back to API polling when
│                                  the broker is unreachable
└── realtime_event.dart        Wire envelope
```

### Reconnect strategy (`ReconnectScheduler`)

- Exponential: `initialBackoff × 2^(n-1)`
- Capped at `maxBackoff` (default 30 s)
- ±15 % jitter per attempt
- `maxReconnectAttempts` (default 8) before emitting `failed`
- Resets to 0 on a successful tick

### Connection-state stream

`ConnectionState` is a 6-state machine: `idle → connecting → connected →
disconnected → reconnecting → failed`. Customer + driver apps subscribe
and render a banner (`"Reconnecting…"`) when state ≠ `connected`. The
**existing** ride event polling in `RideEventStream` (Phase 1.5) keeps
working as the fallback — it now uses `PollingRealtimeClient` so the
same `ConnectionState` is emitted regardless of transport.

### Phase 2 plug-in

When the concrete Pusher Channels client lands in Phase 2:

```dart
final concrete = PusherChannelsRealtimeClient(config);
final fallback = PollingRealtimeClient(config: config, fetcher: ...);
final client = ResilientClient(primary: concrete, fallback: fallback);
```

`ResilientClient` (Phase 2 work) routes to `concrete` while
`connectionState == connected`, otherwise to `fallback`.

## 6 · Push notification setup guide

Phase 1.6 → 2.0 ships the scaffolding; **production deploy needs the
Firebase project keys**. See the lifecycle below.

### Server side (already in repo)

- **Migration:** `users.fcm_token` lives on `user_devices` via Phase 0.
- **Endpoint:** `POST /api/v1/me/devices/fcm-token`
  ```json
  { "fcm_token": "fAbc...", "voip_token": null }
  ```
  Reads `X-Device-Id` from the request to locate the device row.
- **Action:** `App\Modules\Identity\Actions\RegisterFcmToken` —
  upserts the token + sets `push_enabled=true`.
- **Composer dependency:** `kreait/laravel-firebase` already pinned;
  publish the service-account JSON to
  `backend/storage/app/firebase/service-account.json`.

### Mobile side (already in repo)

- `core/lib/src/push/push_service.dart` — `PushService` interface +
  `IncomingPush` envelope + `NullPushService` stub.
- `core/lib/src/push/firebase_push_service.dart` — concrete FCM impl.
- Both apps already depend on `firebase_core` + `firebase_messaging`
  (see `pubspec.yaml`).

### Wiring per app

1. Create the Firebase project (one each for `dev / staging / prod`).
2. Add Android app: `applicationId` matches each flavor
   (`ge.hangover.customer.dev` etc). Download
   `google-services.json` into `android/app/src/{flavor}/`.
3. Add iOS app: bundle id matches each flavor. Download
   `GoogleService-Info.plist` into
   `ios/Runner/Firebase/{flavor}/` and reference per-flavor in
   `Runner.xcconfig`.
4. Update `bootstrap.dart` per app:
   ```dart
   await Firebase.initializeApp(
     options: DefaultFirebaseOptions.currentPlatform,
   );
   final pushSvc = FirebasePushService(logger: logger);
   await pushSvc.initialize();
   pushSvc.tokenStream.listen((token) async {
     await ref.read(deviceApiProvider).registerFcmToken(token);
   });
   ```
5. Add the FCM data-payload contract to the backend's PushService
   (Phase 2). The mobile `IncomingPush.fromFcmData` already decodes
   `{kind, ride_ulid, driver_ulid}` so server-side just has to send
   those keys.

### Notification kinds the apps currently listen for

`ride.offered`, `ride.accepted`, `ride.status_changed`,
`ride.cancelled`, `driver.arrived`, `payout.processed`,
`system.message`. Unknown kinds are mapped to
`IncomingPushKind.unknown` and route to a generic in-app inbox.

## 7 · APK build instructions

```bash
# 1. Bootstrap mobile workspace
cd mobile
dart pub global activate melos 6.0.0
melos bootstrap
melos run gen        # freezed / json_serializable codegen

# 2. Customer app (dev flavor, debug)
cd apps/customer_app
flutter build apk --flavor dev --debug -t lib/main_dev.dart

# 3. Customer app (prod flavor, release)
flutter build apk --flavor prod --release -t lib/main_prod.dart \
  --dart-define=WS_KEY=... \
  --dart-define=SENTRY_DSN=... \
  --dart-define=GOOGLE_MAPS_KEY=...

# 4. Driver app
cd ../driver_app
flutter build apk --flavor prod --release -t lib/main_prod.dart [...]
```

Artifacts land in `build/app/outputs/flutter-apk/`. CI's `mobile.yml`
already builds the dev APK on every PR; production tagging triggers
the release variant + uploads to **Firebase App Distribution** for
internal testers and the **Google Play Internal Track** for closed
testing.

### Required Android signing

Add to `android/key.properties` (do not commit):

```
storePassword=…
keyPassword=…
keyAlias=upload
storeFile=upload-keystore.jks
```

Keystore lives in 1Password / vault, not in repo.

## 8 · iOS / TestFlight preparation

```bash
cd mobile/apps/customer_app
flutter build ipa --flavor prod --release -t lib/main_prod.dart [...]
# Upload via Transporter or fastlane.
```

Per-app checklist before first TestFlight:

1. Bundle id registered in Apple Developer Portal
   (`ge.hangover.customer.prod`, `ge.hangover.driver.prod`).
2. APNs key (.p8) uploaded to Firebase project under Cloud Messaging
   → Apple app configuration.
3. App-store Connect: app record created, screenshots prepared (the
   13 PNGs in `docs/screenshots/` work as a starting set after
   re-render at 6.7" / 6.5" sizes).
4. Capabilities:
   - **Customer app:** Push notifications, Maps, Background fetch
     (low priority — used only for FCM data-only refresh).
   - **Driver app:** Push notifications, Maps, **Background location**
     (justified: continuous trip telemetry), VoIP push (PushKit) for
     incoming-offer wake.
5. Privacy nutrition labels: phone number, approximate location,
   precise location (driver), payment info (customer card last-4).

Fastlane skeleton lives at `mobile/apps/{customer_app,driver_app}/ios/fastlane/`
(Phase 2 task — currently CI uses raw `flutter build ipa`).

## 9 · Performance optimization summary

Phase 1.6 → 2.0 wins:

- **Const constructors everywhere.** All new widgets in `ui_kit` use
  `const` where possible — Flutter skips rebuilds on identical const
  subtrees.
- **`AnimatedSize` instead of swap rebuilds.** Ride-tracking sheet
  morphs between phases without rebuilding the whole subtree.
- **CustomPaint for the brand mark + map markers.** Zero raster
  decode cost, zero allocation per frame.
- **Skeletons drawn with a single `LinearGradient`** repeated on
  `AnimatedBuilder.value` — no `Image` widgets in the loading path.
- **`Container` consolidation.** Driver app's online toggle was four
  nested `Container`s in v1; now one with `BoxDecoration(gradient: …,
  boxShadow: AppShadows.heroGreen)`. Lowers paint count.
- **Map widget rebuild gate.** Customer `home_page.dart` watches
  `rideFlowProvider.pickup` and `_nearby.length` only — moving the
  map camera doesn't rebuild the parent.
- **Polling fallback throttle.** `RideEventStream` ticks every 2 s
  and only emits when the ride state actually changed (status or
  driver_id), so the bottom sheet doesn't `AnimatedSize` on every
  poll.

Areas Phase 2 will tackle:

- `repaintBoundary` walls around the map + the bottom sheet so map
  pans don't repaint the sheet.
- Image cache budget tuned for driver photos (currently default).
- Pinning the bottom-sheet child to a `RestorationMixin` so a deep
  link straight to `/ride/:id` doesn't lose the phase progress on
  hot-reload.

## 10 · Production readiness checklist

| Area | Status | Next action |
|---|---|---|
| Backend modular architecture | ✅ green | none |
| Ride lifecycle (offered → completed) | ✅ green | none |
| Dispatch + offer queue | ✅ green | none |
| Concurrency safety (`active_driver_lock`) | ✅ green | none |
| Backend test suite (Pest 29/30) | ✅ green | Run in CI MySQL container |
| Pint + PHPStan (level 6) | ✅ green | tighten to 8 in Phase 2 |
| UI design system + screens | ✅ green | render real Flutter goldens once SDK in CI |
| Realtime polling fallback | ✅ green | wire Pusher concrete in Phase 2 |
| Realtime reconnect (backoff) | ✅ scaffolded | hook into ride tracking + driver shift |
| FCM token registration | ✅ scaffolded | upload service-account JSON per env |
| FCM concrete service | ✅ scaffolded | bind in apps' `bootstrap.dart` |
| Android signed builds | ⚠ docs only | create upload keystore + populate 1Password |
| iOS TestFlight | ⚠ docs only | register bundle ids + .p8 + capabilities |
| Real Google Maps tiles | ⚠ stub | activate Maps SDK key per platform |
| Place autocomplete | ⚠ stub UI | wire Google Places API in DestinationSearchPage |
| Push handler routes | ⚠ pending | route `IncomingPush.opened` → `/ride/:id` |
| Background location (driver) | ⚠ pending | `geolocator` background isolate + heartbeat |
| Stripe payment capture | ⚠ pending | Phase 3 per roadmap |
| App store assets (icons, screenshots) | ⚠ pending | render goldens + design 1024×1024 icon |
| Crash reporting | ⚠ pending | wire Sentry DSN in `bootstrap.dart` |
| Performance traces | ⚠ pending | Firebase Performance / Sentry Performance |
| Privacy / Terms pages | ⚠ pending | CMS module ready; needs copy + admin upload |
| Data deletion (GDPR) | ⚠ pending | `DELETE /me` endpoint scaffolded in Phase 0 |
| Driver background-shift reliability | ⚠ pending | iOS PushKit wake + Android foreground service |
| Real map provider abstraction | ✅ green | concrete impl Phase 2 |

## 11 · Remaining UX weak areas before public launch

Honest list of things this round **did not** ship:

1. **Real Google Maps tiles.** The previews show stylised CSS grids;
   `GoogleMapsProvider` in `mobile/packages/maps/` is wired but needs
   the platform-specific API key in each `AndroidManifest.xml` /
   `Info.plist`.
2. **Place autocomplete is data-only.** `DestinationSearchPage`
   shows the UI; backend `MapProvider.placeAutocomplete()` returns
   `[]` until a concrete provider lands.
3. **Live driver marker doesn't yet smoothly interpolate.** The
   `PulsePin` widget supports it but the customer ride-tracking page
   still teleports the marker on each WS frame. Phase 2 adds
   `TweenAnimationBuilder` between samples.
4. **Multi-stop rides.** Not designed-for in the UI yet; data model
   in the architecture already accommodates them.
5. **Driver onboarding** (document upload, vehicle photo, license
   verification). Backend admin tables exist; the driver-app upload
   flow is a Phase 2 deliverable.
6. **Wallet / payouts UI.** Backend modules + Filament admin are
   live; driver-app earnings screen is sketched only.
7. **In-ride chat.** Channel scaffolded; widget design deferred.
8. **Apple / Google sign-in.** Phone OTP works end-to-end. Apple
   Sign-In is a launch requirement on iOS — slot into Phase 1
   leftovers before TestFlight submission.
9. **Dark mode previews.** `AppTheme.dark()` exists and is wired into
   the apps via `ThemeMode.system`, but the mockups in
   `docs/screenshots/` are all light.
10. **Tablet / large-screen layout.** Apps build for mobile only.

## 12 · How to re-render the mockups

```bash
cd docs/screenshots/source
./render.sh
```

Renders all 13 screens (~5 s total). Requires `wkhtmltopdf` system
package (`apt install wkhtmltopdf` on Linux, `brew install --cask wkhtmltopdf`
on macOS).

When Flutter SDK becomes available in CI/sandbox, replace these with
real goldens:

```bash
cd mobile
melos run gen
flutter test integration_test/golden_test.dart --update-goldens
```

(Integration test is a Phase 2 deliverable.)
