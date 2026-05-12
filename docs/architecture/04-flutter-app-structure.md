# 04 — Flutter App Structure

## 4.1 Strategy: two apps, one shared package

We ship two separate Flutter apps (different bundle IDs, app icons, push topics, store listings) backed by one shared package that contains everything app-agnostic.

```
mobile/
├── apps/
│   ├── customer/            # ge.hangover.customer
│   └── driver/              # ge.hangover.driver
├── packages/
│   ├── core/                # design system, theme, i18n shells, app shell, router
│   ├── data/                # API client, models, repositories, local DB
│   ├── domain/              # entities, use cases, pure Dart
│   ├── realtime/            # WS client, presence, geo encoding, retry
│   ├── maps/                # MapProvider abstraction + Google/Mapbox impls
│   ├── payments/            # Stripe + Apple/Google Pay + cash
│   ├── auth/                # phone OTP, Google, Apple, token store
│   └── analytics/           # Firebase Analytics + Sentry wrappers
├── melos.yaml               # monorepo orchestration
├── pubspec.yaml             # workspace (Dart 3 / Flutter 3.x stable)
└── README.md
```

We use **Melos** for the monorepo; **Flutter Workspaces** (Flutter ≥ 3.22) can replace it later but Melos works on every stable.

## 4.2 Per-app structure (Customer app shown; Driver mirrors it)

We follow **feature-first clean architecture**: each feature is a slice with `presentation`, `application`, `domain`, `data` layers.

```
apps/customer/
├── lib/
│   ├── main.dart                       # entrypoint, env bootstrap, runZonedGuarded
│   ├── main_dev.dart
│   ├── main_staging.dart
│   ├── main_prod.dart
│   ├── app.dart                        # MaterialApp.router, theme, l10n
│   ├── bootstrap.dart                  # service locator init, error handlers
│   ├── config/
│   │   ├── env.dart                    # const flavor configuration
│   │   ├── feature_flags.dart
│   │   └── app_router.dart             # go_router config
│   ├── di/
│   │   └── locator.dart                # get_it/riverpod provider container
│   ├── features/
│   │   ├── auth/
│   │   │   ├── data/
│   │   │   │   ├── auth_api.dart
│   │   │   │   └── auth_repository_impl.dart
│   │   │   ├── domain/
│   │   │   │   ├── auth_repository.dart
│   │   │   │   ├── entities/
│   │   │   │   └── usecases/
│   │   │   │       ├── request_otp.dart
│   │   │   │       ├── verify_otp.dart
│   │   │   │       ├── google_sign_in.dart
│   │   │   │       └── apple_sign_in.dart
│   │   │   └── presentation/
│   │   │       ├── pages/
│   │   │       │   ├── welcome_page.dart
│   │   │       │   ├── phone_input_page.dart
│   │   │       │   ├── otp_page.dart
│   │   │       │   └── name_setup_page.dart
│   │   │       ├── controllers/
│   │   │       │   └── auth_controller.dart   # Riverpod Notifier
│   │   │       └── widgets/
│   │   ├── home/
│   │   │   └── presentation/pages/home_page.dart   # map + bottom sheet shell
│   │   ├── ride_request/
│   │   │   ├── domain/usecases/
│   │   │   │   ├── estimate_fare.dart
│   │   │   │   ├── request_ride.dart
│   │   │   │   └── cancel_ride.dart
│   │   │   └── presentation/
│   │   │       ├── controllers/ride_request_controller.dart
│   │   │       └── widgets/
│   │   │           ├── pickup_dropoff_sheet.dart
│   │   │           ├── fare_estimate_card.dart
│   │   │           ├── payment_method_picker.dart
│   │   │           └── promo_code_field.dart
│   │   ├── ride_tracking/
│   │   │   ├── domain/usecases/
│   │   │   │   ├── subscribe_to_ride.dart
│   │   │   │   └── chat_with_driver.dart
│   │   │   └── presentation/
│   │   │       ├── pages/ride_tracking_page.dart
│   │   │       └── widgets/
│   │   │           ├── driver_card.dart
│   │   │           ├── ride_status_pill.dart
│   │   │           ├── live_driver_marker.dart
│   │   │           └── chat_sheet.dart
│   │   ├── ride_history/
│   │   ├── rating/
│   │   ├── profile/
│   │   ├── addresses/
│   │   ├── wallet/
│   │   ├── promos/
│   │   ├── support/
│   │   ├── notifications/
│   │   └── settings/
│   ├── l10n/
│   │   ├── app_en.arb
│   │   ├── app_ka.arb
│   │   └── app_ru.arb
│   └── platform/
│       ├── deep_links.dart
│       ├── push_handler.dart
│       └── lifecycle.dart
├── assets/
│   ├── images/
│   ├── icons/
│   ├── lottie/
│   └── fonts/
├── android/
├── ios/
├── test/
│   ├── features/
│   ├── widget/
│   └── integration/
├── integration_test/
├── pubspec.yaml
└── analysis_options.yaml
```

