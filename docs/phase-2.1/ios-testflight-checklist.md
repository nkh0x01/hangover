# Hangover Mobile — iOS / TestFlight Setup Checklist

> Phase 2.1 deliverable. Pre-flight items the team needs to clear
> before the first TestFlight build can land in front of a real iOS
> tester. Owned by whoever holds the Apple Developer account.

## Apple account prerequisites

- [ ] **Apple Developer Program enrollment** ($99 / year)
  enrolled and renewed for ≥ 6 months.
- [ ] **Account holder** has accepted the latest Apple agreements
  in App Store Connect → Agreements, Tax, and Banking.
- [ ] Tax + banking forms submitted (otherwise paid apps are blocked
  — irrelevant for free apps but TestFlight is gated on the agreement).
- [ ] At least one **App Manager** admin has been added in
  App Store Connect so the account holder isn't a single point of
  failure.

## Bundle identifiers

Register the four bundle ids in
**Certificates, Identifiers & Profiles → Identifiers → App IDs**:

| Bundle id                            | App         | Flavor / build  |
|--------------------------------------|-------------|-----------------|
| `app.hangover.customer.dev`          | customer    | dev / sandbox   |
| `app.hangover.customer`              | customer    | prod            |
| `app.hangover.driver.dev`            | driver      | dev / sandbox   |
| `app.hangover.driver`                | driver      | prod            |

Capabilities to enable on each App ID:

- [x] Push Notifications (APNs)
- [x] Background Modes
  - [x] Remote notifications
  - [x] Location updates (**driver only**)
  - [x] Audio (**driver only** — incoming-offer sound while screen off)
  - [x] Background fetch
- [x] Associated Domains (for universal-link OTP shortcuts)
- [x] Maps
- [ ] Sign in with Apple — Phase 2.2

## APNs key

- [ ] Create one **APNs Auth Key** (not certificate) under
  Identifiers → Keys → "+". Download the `.p8` (Apple only lets you
  download it once — save it to the team password manager
  *immediately*).
- [ ] Note the **Key ID** + **Team ID**.
- [ ] Upload the `.p8` to **each Firebase project** that drives an
  iOS build: Firebase Console → Project Settings → Cloud Messaging →
  *Apple app configuration* → APNs Authentication Key.

A single APNs key covers every iOS bundle id under the same team —
you don't need one per app.

## Provisioning profiles

For each bundle id register **two** profiles:

1. **Development** — covers `flutter run` / TestFlight internal QA.
2. **App Store** — covers TestFlight external testers + production.

Once Xcode is set to "Automatically manage signing" with the team
selected, these regenerate on demand and you don't need to touch
them manually. The build system needs:

- [ ] `App Store Connect API key` (.p8) created under Users and Access
  → Keys (used by `fastlane match`, GitHub Actions, or Xcode Cloud
  for automated uploads).

## App Store Connect listing scaffolding

Create the App records in App Store Connect:

- [ ] Customer app:
  - Name: **Hangover**
  - Primary language: English (U.S.)
  - Bundle ID: `app.hangover.customer`
  - SKU: `hangover.customer`
  - User Access: Full access
- [ ] Driver app:
  - Name: **Hangover Driver**
  - Bundle ID: `app.hangover.driver`
  - SKU: `hangover.driver`

Each app needs the following metadata stubbed before TestFlight can go
to external review (internal TestFlight works without them):

- [ ] Subtitle (30 chars)
- [ ] Promotional text (170 chars)
- [ ] Description
- [ ] Keywords (100 chars)
- [ ] Support URL  → `https://hangover.app/support`
- [ ] Marketing URL → `https://hangover.app`
- [ ] Privacy policy URL → `https://hangover.app/legal/privacy`
- [ ] Screenshots: 6.7" iPhone (1290 × 2796) — minimum 3, recommend 5.
  Reuse the source HTML in `docs/screenshots/source/` rendered at
  iPhone aspect ratio.
- [ ] Category: Travel (primary), Navigation (secondary)
- [ ] Age rating questionnaire completed
- [ ] App privacy: declare every data type the app collects.
  See `docs/architecture/security-and-privacy.md` for the canonical
  list — phone, location (precise + background for driver), device
  identifiers, crash diagnostics.

## Xcode project — manual settings

The Flutter `flutter create` scaffold needs the following one-time
edits inside Xcode for each app. The template overlays in
`mobile/templates/ios/<app>/` give you the values:

