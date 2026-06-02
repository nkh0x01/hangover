# Ride 360 Expo V2 CI and iOS TestFlight

This setup is separate from the existing Flutter workflows. The Flutter apps,
current iOS signing scripts, Firebase/Sentry setup, Android release workflows,
and backend are not part of this V2 build path.

## Apps

| App | Workflow | App name | iOS bundle ID |
| --- | --- | --- | --- |
| Customer | `.github/workflows/expo-v2-customer-ios-testflight.yml` | Ride 360 Customer V2 | `app.ride360.customer` |
| Driver | `.github/workflows/expo-v2-driver-ios-testflight.yml` | Ride 360 Driver V2 | `app.ride360.driver` |

## CI Checks

`.github/workflows/expo-v2-ci.yml` runs on `mobile-v2/**` changes:

- `npm run lint`
- `npm run typecheck`
- `npm test`
- `npm run check:production`

The production check verifies app names, bundle IDs, app roles, production API
base URL, and scans V2 source files for accidental development URLs.

## API Environment

Production builds use:

```txt
EXPO_PUBLIC_API_BASE_URL=https://ride.365sakartvelo.com
EXPO_PUBLIC_APP_ENV=production
```

Staging can be added later by introducing staging EAS build profiles and setting
`EXPO_PUBLIC_API_BASE_URL` to the staging API host.

## Version and Build Numbers

V2 TestFlight workflows default to:

```txt
version: 2.0.0
build number: 200000 + GitHub run number
```

Manual workflow inputs may override both. Keep V2 build numbers in the 200000+
range to avoid colliding with current Flutter TestFlight builds.

## Required GitHub Secrets

Always required for EAS iOS builds:

```txt
EXPO_TOKEN
EXPO_CUSTOMER_EAS_PROJECT_ID
EXPO_DRIVER_EAS_PROJECT_ID
```

Required only when `submit_to_testflight=true`:

```txt
ASC_CUSTOMER_APP_ID
ASC_DRIVER_APP_ID
APPLE_TEAM_ID
```

App Store Connect API keys and iOS signing credentials should be configured in
EAS/Expo, not committed to this repository.

## Triggering Builds

1. Open GitHub Actions.
2. Select either `Expo V2 Customer iOS TestFlight` or `Expo V2 Driver iOS TestFlight`.
3. Run manually with:
   - `submit_to_testflight=false` for build-only validation and IPA artifact capture.
   - `submit_to_testflight=true` only when intentionally uploading to TestFlight.

The workflow summary reports the GitHub run URL, EAS build ID, bundle ID,
version/build, IPA artifact path, and TestFlight upload status.

## Risks

- These workflows use the existing App Store bundle IDs. Do not run with
  `submit_to_testflight=true` until the team is ready for V2 TestFlight builds.
- EAS projects must be initialized and linked before CI can build in
  non-interactive mode.
- If EAS managed credentials are not configured for each bundle ID, builds will
  fail before producing an IPA.
- Android profiles are intentionally omitted until Android release planning is
  requested.
