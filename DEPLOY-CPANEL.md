# Deploying on cPanel (subdomain, MySQL-only, no Redis)

This is the most common shared-hosting setup. You get:

- A subdomain (e.g. `bot.gadget.ge`)
- MySQL/MariaDB
- PHP 8.2+ via cPanel "Setup PHP App"
- cPanel cron jobs (1-minute granularity)
- No SSH (or limited shell), no Redis, no supervisor

The bot is designed to run **fully on MySQL** in this mode. Queue, cache
and session all use database drivers; there is no daemon — a single
cron entry every minute keeps everything alive.

Trade-off: the **human-like debounce** is no longer 5-15s but ~30-60s
(one cron tick). Still better than instant. If you ever move to a VPS
with Redis + supervisor, flip three env vars and you're on the fast
path automatically.

---

## 1) Create the subdomain

In cPanel:

1. **Domains → Create New Domain**
2. Domain: `bot.gadget.ge`
3. Document Root: `/home/USER/bot.gadget.ge/public`   ← important: `/public`
4. Save

cPanel creates `/home/USER/bot.gadget.ge/` for you. We'll upload code
there, but the web only serves `/public/`.

## 2) Create the MySQL database

cPanel: **MySQL Databases**

1. Create DB: `USER_gadgetbot`
2. Create user: `USER_gadget` with strong password
3. Add user to DB → grant **ALL PRIVILEGES**
4. Note them — you'll paste into `.env`.

## 3) Upload the code

Easiest: cPanel **Git Version Control**.

1. cPanel → **Git Version Control → Create**
2. Clone URL: your fork's HTTPS URL
3. Repository Path: `/home/USER/bot.gadget.ge`
4. Branch: `claude/gadget-ai-sales-chatbot-dKe2l` (or `master` after merge)
5. Click **Create**

Alternative: **File Manager → Upload zip → Extract** into
`/home/USER/bot.gadget.ge/`.

After the files are there:

```
/home/USER/bot.gadget.ge/
├── app/
├── bootstrap/
├── config/
├── database/
├── public/       ← document root
├── routes/
├── storage/
├── artisan
├── composer.json
└── .env.example
```

## 4) Set up the PHP app (cPanel)

cPanel → **Setup PHP App** (sometimes "Setup Node.js / PHP App"):

1. **Create Application**
2. PHP version: **8.2** or **8.3** (8.4 also works)
3. Application root: `/home/USER/bot.gadget.ge`
4. Application URL: `bot.gadget.ge`
5. Application startup file: leave blank
6. Click **Create**, then **Run Composer install** button.

If "Run Composer install" isn't available, open **Terminal** in cPanel:

```bash
cd ~/bot.gadget.ge
composer install --no-dev --optimize-autoloader
```

## 5) Configure `.env`

cPanel → **File Manager** → `/home/USER/bot.gadget.ge/`.

1. Copy `.env.example` to `.env`.
2. Edit `.env` and set at minimum:

```env
APP_NAME="Gadget AI"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bot.gadget.ge
APP_TIMEZONE=Asia/Tbilisi

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=USER_gadgetbot
DB_USERNAME=USER_gadget
DB_PASSWORD=********

# DB-driven — no Redis required
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# WhatsApp / Messenger / Instagram / FB — fill from Meta App Dashboard
WHATSAPP_VERIFY_TOKEN=...
WHATSAPP_APP_SECRET=...
WHATSAPP_PHONE_NUMBER_ID=...
WHATSAPP_BUSINESS_ACCOUNT_ID=...
WHATSAPP_ACCESS_TOKEN=...

MESSENGER_VERIFY_TOKEN=...
MESSENGER_APP_SECRET=...
MESSENGER_PAGE_ID=...
MESSENGER_PAGE_ACCESS_TOKEN=...

INSTAGRAM_VERIFY_TOKEN=...
INSTAGRAM_APP_SECRET=...
INSTAGRAM_ACCOUNT_ID=...
INSTAGRAM_ACCESS_TOKEN=...

# Claude
ANTHROPIC_API_KEY=sk-ant-...

# gadget.ge (WooCommerce REST API)
GADGET_WC_BASE_URL=https://gadget.ge
GADGET_WC_CONSUMER_KEY=ck_...
GADGET_WC_CONSUMER_SECRET=cs_...
GADGET_WC_WEBHOOK_SECRET=random_string_here

# Escalation target — your phone in E.164
ESCALATION_WHATSAPP_TO=995599XXXXXX
ESCALATION_ADMIN_URL=https://bot.gadget.ge/admin

# Payments
PAYMENT_PROVIDER=bog
PAYMENT_API_KEY=...
PAYMENT_API_SECRET=...
PAYMENT_RETURN_URL=https://bot.gadget.ge/payments/return
PAYMENT_FAIL_URL=https://bot.gadget.ge/payments/fail
```

