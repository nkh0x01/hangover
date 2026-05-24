# `.env` Production Template

> Drop into `/var/www/hangover/shared/.env` on the VPS. Every line
> below is annotated with where the value comes from and whether
> pilot needs it. Pilot-required lines are tagged `[REQUIRED]`;
> placeholders are tagged `[LATER]` (Phase 2.5 onward).
>
> Generate each `*_KEY` / `*_SECRET` / `*_PASSWORD` from a password
> manager — never hand-type them. The full app key is set once via
> `php artisan key:generate`.

```ini
# ─── Core ───────────────────────────────────────────────────────────────
APP_NAME="Hangover"
APP_ENV=production
APP_KEY=                              # set by `php artisan key:generate`
APP_DEBUG=false
APP_URL=https://ride.365sakartvelo.com
APP_TIMEZONE=Asia/Tbilisi
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

# ─── Logging ───────────────────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null

# Daily-rotated files live in storage/logs/. The dispatch/realtime/
# push/security/payment channels are declared in config/logging.php.

# ─── Database ──────────────────────────────────────────────────────────
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hangover
DB_USERNAME=hangover
DB_PASSWORD=                          # from MySQL bring-up (vps-bring-up.md §7)
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

# Use a read-only replica once we have one. For pilot the writer
# serves reads too.

# ─── Cache + Sessions ──────────────────────────────────────────────────
CACHE_STORE=redis
CACHE_PREFIX=hangover-cache
SESSION_DRIVER=redis
SESSION_LIFETIME=480                  # admin panel auto-logout in 8 hours
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=ride.365sakartvelo.com

# ─── Queues ────────────────────────────────────────────────────────────
QUEUE_CONNECTION=redis
REDIS_QUEUE=default
HORIZON_PREFIX=hangover-horizon

# ─── Redis ─────────────────────────────────────────────────────────────
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=                       # from Redis bring-up (vps-bring-up.md §8)
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_PREFIX=hangover:

# ─── Realtime broker (Laravel Reverb) ──────────────────────────────────
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=hangover
REVERB_APP_KEY=                       # 32-char random; expose to mobile
REVERB_APP_SECRET=                    # 32-char random; backend-only
REVERB_HOST=ride.365sakartvelo.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_ALLOWED_ORIGINS=https://ride.365sakartvelo.com,*

# Mobile clients connect to wss://ride.365sakartvelo.com/app/<REVERB_APP_KEY>
# via the nginx upstream. The internal Reverb daemon binds 127.0.0.1:8080.

# ─── Sanctum + CORS ────────────────────────────────────────────────────
SANCTUM_STATEFUL_DOMAINS=ride.365sakartvelo.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax

CORS_ALLOWED_ORIGINS=https://ride.365sakartvelo.com
CORS_ALLOWED_HEADERS=*
CORS_EXPOSED_HEADERS=X-Request-Id

# ─── Filesystem ────────────────────────────────────────────────────────
FILESYSTEM_DISK=local
# Phase 3 swaps this to s3-compatible for driver-document storage.

# ─── Mail ──────────────────────────────────────────────────────────────
MAIL_MAILER=log                       # [LATER] swap to smtp/postmark/sendgrid
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@ride.365sakartvelo.com"
MAIL_FROM_NAME="${APP_NAME}"

# ─── SMS / OTP (sender.ge) ─────────────────────────────────────────────
SMS_DRIVER=sender_ge                  # [REQUIRED for prod]
SENDER_GE_API_KEY=                    # [REQUIRED] set only in server env
SENDER_GE_SENDER=Ride360              # [REQUIRED] approved sender.ge name
SENDER_GE_BASE_URL=https://sender.ge/api/send.php

# Legacy Twilio fallback; not used by the Ride 360 production lane.
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM_NUMBER=+995300000000

# ─── Pilot config (Phase 2.2) ──────────────────────────────────────────
PILOT_ENABLED=true
PILOT_COHORT=tbilisi-w1
PILOT_TEST_PHONES=+995599000001,+995599000002,+995599000003
PILOT_MIN_DRIVERS=3
PILOT_MAX_NO_DRIVERS_PER_HOUR=5
PILOT_MAX_CANCEL_RATE=0.20
PILOT_SERVICE_OPEN=07:00
PILOT_SERVICE_CLOSE=23:00

# ─── Commission + payment (Phase 2.3) ──────────────────────────────────
LEDGER_CURRENCY=GEL
COMMISSION_DEFAULT_RATE=0.15
COMMISSION_MIN_AMOUNT=0.10
COMMISSION_MAX_AMOUNT=50.00

PAYMENT_DEFAULT=cash
PAYMENT_CURRENCY=GEL
PAYMENT_CARD_GATEWAY=null             # [LATER] flip to bog | tbc_pay | stripe
PAYMENT_APPLE_PAY_GATEWAY=stripe
PAYMENT_GOOGLE_PAY_GATEWAY=stripe
PAYMENT_RETRY_ATTEMPTS=3

# Empty until provisioned. The gateways throw RuntimeException
# rather than silently using a sandbox.
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
BOG_BASE_URL=https://api.bog.ge/payments/v1
BOG_CLIENT_ID=
BOG_CLIENT_SECRET=
BOG_MERCHANT_ID=
TBC_PAY_BASE_URL=https://api.tbcpay.ge/v1
TBC_PAY_API_KEY=
TBC_PAY_API_SECRET=
TBC_PAY_CAMPAIGN_ID=

# ─── Push notifications (Phase 2.0) ────────────────────────────────────
PUSH_DRIVER=null                      # [REQUIRED for prod] flip to 'firebase'
FIREBASE_CREDENTIALS=/var/www/hangover/shared/firebase-service-account.json

# When PUSH_DRIVER=firebase, drop the service-account JSON at the
# path above, mode 0600, owned by hangover. The kreait/laravel-
# firebase package picks it up automatically.

# ─── Crash + error reporting (Phase 2.1) ───────────────────────────────
SENTRY_LARAVEL_DSN=                   # [REQUIRED] DSN from sentry.io
SENTRY_RELEASE=hangover@0.1.0
SENTRY_TRACES_ENABLED=true
SENTRY_TRACES_SAMPLE_RATE=0.10

# ─── Safety + fraud (Phase 2.4) ────────────────────────────────────────
SAFETY_CANCEL_STORM_COUNT=5
SAFETY_CANCEL_STORM_WINDOW_HOURS=2
SAFETY_IMPLAUSIBLE_SPEED_KMH=200.0
SAFETY_MAX_DEVICES_24H=4
SAFETY_SOS_ACK_SLA_MIN=5
SAFETY_DOC_EXPIRY_WARNING_DAYS=30

# ─── Telescope (dev only) ──────────────────────────────────────────────
TELESCOPE_ENABLED=false               # always false in prod

# ─── Horizon ───────────────────────────────────────────────────────────
HORIZON_DOMAIN=                       # leave empty; lives at /horizon
HORIZON_PATH=horizon
HORIZON_DARK_MODE=true

# ─── Maps ──────────────────────────────────────────────────────────────
MAPS_PROVIDER=google
GOOGLE_MAPS_API_KEY=                  # [REQUIRED for prod] backend geocoder

# ─── App version gate (mobile) ─────────────────────────────────────────
APP_MIN_VERSION=0.1.0
APP_LATEST_VERSION=0.1.0
APP_FORCE_UPGRADE_BELOW=0.1.0

# ─── Misc ──────────────────────────────────────────────────────────────
BCRYPT_ROUNDS=12
HASH_DRIVER=bcrypt
TRUSTED_PROXIES=127.0.0.1
```

