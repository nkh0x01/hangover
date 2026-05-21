# VPS Bring-up — Fresh Ubuntu → Hangover-ready

> Step-by-step provisioning of a Tbilisi-region Ubuntu 22.04 / 24.04
> VPS so that `ride.365sakartvelo.com` is ready to serve the pilot.
> Designed to be ~60 minutes from `apt update` to "deployment-ready"
> for someone who's done a Laravel VPS before.

All commands assume you SSH as a non-root user with sudo. The
hardening section below covers the SSH key setup, firewall, fail2ban,
and disabling root login.

## 0. Pre-flight

Confirm before you start:

- [ ] You have `ride.365sakartvelo.com` `A` record pointing at the
      VPS (see `dns-records.md`).
- [ ] You can SSH to the VPS as root with the provider-issued
      password.
- [ ] You have an SSH public key handy on your laptop
      (`~/.ssh/id_ed25519.pub`).

## 1. Create a deploy user

```bash
# As root, on the VPS:
adduser hangover
usermod -aG sudo hangover
mkdir -p /home/hangover/.ssh
chmod 700 /home/hangover/.ssh

# Paste your public key:
nano /home/hangover/.ssh/authorized_keys
chmod 600 /home/hangover/.ssh/authorized_keys
chown -R hangover:hangover /home/hangover/.ssh
```

From your laptop:

```bash
ssh hangover@ride.365sakartvelo.com   # should succeed without a password
```

## 2. Harden SSH

`/etc/ssh/sshd_config.d/99-hangover.conf` (create as root):

```
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
ChallengeResponseAuthentication no
UsePAM no
X11Forwarding no
AllowUsers hangover
ClientAliveInterval 60
ClientAliveCountMax 5
```

```bash
sudo sshd -t              # validate syntax
sudo systemctl reload ssh
```

Do NOT close your existing root session until you've confirmed
`ssh hangover@…` works in a fresh terminal.

## 3. Firewall + fail2ban

```bash
sudo apt update
sudo apt install -y ufw fail2ban
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable

sudo systemctl enable --now fail2ban
```

Reverb listens on `127.0.0.1:8080` so it doesn't need a port open —
nginx proxies to it.

## 4. Time + locale

```bash
sudo timedatectl set-timezone Asia/Tbilisi
sudo apt install -y locales
sudo locale-gen en_US.UTF-8 ka_GE.UTF-8
```

## 5. PHP 8.3

Ubuntu 22.04 ships PHP 8.1 by default; 24.04 ships 8.3. On 22.04:

```bash
sudo apt install -y software-properties-common ca-certificates
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
```

Install:

```bash
sudo apt install -y \
  php8.3 php8.3-fpm php8.3-cli \
  php8.3-bcmath php8.3-curl php8.3-intl php8.3-mbstring \
  php8.3-mysql php8.3-pdo php8.3-redis php8.3-xml php8.3-zip \
  php8.3-soap php8.3-gd php8.3-imagick php8.3-opcache
```

Verify:

```bash
php -v        # 8.3.x
php -m | grep -E "redis|pdo_mysql|bcmath|intl|mbstring|gd"
```

`php-fpm` is enabled by default. Check:

```bash
sudo systemctl status php8.3-fpm
```

### PHP-FPM pool tuning

`/etc/php/8.3/fpm/pool.d/hangover.conf` (replace `www.conf`):

```ini
[hangover]
user = hangover
group = hangover
listen = /run/php/php8.3-fpm-hangover.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
pm.max_requests = 1000

php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 16M
php_admin_value[post_max_size] = 24M
php_admin_value[max_execution_time] = 60
php_admin_flag[log_errors] = on
php_admin_value[error_log] = /var/log/php-fpm-hangover.log
catch_workers_output = yes
```

Disable the default pool:

```bash
sudo mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.disabled
sudo systemctl restart php8.3-fpm
```

## 6. Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version    # 2.7.x or newer
```

## 7. MySQL 8

```bash
sudo apt install -y mysql-server-8.0
sudo mysql_secure_installation
```

Create the app database + user:

```sql
sudo mysql

CREATE DATABASE hangover CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'hangover'@'localhost' IDENTIFIED BY 'STRONG_RANDOM_PASSWORD';
GRANT ALL PRIVILEGES ON hangover.* TO 'hangover'@'localhost';
FLUSH PRIVILEGES;

-- Sanity check spatial support (Phase 1.5 migrations use POINT SRID 4326):
SELECT VERSION();                    -- expect 8.x
SELECT ST_X(ST_SRID(POINT(44.83, 41.72), 4326));   -- expect 44.83