## 6) Generate key, run migrations, seed

In cPanel **Terminal** (or via **Setup PHP App → Run Script**):

```bash
cd ~/bot.gadget.ge
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=SystemPromptSeeder --force
php artisan db:seed --class=DemoEmployeesSeeder --force   # optional
```

Then change the owner password from `/admin` immediately.

## 7) One cron entry — the tick

cPanel → **Cron Jobs → Add New Cron Job**

- Common Settings: **Every minute (`* * * * *`)**
- Command (one line — replace `USER` with your cPanel username; PHP path may differ per host):

```bash
cd /home/USER/bot.gadget.ge && /usr/local/bin/php artisan tick >> storage/logs/tick.log 2>&1
```

That's it. `tick` does:

1. `php artisan schedule:run` — fires catalog sync, daily summary, etc.
2. drains the `jobs` table for ~50 seconds — picks up incoming webhooks,
   debounced AI replies, comment responders.

If your host's PHP binary is somewhere else, find it with
`which php` or check **Setup PHP App** for the path.

## 8) HTTPS

cPanel → **SSL/TLS Status** → tick `bot.gadget.ge` → **Run AutoSSL**.
Meta won't accept non-HTTPS webhooks; Let's Encrypt is fine.

## 9) Point Meta webhooks at the subdomain

In your Facebook App Dashboard:

| Channel    | Callback URL                              |
| ---------- | ----------------------------------------- |
| WhatsApp   | `https://bot.gadget.ge/webhooks/whatsapp`   |
| Messenger  | `https://bot.gadget.ge/webhooks/messenger`  |
| Instagram  | `https://bot.gadget.ge/webhooks/instagram`  |
| Facebook   | `https://bot.gadget.ge/webhooks/messenger`  |

Verify Token = whatever you put into `.env` for each channel.

For gadget.ge: WordPress admin → **WooCommerce → Settings → Advanced →
Webhooks** → all targeting `https://bot.gadget.ge/webhooks/gadget`.

## 10) Catalog sync

Once WC keys are in `.env`, the scheduler picks up `gadget:sync-products`
every 15 minutes and `gadget:sync-coupons` every 30. For an immediate
first sync:

```bash
cd ~/bot.gadget.ge
php artisan gadget:sync-products
php artisan gadget:sync-coupons
```

## 11) Verify

- Visit `https://bot.gadget.ge/admin` → log in
- Send your WhatsApp business number a test message → should appear
  in the inbox within ~60s (cron tick) and the bot replies
- Comment on a FB page post → bot replies publicly + opens DM

---

## File permissions

cPanel usually sets these correctly via "Setup PHP App", but if you
see 500 errors:

```bash
cd ~/bot.gadget.ge
chmod -R 775 storage bootstrap/cache
find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

## Troubleshooting

- **`Connection refused` to MySQL** — host should be `127.0.0.1`,
  not `localhost`, when `mysqld` uses TCP. cPanel usually accepts both.
- **`tick.log` empty** — make sure the cron command's working dir is
  absolute (`cd /home/USER/bot.gadget.ge` not `cd bot.gadget.ge`).
- **Webhooks return 500** — `tail -n 100 storage/logs/laravel.log`.
- **AI replies never arrive** — check `jobs` and `failed_jobs` tables
  in phpMyAdmin. A populated `failed_jobs` table is your first clue.
- **Customer waits >2 minutes** — your cron isn't firing. Confirm in
  cPanel → Cron Jobs → "Currently scheduled cron jobs" → there should
  be a `* * * * *` row. Some hosts disable per-minute crons; ask
  support to enable it.

## Moving up to Redis later (no rewrite)

When you get a VPS / dedicated server with Redis:

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
```

Stop the cron, start a supervisor process running
`php artisan queue:work redis --queue=inbound,reply,comments --tries=3`,
and you're back to 5-15s debounce. No code changes.
