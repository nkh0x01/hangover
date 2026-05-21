# 06 — Authentication Flow

## 6.1 Methods supported

| Method | Audience | Notes |
|---|---|---|
| Phone + SMS OTP | Customer, Driver | Primary path |
| Google Sign-In | Customer, Driver | iOS + Android |
| Apple Sign-In | Customer, Driver | Required on iOS; offered everywhere |
| Email + password | Admin / Dispatcher only | Filament login, 2FA strongly enforced |

Drivers may **link** social accounts after signing up by phone, but the **primary identity** for a driver is always their phone number (regulatory + dispatching reasons).

## 6.2 Tokens

We use **Laravel Sanctum personal access tokens**, but with three project-specific rules:

1. Tokens are **device-bound**. A token row stores `device_uuid` in its `name` field (`pat:{platform}:{device_uuid}`); a token only authenticates a request whose `X-Device-Id` matches.
2. Tokens carry **abilities** that scope them. Issued abilities:
   - `customer` — customer app token
   - `driver` — driver app token (only if `users.type='driver'`)
   - `driver:onboarding` — limited token for unapproved drivers (only docs + onboarding endpoints)
   - `admin` — admin (rare; admin mostly uses session cookies via Filament)
3. Tokens **expire**. Sanctum supports `expiration` in `config/sanctum.php`. We set:
   - access token TTL = **30 days** (mobile app stays logged in across normal sessions)
   - refresh available indefinitely while the device row is `revoked_at IS NULL` — but each refresh **rotates** the token (revokes the old one) and bumps `last_active_at`.

`POST /auth/refresh` is the only way to get a new token; passing an expired token to any other endpoint returns `auth.token_expired`.

We deliberately avoid JWT: no inherent revocation, mismatched with Sanctum, no benefit in a single-cluster monolith.

## 6.3 Phone OTP flow

### 6.3.1 Sequence

```
Mobile                       API                        SMS Provider
  │  POST /auth/otp/request                                 │
  │     { phone, purpose }                                  │
  │ ───────────────────────────► validate, normalize        │
  │                              check rate limits           │
  │                              insert phone_verifications  │
  │                              dispatch SendOtpJob ────────► send SMS
  │ ◄─────────────────────────── 202 { resend_after: 60 }    │
  │                                                          │
  │  POST /auth/otp/verify                                   │
  │     { phone, code, device_uuid, platform, app_version }  │
  │ ───────────────────────────► verify code (bcrypt)        │
  │                              find_or_create user          │
  │                              upsert user_devices          │
  │                              issue Sanctum token          │
  │                              attach FCM placeholder       │
  │ ◄─────────────────────────── 200 { token, user, abilities, expires_at } │
```

### 6.3.2 OTP details