## Generating the secrets

Run these on your laptop, paste the output into the file above:

```bash
# APP_KEY — set later via artisan; leave empty here.

# Reverb keys (two separate values):
openssl rand -hex 16    # → REVERB_APP_KEY
openssl rand -hex 16    # → REVERB_APP_SECRET

# Random database + Redis passwords (already used during bring-up):
openssl rand -base64 24 | tr -d '=+/'    # MySQL DB_PASSWORD
openssl rand -base64 24 | tr -d '=+/'    # REDIS_PASSWORD
```

## File permissions

```bash
chmod 600 /var/www/hangover/shared/.env
chown hangover:hangover /var/www/hangover/shared/.env
```

## What the mobile clients need

The mobile apps don't read the `.env` directly. They read:

- `APP_URL` (the host) — baked into `mobile/packages/core/.../env_config.dart`
- `REVERB_APP_KEY` — same place, baked at build time
- `GOOGLE_MAPS_API_KEY` — baked into the platform manifest (see
  `docs/phase-2.1/build-apk-runbook.md`)

Mobile app builds need to be tied to a specific backend cohort —
i.e. don't reuse the same APK between `dev`, `staging`, and `prod`
flavours. The build pipeline in Phase 2.1 already handles this.

## Verifying after edits

```bash
cd /var/www/hangover/current/backend
php artisan config:clear
php artisan config:cache
php artisan about
# Verify "Environment: production", "Debug Mode: OFF", DB/Redis OK.
```

If `php artisan about` shows DB or Redis connection errors, fix the
`.env` values before continuing — caching propagates errors.
