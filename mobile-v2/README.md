# Ride 360 Expo V2

Initial Expo / React Native TypeScript workspace for the Ride 360 Customer and Driver V2 apps.

This workspace is intentionally separate from the existing Flutter `mobile/` directory. It does not change the backend, current production apps, signing workflows, Android workflows, Firebase, or Sentry.

## Layout

```txt
mobile-v2/
  apps/
    customer/
    driver/
  packages/
    api/
    auth/
    ui/
    i18n/
    diagnostics/
    config/
    types/
```

## Setup

```bash
cd mobile-v2
npm install
```

## Run

Customer app:

```bash
npm run start:customer
```

Driver app:

```bash
npm run start:driver
```

Each app accepts the same public environment values:

```bash
EXPO_PUBLIC_API_BASE_URL=https://ride.365sakartvelo.com
EXPO_PUBLIC_APP_ENV=staging
```

## Validation

```bash
npm run typecheck
npm run lint
```