EXIT;
```

Save the password into your password manager. You'll also paste it
into `/var/www/hangover/shared/.env` later.

### MySQL tuning (4-GB VPS)

`/etc/mysql/mysql.conf.d/99-hangover.cnf`:

```ini
[mysqld]
bind-address = 127.0.0.1
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 1
innodb_flush_method = O_DIRECT
max_connections = 100
table_open_cache = 2000
tmp_table_size = 64M
max_heap_table_size = 64M
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 1
log_queries_not_using_indexes = 0
```

```bash
sudo systemctl restart mysql
```

## 8. Redis 7

```bash
sudo apt install -y redis-server
sudo nano /etc/redis/redis.conf
```

Set (or confirm):

```
bind 127.0.0.1 ::1
protected-mode yes
requirepass STRONG_RANDOM_REDIS_PASSWORD
maxmemory 512mb
maxmemory-policy allkeys-lru
appendonly yes
appendfsync everysec
```

```bash
sudo systemctl restart redis-server
redis-cli -a STRONG_RANDOM_REDIS_PASSWORD ping   # expect PONG
```

Save the Redis password.

## 9. Nginx + certbot

```bash
sudo apt install -y nginx certbot python3-certbot-nginx
sudo systemctl enable --now nginx
```

Drop the default site:

```bash
sudo rm /etc/nginx/sites-enabled/default
```

The Hangover server block lives in `nginx-config.md` — wire it in
after the app is on disk.

## 10. Supervisor

```bash
sudo apt install -y supervisor
sudo systemctl enable --now supervisor
```

Config drops into `/etc/supervisor/conf.d/`. We add Horizon + Reverb
processes in `queue-workers.md`.

## 11. Node 20 (for asset compilation, optional)

Filament 3 ships with pre-built assets. You only need Node if you
intend to recompile Tailwind or run `npm run build`. For pilot, skip.

```bash
# Skip unless building front-end assets:
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 12. Filesystem layout

We use the **atomic-releases pattern**: each deploy is a fresh
checkout in `/var/www/hangover/releases/YYYYMMDDhhmmss`; the symlink
`/var/www/hangover/current` flips at the end. Shared state
(`.env`, `storage/`, `bootstrap/cache/` permissions) lives in
`/var/www/hangover/shared`.

```bash
sudo mkdir -p /var/www/hangover/{releases,shared}
sudo mkdir -p /var/www/hangover/shared/{storage,bootstrap-cache}
sudo mkdir -p /var/www/hangover/shared/storage/{app,framework,logs}
sudo mkdir -p /var/www/hangover/shared/storage/framework/{cache,sessions,testing,views}
sudo chown -R hangover:hangover /var/www/hangover
```

This layout is what `deployment-checklist.md` § "Per-deploy" expects.

## 13. Clone the repo + first release

```bash
cd /var/www/hangover/releases
git clone --branch claude/scooter-platform-architecture-Wvmeu \
  https://github.com/nkh0x01/hangover.git \
  $(date +%Y%m%d%H%M%S)
cd $(ls -1d 20* | tail -n1)/backend
```

(For production, switch to a tagged release branch like `pilot-v0.1.0`
once the team tags one. For now the long branch name works.)

```bash
composer install --no-dev --optimize-autoloader --no-interaction
```

## 14. Copy + edit `.env`

```bash
cp /var/www/hangover/releases/<release>/backend/.env.example \
   /var/www/hangover/shared/.env
nano /var/www/hangover/shared/.env
```

Use the template in `env-production-template.md`.

Link it into the release:

```bash
ln -sf /var/www/hangover/shared/.env \
       /var/www/hangover/releases/<release>/backend/.env
```

Link shared state:

```bash
release=/var/www/hangover/releases/<release>/backend
rm -rf "$release/storage"
ln -sf /var/www/hangover/shared/storage "$release/storage"
```

## 15. App key + first migrate

```bash
cd /var/www/hangover/releases/<release>/backend
php artisan key:generate --force
php artisan storage:link
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## 16. Activate the release

```bash
ln -sfn /var/www/hangover/releases/<release> /var/www/hangover/current
```

`/var/www/hangover/current/backend/public` is what nginx serves.

## 17. nginx + SSL

Follow `nginx-config.md`. Two-step:

```bash
# 1. Drop the server block.
sudo cp /var/www/hangover/current/docs/deployment/snippets/ride.conf \
        /etc/nginx/sites-available/ride.365sakartvelo.com
sudo ln -s /etc/nginx/sites-available/ride.365sakartvelo.com \
           /etc/nginx/sites-enabled/