## 4.3 Shared packages — purpose & content

### `packages/core`

- Design system: `AppTheme.light`, `AppTheme.dark`, color tokens, typography (`HangoverTypography`), spacing tokens (`Insets`), radii, motion durations.
- Reusable widgets: `PrimaryButton`, `SecondaryButton`, `AppTextField`, `BottomSheetScaffold`, `LoadingState`, `EmptyState`, `ErrorState`, `RatingStars`, `MoneyText`, `Avatar`.
- App shell scaffolding: `AppShell`, navigation chrome, snackbar host, dialog host.
- i18n bootstrap (locale loading), date/time formatters with `intl`.
- Logging facade.

### `packages/data`

- `ApiClient` (Dio) with:
  - `AuthInterceptor` (Sanctum bearer + refresh-on-401 logic, see [06](06-authentication-flow.md))
  - `IdempotencyInterceptor` (auto-generates `Idempotency-Key` for mutating calls)
  - `RequestIdInterceptor`
  - `RetryInterceptor` (exponential backoff, only for idempotent verbs and 5xx)
  - `LocaleInterceptor`
  - Certificate pinning via `dio_certificate_pinning`
- DTO models generated via `freezed` + `json_serializable`.
- Repository implementations talking to the API (`AuthRepositoryImpl`, `RideRepositoryImpl`, `WalletRepositoryImpl`, …).
- Local persistence:
  - `secure_storage` for tokens.
  - `drift` (or `hive`) for offline-safe caches: addresses, recent rides, draft messages, queued events.
- An outbox: any user-facing mutation made offline is queued and replayed when connectivity returns (driver app needs this most — finishing a ride in a tunnel).

### `packages/domain`

- Pure Dart entities (`Ride`, `RideStatus`, `Driver`, `Customer`, `Fare`, `Location`, `PaymentMethod`, …) using `freezed` for immutability.
- Use case interfaces and base classes.
- No Flutter imports — testable on the Dart VM.

### `packages/realtime`

- `RealtimeClient` wrapping `pusher_channels_flutter` (Reverb is Pusher-protocol compatible).
- Channel managers: `RideChannel(rideId)`, `DriverChannel(driverId)`, `CityPresenceChannel(cityId)`.
- Backoff + reconnect, presence ping, foreground/background lifecycle handling.
- Adaptive location-publish controller for the **driver** app:
  - 10–30 s when idle/online (battery saver)
  - 2–5 s when offered or accepted but trip not started
  - 1 s during active trip
  - Pauses below 5 km/h and after 30 s of no movement.

### `packages/maps`

- `MapProvider` Dart interface: `Widget mapWidget(...)`, `route(from,to)`, `eta(from,to)`, `reverseGeocode(point)`, `placeAutocomplete(query)`.
- Implementations: `GoogleMapsProvider` (uses `google_maps_flutter`), `MapboxProvider` (uses `mapbox_maps_flutter`).
- Selected by build flavor; app code never imports a concrete provider.

### `packages/payments`

- Stripe SDK wrapper (`flutter_stripe`).
- Apple Pay / Google Pay sheets.
- Cash flow (no SDK; UI + state only).
- A unified `Charge` API the ride flow calls regardless of method.

### `packages/auth`

- Phone OTP flow (`requestOtp`, `verifyOtp`, resend cooldown).
- Google Sign-In (`google_sign_in`) → exchange ID token for Sanctum token.
- Apple Sign-In (`sign_in_with_apple`) → same.
- Token store + auto-refresh.
- `AuthState` stream that the rest of the app listens to.

### `packages/analytics`

- Firebase Analytics events typed via Dart sealed classes (`AnalyticsEvent.rideRequested(distanceKm)` → string + params).
- Sentry init (with release tags from build flavor).
- `Tracer` for performance spans (TTL into Sentry Performance).

## 4.4 State management

**Riverpod 2.x** (with `riverpod_generator` for codegen) is the only state-management library.

- `AsyncNotifier` for any state that does async work.
- `Notifier` for sync state.
- Service locator via `get_it` is **only** for non-Riverpod-friendly singletons (e.g. native plugins requiring eager init).

