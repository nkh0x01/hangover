# Ride 360 iOS Xcode Cloud Release Builds

This Xcode Cloud path is preserved as a legacy/alternative workflow. The active no-local-Xcode iOS release workflow is now GitHub Actions:

- `docs/deployment/ride360-ios-github-actions.md`
- `.github/workflows/ios-customer-testflight.yml`
- `.github/workflows/ios-driver-testflight.yml`

The rest of this document is retained for reference if Xcode Cloud is re-enabled later.

Ride 360 iOS releases can be built by Xcode Cloud in App Store Connect. Local Xcode archive builds are not required.

## Apps

- Ride 360 Customer
  - Bundle ID: `app.ride360.customer`
  - Workspace: `mobile/apps/customer_app/ios/Runner.xcworkspace`
  - Scheme: `Runner`
- Ride 360 Driver
  - Bundle ID: `app.ride360.driver`
  - Workspace: `mobile/apps/driver_app/ios/Runner.xcworkspace`
  - Scheme: `Runner`

Both app targets use automatic signing with Apple Team ID `5BB9G38XX8`.

## Repository Scripts

Each app has Xcode Cloud scripts next to its selected workspace:

- `mobile/apps/customer_app/ios/ci_scripts/ci_post_clone.sh`
- `mobile/apps/customer_app/ios/ci_scripts/ci_pre_xcodebuild.sh`
- `mobile/apps/customer_app/ios/ci_scripts/ci_post_xcodebuild.sh`
- `mobile/apps/driver_app/ios/ci_scripts/ci_post_clone.sh`
- `mobile/apps/driver_app/ios/ci_scripts/ci_pre_xcodebuild.sh`
- `mobile/apps/driver_app/ios/ci_scripts/ci_post_xcodebuild.sh`

The wrappers call `mobile/scripts/xcode_cloud/ride360_xcode_cloud.sh`. Each `ci_scripts` directory also contains a relative helper symlink so Xcode Cloud can resolve the shared script from the selected workspace's script directory.

The scripts:

- install Flutter on the Xcode Cloud runner if needed;
- run `flutter pub get`;
- run `flutter build ios --release --config-only`;
- install CocoaPods;
- inject `IOS_MAPS_API_KEY` into `Runner/Info.plist`;
- disable `sentry_flutter` and use an API-compatible no-op crash reporter for the Xcode Cloud iOS archive, avoiding the `sentry-cocoa` Swift Package Manager dependency;
- force production Dart defines:
  - `API_BASE_URL=https://ride.365sakartvelo.com`
  - `RIDE360_RELEASE_BUILD=true`
  - `DEV_BYPASS_ENABLED=false`
- reject localhost, `10.0.2.2`, staging, and `/api/v1` API strings in archived output when an archive path is available.

## Required App Store Connect Setup

Apple Developer Team:

- Team ID: `5BB9G38XX8`

Bundle IDs:

- `app.ride360.customer`
- `app.ride360.driver`

App records:

- Create or verify a separate App Store Connect app record for Ride 360 Customer.
- Create or verify a separate App Store Connect app record for Ride 360 Driver.

No App Store Connect API key, Issuer ID, Key ID, or `.p8` file is needed for this workflow because Xcode Cloud runs inside App Store Connect.

## Xcode Cloud Workflow: Customer

In App Store Connect:

1. Open **My Apps**.
2. Select **Ride 360 Customer**.
3. Open **Xcode Cloud**.
4. Create a new workflow.
5. Select the Git repository for this project.
6. Select the branch, for example `master` or your release branch.
7. Select workspace:

   `mobile/apps/customer_app/ios/Runner.xcworkspace`

8. Select scheme:

   `Runner`

9. Add an **Archive** action for iOS.
10. Set distribution to **TestFlight**.
11. Add environment variables:

    - `IOS_MAPS_API_KEY` secret, required
    - `API_BASE_URL=https://ride.365sakartvelo.com`
    - `RIDE360_RELEASE_BUILD=true`
    - `DEV_BYPASS_ENABLED=false`
    - `APPLE_TEAM_ID=5BB9G38XX8`
    - `FLUTTER_VERSION=stable`

Optional:

- `IOS_VERSION_NAME=0.1.0`
- `IOS_BUILD_NUMBER=<integer>`
- `WS_KEY=<production websocket key>`

If `IOS_BUILD_NUMBER` is omitted, the script uses Xcode Cloud's `CI_BUILD_NUMBER`, then the build number from `pubspec.yaml`.

## Xcode Cloud Workflow: Driver

Repeat the same setup in the **Ride 360 Driver** App Store Connect app record.

Use:

- Workspace: `mobile/apps/driver_app/ios/Runner.xcworkspace`
- Scheme: `Runner`
- Bundle ID: `app.ride360.driver`
- Distribution: **TestFlight**

Use the same environment variables. The script detects the driver workflow from the `ios/ci_scripts` wrapper in the driver app.

## TestFlight Upload

Xcode Cloud performs the archive and distribution inside App Store Connect:

1. Start the Customer workflow.
2. Start the Driver workflow.
3. Wait for the Archive action to pass.
4. Open each app's **TestFlight** tab.
5. Wait for Apple processing.
6. Add the processed build to internal testers.
7. For external testers, complete beta review information and submit for beta review.
8. For App Store release, complete metadata, screenshots, privacy details, and submit the selected build for review.

## Maps

The `IOS_MAPS_API_KEY` value must be a Google Maps iOS key that allows both bundle IDs:

- `app.ride360.customer`
- `app.ride360.driver`

Use separate keys if the Google Cloud project policy requires one key per bundle ID.

## Codemagic

Codemagic is not part of the active Ride 360 iOS release workflow. The Codemagic config and script have been removed so the iOS release path is App Store Connect/Xcode Cloud only.

## References

- Apple Xcode Cloud custom scripts: https://developer.apple.com/documentation/xcode/writing-custom-build-scripts
- Apple Xcode Cloud environment variables: https://developer.apple.com/documentation/xcode/environment-variable-reference
- Flutter continuous delivery with Xcode Cloud: https://docs.flutter.dev/deployment/cd