# 2. Initial cert (HTTP-01 challenge).
sudo certbot --nginx -d ride.365sakartvelo.com \
  --email ops@365sakartvelo.com --agree-tos --no-eff-email --redirect

sudo nginx -t && sudo systemctl reload nginx
```

(The snippets directory is created in `nginx-config.md` — you can
also paste the config directly.)

## 18. Supervisor processes

Follow `queue-workers.md` to drop Horizon + Reverb supervisor
configs, then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
# expect: horizon RUNNING / reverb RUNNING
```

## 19. Cron — Laravel scheduler

```bash
crontab -e -u hangover
```

Add:

```
* * * * * cd /var/www/hangover/current/backend && php artisan schedule:run >> /dev/null 2>&1
```

This runs the Laravel scheduler every minute — used by Horizon
snapshots, offer-expiry mop-up, and the daily payouts cron when
Phase 2.5 lands.

## 20. First-deploy smoke

```bash
curl -s https://ride.365sakartvelo.com/api/v1/health
# expected: { "status":"ok", ... }

# Verify WS:
curl -i https://ride.365sakartvelo.com/app/<reverb-key>
# expected: 426 Upgrade Required (Reverb refuses non-WS HTTP)

# Verify admin:
curl -s -o /dev/null -w "%{http_code}\n" https://ride.365sakartvelo.com/admin
# expected: 302 → /admin/login
```

If any of the above fails, do NOT seed real data. Go through
`deployment-checklist.md` § "Bring-up troubleshooting".

## 21. Backups

We're a single-host deployment — backups are not optional.

### Daily database snapshot

`/etc/cron.daily/hangover-db-dump`:

```bash
#!/usr/bin/env bash
set -euo pipefail

DEST=/var/backups/hangover
mkdir -p "$DEST"
TS=$(date +%Y%m%d-%H%M%S)
mysqldump --single-transaction --quick --hex-blob \
  -u hangover -p"$(cat /root/.mysql-pw)" hangover \
  | gzip > "$DEST/hangover-$TS.sql.gz"

# Retain 14 days locally.
find "$DEST" -name 'hangover-*.sql.gz' -mtime +14 -delete

# Ship off-host (configure first):
# rclone copy "$DEST/hangover-$TS.sql.gz" "remote:hangover-backups/"
```

```bash
sudo chmod +x /etc/cron.daily/hangover-db-dump
echo 'YOUR_MYSQL_PASSWORD' | sudo tee /root/.mysql-pw > /dev/null
sudo chmod 600 /root/.mysql-pw
```

### Off-host

Configure `rclone` against an S3-compatible store (Backblaze B2,
Wasabi, or your existing AWS account). At minimum: 30 days of daily
backups in a different region from the VPS.

```bash
sudo apt install -y rclone
sudo -u hangover rclone config   # configure once interactively
```

Uncomment the `rclone copy` line in the dump script after this.

### Application files

The app is reproducible from git + `.env`. The only thing you can't
reproduce is `storage/app/public/documents/*` (driver document
uploads). Add to the daily backup script:

```bash
tar czf "$DEST/hangover-storage-$TS.tar.gz" \
    -C /var/www/hangover/shared storage/app
```

For pilot scale (≤ 50 drivers, ≤ 350 documents) this stays well
under 100 MB. Phase 3 moves these to S3.

## 22. Monitoring (lightweight)

```bash
sudo apt install -y htop iotop sysstat
```

For real monitoring, point Sentry at the backend (already done in
Phase 2.1) — `SENTRY_LARAVEL_DSN=...` in `.env`. That gives us
exceptions + performance traces without paying for a metrics agent.

Want full metrics later? Add Netdata in 60 s:

```bash
bash <(curl -Ss https://my-netdata.io/kickstart.sh)
```

Listen on `127.0.0.1` only and SSH-tunnel from your laptop.

## Bring-up timeline

| Step                                | Time      |
|-------------------------------------|-----------|
| Sections 1–4 (user, SSH, firewall)  | 10 min    |
| Sections 5–6 (PHP, Composer)        | 10 min    |
| Section 7 (MySQL + tuning)          | 10 min    |
| Sections 8–10 (Redis, nginx, supervisor) | 10 min |
| Sections 12–17 (app deploy + SSL)  | 15 min    |
| Sections 18–19 (supervisor + cron)  | 5 min     |
| Sections 20–22 (smoke + backups)    | 10 min    |
| **Total**                            | **~70 min** |

The first iteration takes longer because of password generation,
DNS propagation waits, and certbot challenges. Subsequent deploys
follow `deployment-checklist.md` and take 5 min.