Reasoning: Riverpod compiles cleanly, has first-class testability, and avoids the BuildContext gymnastics of Provider/Bloc for this team's expected size. (Bloc is also acceptable; we lock to Riverpod for consistency.)

## 4.5 Routing

`go_router` for declarative deep-linkable routes. Top-level routes for the customer app:

```
/                         → splash → redirect by auth state
/onboarding               → first-launch carousel
/auth/phone               → phone entry
/auth/otp                 → otp verification
/auth/name                → name setup (first-time)
/home                     → map + bottom sheet (default after login)
/ride/request             → pickup/dropoff selection
/ride/estimate            → fare confirm
/ride/:rideUlid           → live tracking (active ride)
/ride/:rideUlid/chat
/ride/:rideUlid/rate
/history
/history/:rideUlid
/profile
/profile/edit
/addresses
/wallet
/promos
/support
/settings/language
/settings/notifications
```

Driver app top-level routes:

```
/auth/...                 same as customer
/onboarding/docs          driver doc upload checklist
/onboarding/pending       awaiting approval
/home                     map + online toggle + offer sheet
/offer/:rideUlid          incoming offer screen (push-launched)
/active/:rideUlid         ride in progress
/active/:rideUlid/nav     navigation overlay
/earnings                 daily/weekly/monthly
/earnings/withdraw
/profile
/documents
/support
/sos                      modal route
```

## 4.6 Theming

- Material 3.
- Light + dark fully implemented from day one.
- Color tokens in `core/lib/theme/colors.dart`; never hard-coded in features.
- Typography uses **Noto Sans Georgian** + **Inter** Latin fallback to render Georgian, English, Russian glyphs consistently.
- All paddings/margins reference `Insets.xs|s|m|l|xl|xxl` (4/8/12/16/24/32).
- Radii: `Radii.s|m|l` (8/12/16).
- Motion: `Motion.fast = 150ms`, `Motion.med = 250ms`, `Motion.slow = 400ms`.

## 4.7 Internationalization

- Flutter `intl` + `.arb` files.
- Three locales locked: `ka`, `en`, `ru`. Default `ka`. RTL is not required.
- Money formatting via `NumberFormat.simpleCurrency(locale, name)` with explicit currency code from the API response (not from device locale).
- Date formatting via `DateFormat.yMMMd(locale)` etc., always converting UTC server timestamps to device TZ.

## 4.8 Offline & resiliency

- **Read-side**: latest profile, addresses, last 20 rides, available promos cached locally with `drift`. TTL 24 h; serve cached on cold start while revalidating.
- **Write-side**: optimistic UI for non-critical mutations (rate ride, edit profile). Critical mutations (request ride, accept offer, complete trip) are **never** queued offline — UI shows a clear "connection lost" state.
- WS reconnect: exponential backoff (0.5 s → 30 s cap) with jitter. On reconnect, the ride tracking page re-fetches the canonical ride state, then resubscribes.

## 4.9 Push notifications

- FCM via `firebase_messaging`.
- iOS additionally uses APNs via FCM and **PushKit for driver incoming offers**, so a ride offer wakes the device even from killed state (driver app only).
- Notification categories with custom actions: `ride_offer` (Accept/Reject), `ride_arriving`, `ride_completed`.
- Deep links from notifications open the right route (`/offer/:ulid`, `/ride/:ulid`).

## 4.10 Testing

- **Unit tests** for every use case and Notifier in `domain` and feature folders.
- **Widget tests** for every reusable widget and at least the happy path of each page.
- **Integration tests** under `integration_test/`:
  - Customer: full auth → request → cancel flow against a mocked API.
  - Driver: auth → go online → accept offer → complete trip.
- **Golden tests** for theme correctness across light/dark and all three locales.
- CI runs `flutter analyze` (strict_lints), `flutter test`, integration tests on an Android emulator.

## 4.11 Build flavors

Three flavors per app: `dev`, `staging`, `prod`. Each has its own:

- Bundle id / package name (`ge.hangover.customer.dev`, …)
- App icon variant (dev = blue tint, staging = orange, prod = brand)
- API base URL
- Firebase project (separate FCM senders)
- Sentry DSN
- Maps API key

Entrypoints: `main_dev.dart`, `main_staging.dart`, `main_prod.dart` — each calls a shared `bootstrap(flavor)`.

## 4.12 Accessibility

- Minimum tappable target 48×48.
- Semantic labels on every icon-only button.
- Dynamic Type respected via `MediaQuery.textScaler`.
- Color contrast WCAG AA on both themes.
