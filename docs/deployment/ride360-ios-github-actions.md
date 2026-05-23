# Ride 360 iOS GitHub Actions Release Builds

Ride 360 iOS releases are built in GitHub Actions on a hosted macOS runner. This workflow does not require local Xcode, Xcode Cloud, Codemagic, Expo, or React Native.

## Workflows

- **Build Customer iOS**
  - App path: `mobile/apps/customer_app`
  - Entry point: `lib/main_prod.dart`
  - Bundle ID: `app.ride360.customer`
  - Output artifact: `Ride360-Customer-v<version>-<build>.ipa`
- **Build Driver iOS**
  - App path: `mobile/apps/driver_app`
  - Entry point: `lib/main_prod.dart`
  - Bundle ID: `app.ride360.driver`
  - Output artifact: `Ride360-Driver-v<version>-<build>.ipa`

Both workflows build production IPAs and upload them to TestFlight automatically.

## Production Configuration

The workflows force these values:

- `API_BASE_URL=https://ride.365sakartvelo.com`
- `RIDE360_RELEASE_BUILD=true`
- `DEV_BYPASS_ENABLED=false`
- `APP_ENV=production`
- `SENTRY_DSN=`

The helper script also rejects final IPA strings containing localhost, `10.0.2.2`, staging API hosts, or `https://ride.365sakartvelo.com/api/v1`.

## Required GitHub Secrets

Create these repository secrets in GitHub:

- `APP_STORE_CONNECT_ISSUER_ID`
  - Value: `c31a4d8b-f081-4263-be24-796d00558ddd`
- `APP_STORE_CONNECT_KEY_ID`
  - Value: `VWBCPRZ4JS`
- `APP_STORE_CONNECT_PRIVATE_KEY`
  - Value: the full contents of `AuthKey_VWBCPRZ4JS.p8`
  - Multiline PEM text is supported. Text with escaped `\n` line breaks is also supported.
- `APPLE_TEAM_ID`
  - Value: `5BB9G38XX8`
- `IOS_MAPS_API_KEY`
  - Value: the production Google Maps iOS API key.

The App Store Connect API key must have access to both Ride 360 apps and enough permission to manage signing and upload TestFlight builds, typically App Manager or Admin.

## Apple Setup

Verify these identifiers exist in Apple Developer and App Store Connect:

- `app.ride360.customer`
- `app.ride360.driver`

Create one App Store Connect app record for Ride 360 Customer and one for Ride 360 Driver. Both app records must be connected to Apple Team ID `5BB9G38XX8`.

The workflow uses automatic signing with:

- `xcodebuild -allowProvisioningUpdates`
- App Store Connect API key authentication
- Team ID `5BB9G38XX8`

If Apple blocks automatic certificate/profile creation for the API key, create the iOS Distribution signing assets in the Apple Developer portal and retry. The workflow is intentionally written so that no local Mac signing setup is needed.

## Google Maps

The `IOS_MAPS_API_KEY` value must allow these iOS bundle IDs:

- `app.ride360.customer`
- `app.ride360.driver`

Use separate Google Maps keys only if your Google Cloud policy requires one key per bundle ID.

## Crash Reporting

Remote crash reporting is disabled in committed source until the iOS package resolution issue is solved. The core package keeps the same `CrashReporter` public API with a no-op implementation.

This does not change app logic in the repository and does not affect Android builds.

## Run A Release

1. Push the release branch to GitHub.
2. Open the repository in GitHub.
3. Go to **Actions**.
4. Select **Build Customer iOS**.
5. Click **Run workflow**.
6. Select the branch, normally `master`.
7. Optionally enter `version_name` and `build_number`.
8. Run the workflow.
9. Repeat with **Build Driver iOS**.

When each workflow finishes, GitHub keeps the IPA and SHA256 file as workflow artifacts. The same IPA is uploaded to TestFlight automatically.

## TestFlight

After a successful upload:

1. Open App Store Connect.
2. Select the matching app.
3. Open **TestFlight**.
4. Wait for Apple processing.
5. Add the processed build to internal testers.
6. For external testers, complete beta review information and submit for beta review.

## Files

- `.github/workflows/ios-customer-testflight.yml`
- `.github/workflows/ios-driver-testflight.yml`
- `mobile/scripts/github_actions/build_ios_testflight.sh`
