# Hotel PMS — staging deploy bundle

Target: **`hotel.365sakartvelo.com`** · stack: shared hosting (cPanel/Plesk + Apache)

This bundle is run **on the server**. The Claude sandbox cannot reach
`hotel.365sakartvelo.com` directly, so you'll either SSH in or upload via
cPanel File Manager and run from cPanel's terminal.

---

## What you need before you start

| Need                        | Where                                                |
|-----------------------------|------------------------------------------------------|
| SSH access to the server    | cPanel → SSH Access (or Plesk → SSH Terminal)        |
| MySQL database + user       | cPanel → MySQL® Databases                            |
| Subdomain pointed at server | cPanel → Domains → `hotel.365sakartvelo.com`         |
| SSL for that subdomain      | cPanel → SSL/TLS Status → AutoSSL                    |
| PHP 8.3 or 8.4 selected     | cPanel → MultiPHP Manager (or "Select PHP Version")  |
| Composer 2.x available      | cPanel → Terminal `which composer` — install if not  |
| Node 20.x available         | Same — `node -v`. Or build assets locally and upload |

PHP extensions needed: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`,
`ctype`, `json`, `bcmath`, `fileinfo`, `gd`, `intl`, `zip`.

---

## Step-by-step

### 1. Pull the code onto the server

```bash
cd ~                                # cPanel home directory
git clone https://github.com/nkh0x01/hangover.git hotel-pms
cd hotel-pms
git checkout claude/hotel-pms-channel-manager-jj7LT
```

If git over HTTPS isn't allowed on shared hosting, download the branch as a
zip from GitHub and upload via File Manager into `~/hotel-pms`.

### 2. Point the subdomain at `~/hotel-pms/public`

This is the single most important step on shared hosting — Laravel's
front-controller MUST be the document root.

**cPanel** → *Domains* → *hotel.365sakartvelo.com* → *Document Root*
→ change to `/home/<user>/hotel-pms/public`.

**Plesk** → *Domains* → *hotel.365sakartvelo.com* → *Hosting Settings*
→ *Document root* → `/hotel-pms/public`.

> **Fallback for hosts that won't let you change document root** — see
> "Option B: webroot is `public_html`" at the bottom.

### 3. Configure `.env`

```bash
cd ~/hotel-pms
cp deploy/.env.staging.example .env
nano .env                  # fill in DB_DATABASE / DB_USERNAME / DB_PASSWORD
                           # and a strong STAGING_DEMO_PASSWORD
php artisan key:generate
```

Required fields you must set:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` — the MySQL DB you created in cPanel
- `STAGING_DEMO_PASSWORD` — long, non-obvious. Will be the password for
  `admin@example.test` and `reception@example.test` after seeding.

### 4. Run the deploy script

```bash
bash deploy/deploy.sh
```

This runs (in order):

```
composer install --no-dev --optimize-autoloader
chmod -R 775 storage bootstrap/cache
npm ci && npm run build
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan migrate:fresh --seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan storage:link
```

The script refuses to run unless `APP_ENV=staging` and `.env` is present.

### 5. Copy the hardened `.htaccess`

```bash
cp deploy/htaccess-public public/.htaccess
```

