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

## Driver iOS local/simulator debugging

Do not upload a new TestFlight build for every crash probe. Use local or simulator
builds first.

Diagnostic mode is selected with one variable:

```bash
EXPO_DRIVER_DIAGNOSTIC_MODE=full       # normal Driver V2 app
EXPO_DRIVER_DIAGNOSTIC_MODE=cleanroom  # boot-only View/Text app
EXPO_DRIVER_DIAGNOSTIC_MODE=null       # AppRegistry + return null
EXPO_DRIVER_DIAGNOSTIC_MODE=primitive  # staged RN primitives
EXPO_DRIVER_DIAGNOSTIC_MODE=staged     # lazy full-app import probes
```

The scripts mirror this into `EXPO_PUBLIC_DRIVER_DIAGNOSTIC_MODE`, which is the
value read by the JavaScript entrypoint in Expo/Metro bundles.

The Driver iOS simulator config uses:

- `EXPO_APP_TARGET=driver`
- `EXPO_PUBLIC_APP_ROLE=driver`
- `EXPO_PUBLIC_APP_NAME="Ride 360 Driver V2 Local"`
- `EXPO_PUBLIC_APP_ENV=development`
- `EXPO_PUBLIC_API_BASE_URL=https://ride.365sakartvelo.com`
- `newArchEnabled=false`

### A. Expo local dev

```bash
cd mobile-v2
EXPO_DRIVER_DIAGNOSTIC_MODE=full npm run start:driver:ios
```

Try isolation modes without editing files:

```bash
EXPO_DRIVER_DIAGNOSTIC_MODE=null npm run start:driver:ios
EXPO_DRIVER_DIAGNOSTIC_MODE=primitive npm run start:driver:ios
EXPO_DRIVER_DIAGNOSTIC_MODE=staged npm run start:driver:ios
```

On this machine, `npx expo start --ios` reached the simulator launcher but did
not open the app because the installed simulator Expo Go was `56.0.2` while SDK
55 requested Expo Go `55.0.34`; Expo CLI asked for interactive approval to
install the recommended Expo Go. Do not approve/install implicitly from scripts.

### B. Native iOS simulator build

```bash
cd mobile-v2
EXPO_DRIVER_DIAGNOSTIC_MODE=full npm run run:driver:ios
```

Release-like simulator build:

```bash
EXPO_DRIVER_DIAGNOSTIC_MODE=full npm run run:driver:ios:release
```

If CocoaPods is missing, stop and install it before continuing:

```bash
sudo gem install cocoapods
# or, if Homebrew is preferred and available:
brew install cocoapods
```

Do not install CocoaPods automatically in CI/debug scripts.

On this machine, CocoaPods is installed in the user gem directory. Use this
shell setup before local native runs:

```bash
export PATH="$HOME/.gem/ruby/2.6.0/bin:$PATH"
export RUBYOPT=-rlogger
```

If a generated `ios/` folder has stale native config, regenerate it with the
same Driver env before running:

```bash
EXPO_DRIVER_DIAGNOSTIC_MODE=full npx expo prebuild --platform ios --clean
```

### C. EAS iOS simulator build, no TestFlight

This creates a simulator artifact and does not upload to App Store Connect:

```bash
cd mobile-v2
EXPO_DRIVER_DIAGNOSTIC_MODE=primitive npx eas build \
  --platform ios \
  --profile driver-ios-simulator \
  --local
```

Cloud simulator build without local machine signing:

```bash
EXPO_DRIVER_DIAGNOSTIC_MODE=primitive npx eas build \
  --platform ios \
  --profile driver-ios-simulator \
  --non-interactive
```

### D. Inspect effective config

```bash
cd mobile-v2
EXPO_DRIVER_DIAGNOSTIC_MODE=full npm run config:driver > /tmp/driver-full-config.json
EXPO_DRIVER_DIAGNOSTIC_MODE=null npm run config:driver > /tmp/driver-null-config.json
EXPO_DRIVER_DIAGNOSTIC_MODE=primitive npm run config:driver > /tmp/driver-primitive-config.json
EXPO_DRIVER_DIAGNOSTIC_MODE=staged npm run config:driver > /tmp/driver-staged-config.json
```

Only upload another TestFlight build after the simulator path either reproduces
the crash or proves the issue is physical/TestFlight-only. TestFlight build
numbers must stay monotonic: `200022`, `200023`, `200024`, ...

## Validation

```bash
npm run typecheck
npm run lint
npm test
npm run check:production
```