- Code: 6 digits, generated with `random_int(100000, 999999)`, **never logged**.
- Storage: bcrypt hash in `phone_verifications.code_hash`.
- TTL: 5 minutes.
- Attempts: max 5 per `phone_verifications` row; further attempts → `auth.invalid_otp` with `code = 'locked'`.
- Resend: 60 s cooldown for the first resend, doubling up to 8 min, reset after successful verify.
- Rate limit (see [05 §rate limits](05-api-routes.md#rate-limits)).
- Provider abstraction:
  - `App\Modules\Communication\Contracts\SmsGateway`
  - Implementations: `TwilioSms`, `LocalGeProviderSms`. Selected via `config('sms.driver')`.
  - Each provider has a delivery-receipt webhook updating `sms_log.status`.

### 6.3.3 First-time signup

On verify, if no `users` row matches the phone:
1. Create `users` with `type='customer'` (driver onboarding uses a different endpoint to mark `type='driver'`), `phone_e164`, `phone_verified_at = now()`, `locale = Accept-Language ?: 'ka'`, `referral_code = ulid()[0..8]`.
2. The client follows up with `PATCH /customer/me` to set first/last name.

For the **driver** app, the signup endpoint is `POST /auth/otp/verify` with `purpose=driver_signup`. The user is created with `type='driver'` and a paired `drivers` row with `status='pending'`. The issued token has ability `driver:onboarding` only. After admin approval, the next refresh upgrades the token to `driver`.

## 6.4 Google Sign-In

1. Mobile app obtains a Google **ID token** via `google_sign_in` (Customer and Driver clients use distinct OAuth client IDs).
2. `POST /auth/oauth/google` with `{ id_token, device_uuid, platform, app_version, purpose }`.
3. Server validates with Google's tokeninfo endpoint (cached JWKS, signature + audience + `exp`).
4. Lookup:
   - `user_oauth_identities` for `(google, sub)` → existing user.
   - If absent, lookup `users` by verified `email`. Match → link identity. No match → create new user (`type` per `purpose`).
5. Email is set `email_verified_at = now()` only if Google asserts `email_verified = true`.
6. **Phone number is still required for ride functionality.** A Google-only user can browse and complete profile but cannot request a ride until phone is verified — `phone_verified_at` is null → API returns `auth.phone_required` on protected ride endpoints. The mobile app routes them through a phone-add step.

## 6.5 Apple Sign-In

Symmetric to Google but:
- Validates against Apple's JWKS (`https://appleid.apple.com/auth/keys`).
- First-call payload may include the user's name (only first time per Apple's design); we cache it server-side keyed by `sub`, applied at user creation.
- Apple's private-relay email is stored as-is; we don't treat it as authoritative for communication — phone remains primary.

## 6.6 Admin login

- Filament login screen at `/admin`.
- Session cookies, **not** Sanctum.
- Required: 2FA via TOTP (`filament/google-2fa` or `pragmarx/google2fa-laravel`). New admin: first login forces enrollment.
- IP allowlist optional (configurable per environment).
- Admin session TTL 8 h, idle timeout 30 min.
- All admin mutations logged via Spatie activitylog with `ip`, `user_agent`, `request_id`.

## 6.7 Refresh flow

```
Mobile                         API
  │  POST /auth/refresh
  │     X-Device-Id: <uuid>
  │     Authorization: Bearer <old_token>
  │ ────────────────────────────►  match device
  │                                check token unexpired or within 7-day grace
  │                                rotate: create new PAT, delete old
  │                                update user_devices.last_active_at
  │ ◄──────────────────────────── 200 { token, expires_at }
```

If the old token is in the **grace window** (expired ≤ 7 days, device not revoked), refresh still succeeds — this rescues users who reopened the app after a long pause. Past grace, refresh fails and the app falls back to OTP login.

## 6.8 Logout / revocation

- `POST /auth/logout` → revoke current PAT, mark device `revoked_at`.
- `DELETE /me/devices/{id}` → revoke a specific other device (used in security settings).
- Admin can revoke all devices for a user via `POST /admin/users/{ulid}/revoke-sessions`.
- Server emits `auth.token_revoked` events; subscribed WS channels drop the connection.

## 6.9 Sensitive operations: step-up

Some actions require a re-verification within the last 5 min:
- Removing a payment method
- Changing phone number
- Adding/changing bank withdrawal IBAN (driver)
- Account deletion

Re-verification: server issues a one-time challenge; client posts OTP to `/auth/step-up/verify`; success grants a 5-minute step-up flag on the token (Redis `stepup:{token_id}` TTL).

## 6.10 RBAC

| Role | App | Abilities |
|---|---|---|
| `customer` | Mobile (customer) | `customer` |
| `driver` (approved) | Mobile (driver) | `driver` |
| `driver` (pending) | Mobile (driver) | `driver:onboarding` |
| `super_admin` | Admin panel | wildcard |
| `ops_admin` | Admin panel | `driver.*`, `ride.*`, `support.*`, `livemap.view` |
| `finance_admin` | Admin panel | `payout.*`, `refund.*`, `transaction.view` |
| `support_agent` | Admin panel | `support.*`, `user.view`, `ride.view` |
| `dispatcher` | Admin panel + dispatch tool | `ride.view`, `ride.dispatch`, `livemap.view` |

Permissions are enforced through Spatie's `permission:` middleware (`Route::middleware('permission:ride.cancel')`) and matching Filament policy classes.

## 6.11 Security details

- All tokens stored in mobile **secure storage** (Keychain / Keystore via `flutter_secure_storage`).
- Tokens never appear in URLs, never in logs.
- 401 from API triggers a single refresh attempt; on second 401 the client clears storage and routes to the auth screen.
- Server-side, every authenticated request triggers an async update of `user_devices.last_active_at` (debounced per device to once per minute, written via Redis flag).
- Suspicious-login detection (Phase 4+): geolocation jump > 500 km within 10 min between two devices → fraud_flag `multi_account` raised; user prompted to re-verify on next sensitive op.
- Brute-force protection on `/auth/otp/verify`: 5 wrong attempts → invalidate the record + cooldown.

## 6.12 First-launch decision tree (mobile)

```
launch
  ├── no token?              → /auth/phone
  ├── token but expired:
  │     ├── grace?           → silent /auth/refresh → home
  │     └── beyond grace     → /auth/phone (pre-fill phone)
  └── token valid:
        ├── customer         → /home
        └── driver:
              ├── pending    → /onboarding/pending
              └── approved   → /home
```