This file adds:
- HTTPS redirect (works behind cPanel's SSL terminator)
- `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`,
  `Permissions-Policy` headers
- Denies direct access to `.env`, `composer.json`, etc.

### 6. (Optional) Basic-auth gate the whole subdomain

cPanel ships *Directory Privacy*: cPanel → *Directory Privacy* →
`/home/<user>/hotel-pms/public` → enable, add a user, set password. The
browser then prompts for HTTP Basic-Auth before Laravel even sees the request.
Apply this if the subdomain shouldn't be world-readable yet.

### 7. Smoke-test

```bash
curl -sI https://hotel.365sakartvelo.com/login | head -5
# Expected: HTTP/2 200

php artisan test                # optional: runs the 152-test Pest suite
```

Open `https://hotel.365sakartvelo.com/login` in a browser. You should see
the Georgian login page.

---

## Demo accounts

| Role        | Email                       | Password                          |
|-------------|----------------------------|-----------------------------------|
| Super admin | `admin@example.test`        | `$STAGING_DEMO_PASSWORD` from .env|
| Reception   | `reception@example.test`    | `$STAGING_DEMO_PASSWORD` from .env|

Registration is **disabled** in `routes/auth.php` — only seeded users exist.
Password reset is enabled but `MAIL_MAILER=log` so reset emails never leave
the server; they end up in `storage/logs/laravel.log` instead.

## Seeded demo data

- 1 hotel (`Hotel Tbilisi`, GEL, 18 % VAT)
- 12 rooms across 3 floors, 4 room types (Standard / Deluxe / Twin / Family)
- 5 reservations:
  1. checked-OUT 3 days ago — with invoice + completed card payment
  2. checked-IN now, in-house
  3. confirmed, arriving today
  4. confirmed, +20 days
  5. confirmed, +25 days
- Pricing rules: weekend +15 %, summer +25 %, last-minute −10 %, a manual
  override and a CTA day in the next week
- Inventory: 1 storeroom + 1 reception POS location with seeded stock
- Channel manager: Mock connection (sandbox, dry-run) + Booking.com sandbox
  connection (dry-run, all outbound HTTP disabled)

All Channel-manager providers are forced to **dry-run** in staging — no
network calls ever reach Booking.com / Expedia / Airbnb.

---

## What's locked down

- `APP_DEBUG=false` (no stack traces in browser)
- `MAIL_MAILER=log` (no outbound mail)
- `QUEUE_CONNECTION=sync` (no Redis dependency)
- `SESSION_SECURE_COOKIE=true` (cookies require HTTPS)
- Registration route removed from `routes/auth.php`
- `.env`, `composer.json`, `vendor/`, `.git/`, `tests/` denied at Apache layer
- Channel manager dry-run forced for every connection by seeder

---

## Option B: webroot is `public_html`

If your host won't let you set the document root, lay things out like this:

```
~/hotel-pms/              # Laravel project (NOT in webroot)
~/public_html/            # everything in /public, moved here
  index.php               # edited to bootstrap from ~/hotel-pms
  build/
  ...
~/public_html/.htaccess   # copied from deploy/htaccess-public
```

You'll need to edit `~/public_html/index.php`:

```php
// was:  require __DIR__.'/../bootstrap/app.php';
// becomes:
require __DIR__.'/../hotel-pms/bootstrap/app.php';

// was:  $app->bootstrap($_SERVER, fn () => ...);
// becomes (line numbers may differ):
require __DIR__.'/../hotel-pms/vendor/autoload.php';
```

And ALSO place `deploy/htaccess-root` as `~/hotel-pms/.htaccess` to refuse
direct serving from the project root if the doc-root setting ever drifts.

This setup is messier and more fragile — prefer Option A.

---

## Troubleshooting

**500 error on first visit**
- Check `storage/logs/laravel.log` for the real cause
- 99% of the time: `storage/` or `bootstrap/cache/` not writable.
  Fix: `chmod -R 775 storage bootstrap/cache`

**Mixed content / asset 404s**
- `APP_URL` and `ASSET_URL` must match the actual URL (https + subdomain)
- Confirm `public/build/manifest.json` exists; otherwise `npm run build`
  again on the server

**`vite manifest not found`**
- The bundle isn't built. Run `npm ci && npm run build` on the server, or
  upload the `public/build/` directory you built locally.

**`SQLSTATE[HY000] [2002]`**
- DB credentials wrong, or the MySQL user doesn't have privileges on the DB.
  In cPanel: *MySQL® Databases* → *Add User To Database* → ALL PRIVILEGES.

**Login fails with "These credentials do not match…"**
- Re-run `php artisan migrate:fresh --seed --force` and check
  `STAGING_DEMO_PASSWORD` in `.env` — that's the password the seeder hashed
  into the demo accounts.

**Cron / scheduler**
- Not required for the preview. If you want the channel pull scheduler
  active later, add: `* * * * * cd ~/hotel-pms && php artisan schedule:run`

---

## When you want to update the staging copy

```bash
cd ~/hotel-pms
git fetch origin
git checkout claude/hotel-pms-channel-manager-jj7LT
git pull
bash deploy/deploy.sh --quick    # skips migrate:fresh
```

`--quick` keeps existing data and only runs `migrate --force` for new
migrations. Drop the `--quick` to reset the demo data.
