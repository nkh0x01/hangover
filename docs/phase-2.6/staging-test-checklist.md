# Phase 2.6 staging test checklist

Run these against the installed staging-debug APKs on a real Android
phone. Pass each box as a tester / sign-off. Skipping items is fine
when noted.

## Pre-flight (on your Mac)

- [ ] `./mobile/scripts/check-mac-setup.sh` exits 0
- [ ] `./mobile/scripts/staging-smoke.sh` exits 0
      (proves `https://ride.365sakartvelo.com` is reachable + magic OTP
      is honoured server-side)
- [ ] `./mobile/scripts/build-debug-apk.sh both` produces both APKs
      in `build/`

## Install

- [ ] Both APKs install on the phone (`adb install -r ...` returns
      `Success` for each)
- [ ] **Hangover Stg** and **Hangover Driver Stg** icons appear in
      the launcher (placeholder emerald + white-ring)
- [ ] App opens past the splash within ~2 s; lands on the phone screen

## Customer app — sign in via real backend

- [ ] Type `+995555000` followed by any 3 digits → **Send code**
- [ ] Spinner shows for ~200 ms, no red error message
- [ ] OTP screen displays the yellow **Dev OTP · staging: 111111**
      banner
- [ ] Tap **Fill** → digits populate
- [ ] Tap **Verify** → lands on the home map
- [ ] Map shows either (a) real Google tiles (if `MAPS_API_KEY` was
      set at build time) or (b) the FallbackMapProvider green grid
      with the "Preview map · live tiles need MAPS_API_KEY" pill

## Customer app — demo preview (in the same APK)

- [ ] Sign out (long-press app icon → App info → Force stop, or
      uninstall + reinstall)
- [ ] Open the app, on the phone screen tap **Preview app
      (no backend)** — visible because we're staging, not prod
- [ ] Top-of-screen floating **Preview · Home** pill renders
- [ ] Tap **Next** 8× to walk through:
      Home → Fare estimate → Searching → Driver assigned →
      Driver en route → Driver arrived → In progress → Trip
      completed
- [ ] Tap **Restart** → bounces back to home
- [ ] Tap **Exit** → back at the phone-input screen

## Driver app — sign in

- [ ] Type `+995555111` + 3 random digits → **Send code**
- [ ] Dev OTP banner shows `111111` → **Fill** → **Verify**
- [ ] Lands on driver home with the **Offline** toggle
- [ ] Tap **Go online** — this may surface a server-side error if no
      driver profile exists for the phone number; that is **data**, not
      a build issue. Move on.

## Driver app — demo preview

- [ ] On the driver phone screen tap **Preview app (no backend)**
- [ ] Top-of-screen pill renders: **Preview · Offline**
- [ ] Tap Next through all 7 driver stages
- [ ] No crashes, no permission prompts

## Permissions

- [ ] First time the map renders, Android prompts for **location
      permission** — accept it (or deny; both should not crash)
- [ ] Android 13+ phones prompt for **notifications permission** on
      first launch — accept or deny

## Network resilience

- [ ] Enable airplane mode → tap **Send code** on the phone page
- [ ] App surfaces a sensible "Network error" message (not a crash)
- [ ] Disable airplane mode → retry → succeeds

## Tear-down (after sign-off)

- [ ] `adb uninstall app.hangover.customer.staging.debug`
- [ ] `adb uninstall app.hangover.driver.staging.debug`

## Acceptance

Phase 2.6 is "ready for stakeholder demo" the day every box above
is green on at least one physical Android phone.

If the **Customer sign-in** or the **Demo preview** flow fails, file
a blocker. Everything else under known limitations
([README.md](README.md)) is acceptable for Phase 2.6.
