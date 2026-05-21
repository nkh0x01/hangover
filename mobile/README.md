# Hangover Mobile Monorepo

Flutter monorepo for the customer and driver apps. Managed by [Melos](https://melos.invertase.dev/).

## Layout

```
mobile/
├── apps/
│   ├── customer_app/      ge.hangover.customer
│   └── driver_app/        ge.hangover.driver
├── packages/
│   ├── core/              theme, widgets, app shell
│   ├── network/           Dio + interceptors
│   ├── auth/              phone-OTP, OAuth, token store
│   ├── maps/              MapProvider abstraction
│   ├── realtime/          Reverb/Pusher WS client
│   ├── ui_kit/            shared widgets
│   └── localization/      i18n helpers
├── melos.yaml
└── pubspec.yaml
```

## Bootstrap

```bash
dart pub global activate melos 6.0.0
melos bootstrap
melos run gen          # codegen for freezed / json_serializable
melos run analyze
melos run test
```

## Per-app run

```bash
cd apps/customer_app
flutter run --flavor dev -t lib/main_dev.dart
```

Flavors: `dev`, `staging`, `prod`. Each has its own bundle id, icon
variant, Firebase project, Sentry DSN, and `API_BASE_URL`.