1. Open `mobile/apps/<app>/ios/Runner.xcworkspace`.
2. Select the *Runner* target → *Signing & Capabilities*:
   - Team: select your Apple Developer team.
   - Bundle Identifier: set to one of the four bundle ids.
   - Provisioning profile: Automatic.
   - Add capabilities (use the `+ Capability` button):
     - Push Notifications
     - Background Modes (tick the modes from the table above)
     - Associated Domains (`applinks:hangover.app`)
3. Open `Info.plist`, `cmd-A` → switch to *Source* mode. Paste the
   `<key>…</key>/<value>` blocks from
   `mobile/templates/ios/<app>/Info.plist.additions` between the outer
   `<dict>` tags. Save.
4. Drag-and-drop `mobile/templates/ios/<app>/Runner.entitlements` onto
   *Runner → Runner* in the project navigator. In *Build Settings*,
   set `CODE_SIGN_ENTITLEMENTS = Runner/Runner.entitlements`.
5. Append the contents of `mobile/templates/ios/shared/Podfile.additions`
   to `ios/Podfile` (mostly the post-install hook + permission_handler
   flags). Then:
   ```bash
   cd mobile/apps/<app>/ios
   pod install
   ```

After this, `flutter run` and `flutter build ipa --release` both work.

## First TestFlight upload

```bash
cd mobile/apps/customer_app
flutter clean
flutter pub get
flutter build ipa \
  --release \
  --flavor=prod \
  --target=lib/main_prod.dart \
  --export-options-plist=ios/ExportOptions.plist
```

`ExportOptions.plist` — create at `mobile/apps/<app>/ios/`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>method</key>           <string>app-store</string>
    <key>teamID</key>           <string>YOUR_TEAM_ID</string>
    <key>uploadBitcode</key>    <false/>
    <key>uploadSymbols</key>    <true/>
    <key>compileBitcode</key>   <false/>
    <key>destination</key>      <string>export</string>
</dict>
</plist>
```

Then upload the IPA:

```bash
xcrun altool --upload-app \
  -f build/ios/ipa/customer_app.ipa \
  -t ios \
  --apiKey YOUR_KEY_ID \
  --apiIssuer YOUR_ISSUER_ID
```

(Or use Transporter.app on macOS for the GUI flow.)

Apple takes 5-30 min to process. When it shows up in
App Store Connect → TestFlight → iOS Builds, add the build to the
**Internal testing** group so QA can install via TestFlight.

## TestFlight tester groups

- **Internal** (up to 100 users from the App Store Connect team):
  - Engineering
  - QA
  - Product
  No external review required.

- **External** (up to 10 000 users via invite link or email):
  - First wave: 10–20 friends-and-family riders + 2–3 drivers.
  - Requires App Store review (~24 h). Do not enable until the privacy
    declarations + screenshots are complete.

## Pre-flight checklist

Cleared before the first TestFlight build goes out:

- [ ] iOS minimum deployment target: **13.0** (matches `Podfile`)
- [ ] App Transport Security: HTTPS-only, no `NSAllowsArbitraryLoads`
- [ ] Permission strings present + reviewed (location, camera,
  notifications)
- [ ] APNs key uploaded to Firebase
- [ ] Push token round-trip tested via the staging backend
- [ ] Background location prompt fires AFTER the driver toggles
  "Online" (never on first launch — Apple will reject)
- [ ] Crash symbols (dSYM) auto-uploaded to Sentry
- [ ] Build number bumped (`+1` in `pubspec.yaml`)
- [ ] App Store Connect "App Privacy" section filled in
- [ ] Beta App Description + Test Information completed

## Known iOS pain points

- **APNs sandbox vs production**: dev builds use the sandbox APNs
  environment; TestFlight + App Store builds use production. If
  pushes work on `flutter run` but not on TestFlight, you've left the
  entitlement on `development` — change `Runner.entitlements`:
  ```xml
  <key>aps-environment</key>
  <string>production</string>
  ```
- **Background location prompts only show up to twice** — the second
  time the user must visit Settings manually. If our onboarding flow
  asks too eagerly we burn the prompt. Defer the request until the
  driver flips "Online" for the first time.
- **App Tracking Transparency**: not currently required (we don't
  cross-app track), but if Phase 3 introduces an analytics SDK that
  uses IDFA, we'll need to add `NSUserTrackingUsageDescription` and
  the ATT prompt before the first network call.

## Open items / Phase 2.2

- Apple Sign-In (`socialiteproviders/apple` is already in
  `composer.json`, the backend route ships in Phase 2.2).
- CarPlay extension for the driver app (Phase 3).
- Live Activities for the active-ride pill (Phase 3).
