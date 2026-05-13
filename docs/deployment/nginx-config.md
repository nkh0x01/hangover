# nginx + SSL Configuration

> Reverse-proxy setup for `ride.365sakartvelo.com`. Serves PHP-FPM
> for the API + admin, upgrades WebSocket connections to Reverb,
> and terminates TLS via Let's Encrypt.
>
> We use **nginx, not Apache** — Reverb's WebSocket upgrade is
> straightforward in nginx, fiddly in Apache. If you're on a cPanel
> box with Apache mandatory, see the appendix at the bottom of this
> file for the equivalent rewrite + proxy block.

## Single server block

`/etc/nginx/sites-available/ride.365sakartvelo.com`:

```nginx
# Hangover Mobility — pilot single-host config.

upstream hangover_php_fpm {
    server unix:/run/php/php8.3-fpm-hangover.sock;
}

upstream hangover_reverb {
    server 127.0.0.1:8080;
    keepalive 16;
}

# Rate-limit zones — used per-location below.
limit_req_zone $binary_remote_addr zone=otp_req:10m   rate=10r/m;
limit_req_zone $binary_remote_addr zone=safety_req:10m rate=30r/m;
limit_req_zone $binary_remote_addr zone=api_req:10m    rate=120r/m;

# HTTP → HTTPS redirect.
server {
    listen 80;
    listen [::]:80;
    server_name ride.365sakartvelo.com;

    # Let's Encrypt HTTP-01 challenge.
    location /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
    }

    location / {
        return 301 https://$host$request_uri;
    }
}

# Main HTTPS server.
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ride.365sakartvelo.com;

    root /var/www/hangover/current/backend/public;
    index index.php;

    # ── TLS ────────────────────────────────────────────────────────
    ssl_certificate     /etc/letsencrypt/live/ride.365sakartvelo.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ride.365sakartvelo.com/privkey.pem;
    ssl_session_timeout 1d;
    ssl_session_cache shared:HangoverSSL:50m;
    ssl_session_tickets off;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305;
    ssl_prefer_server_ciphers off;
    ssl_stapling on;
    ssl_stapling_verify on;

    # ── Security headers ───────────────────────────────────────────
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(self), microphone=(), camera=()" always;
    # No CSP for now — Filament uses inline scripts that need a careful policy.

    # ── Size + timeout ─────────────────────────────────────────────
    client_max_body_size 24m;          # driver document uploads ≤ 8 MB
    client_body_timeout 60s;
    send_timeout 60s;
    keepalive_timeout 75s;
    gzip on;
    gzip_types text/plain application/json application/javascript text/css text/xml application/xml;

    # ── Reverb WebSocket upstream ──────────────────────────────────
    # Mobile clients hit wss://ride.365sakartvelo.com/app/<reverb-key>
    # and /apps/<id>/events for stats. nginx upgrades the connection
    # and proxies to the Reverb daemon on 127.0.0.1:8080.
    location ~ ^/(app|apps)/ {
        proxy_pass http://hangover_reverb;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;        # long-running WS connections
        proxy_send_timeout 3600s;
        proxy_connect_timeout 60s;
    }

    # ── Per-route rate limits ──────────────────────────────────────
    # OTP send + verify: strict — 10 req / min / IP.
    location ~ ^/api/v1/auth/(send-otp|verify-otp)$ {
        limit_req zone=otp_req burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Safety endpoints: moderate — 30 req / min / IP.
    location ^~ /api/v1/safety/ {
        limit_req zone=safety_req burst=10 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Generic API: 120 req / min / IP.
    location ^~ /api/ {
        limit_req zone=api_req burst=60 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # ── Static assets ──────────────────────────────────────────────
    location ~* \.(jpg|jpeg|png|gif|webp|svg|ico|css|js|woff2?|ttf|map)$ {
        expires 30d;
        access_log off;
        try_files $uri /index.php?$query_string;
    }

    # ── Storage symlink (avatar previews etc) ──────────────────────
    location /storage/ {
        try_files $uri =404;
        access_log off;
        expires 7d;
    }

    # ── Hide sensitive files ───────────────────────────────────────
    location ~ /\.(env|git|ht) {
        deny all;
        return 404;
    }

    # ── Laravel front controller ───────────────────────────────────
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass hangover_php_fpm;
        fastcgi_index index.php;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param HTTPS on;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        fastcgi_read_timeout 90s;
    }

    # ── Logs ───────────────────────────────────────────────────────
    access_log /var/log/nginx/ride.access.log;
    error_log  /var/log/nginx/ride.error.log warn;
}
```

## Activation

```bash
sudo ln -sf /etc/nginx/sites-available/ride.365sakartvelo.com \
            /etc/nginx/sites-enabled/

sudo mkdir -p /var/www/letsencrypt

sudo nginx -t          # must pass before reload
sudo systemctl reload nginx
```

## Let's Encrypt cert

```bash
sudo certbot --nginx \
  -d ride.365sakartvelo.com \
  --email ops@365sakartvelo.com \
  --agree-tos --no-eff-email \
  --redirect
```

Certbot edits the redirect block above (it's already correct, so the
change is a no-op) and writes the certificate paths. Auto-renewal is
installed as a systemd timer:

```bash
sudo systemctl status certbot.timer
sudo certbot renew --dry-run    # verify renewal works
```

Renewal happens twice daily, only fires within 30 days of expiry.

## TLS grade verification

After cert install, test:

```bash
# From your laptop:
curl -sI https://ride.365sakartvelo.com/api/v1/health | head
# expect: HTTP/2 200, Strict-Transport-Security header, etc.

# SSL Labs:
# https://www.ssllabs.com/ssltest/analyze.html?d=ride.365sakartvelo.com
# Expected: A or A+ with TLS 1.2 + 1.3 only, no weak ciphers.
```

## WebSocket sanity

```bash
# From your laptop — confirm the WS upstream:
curl -i \
  -H "Connection: Upgrade" \
  -H "Upgrade: websocket" \
  -H "Sec-WebSocket-Key: $(openssl rand -base64 16)" \
  -H "Sec-WebSocket-Version: 13" \
  https://ride.365sakartvelo.com/app/<REVERB_APP_KEY>

# Expected: HTTP/1.1 101 Switching Protocols (or HTTP/2 equivalent).
# If you see 502 — Reverb daemon isn't running on 127.0.0.1:8080.
# If you see 403 — REVERB_ALLOWED_ORIGINS doesn't include your origin.
```

## Per-route rate-limit tuning

The limits above are pilot-conservative:

| Endpoint        | Limit          | Burst | Justification                                       |
|-----------------|----------------|-------|-----------------------------------------------------|
| OTP send/verify | 10/min/IP      | 5     | Prevents SMS-pumping abuse                          |
| Safety          | 30/min/IP      | 10    | SOS + complaints; can't be too tight in an emergency|
| API (generic)   | 120/min/IP     | 60    | 2 req/s steady, 60-burst lets ride flow breathe    |

Adjust upward once we have NAT-pool data — multiple users behind
one carrier-grade-NAT can share an IP. Phase 3 moves to
device-uuid-aware rate-limiting via Laravel's `RateLimiter::for()`.

## Cloudflare in front (optional)

If you proxy through Cloudflare:
1. `TRUSTED_PROXIES=*` in `.env` (or list the Cloudflare ranges
   explicitly).
2. SSL mode "Full (strict)" — Cloudflare to origin must use the
   Let's Encrypt cert.
3. Cloudflare → Network → WebSockets: **On**.
4. Cloudflare → Speed → Rocket Loader: **Off**. (Breaks Livewire.)
5. Cloudflare → Caching → Page Rules: bypass cache for `/api/*`,
   `/admin/*`, `/horizon/*`, `/livewire/*`, `/app/*`.

For pilot, **don't** use Cloudflare — it adds a hop with its own
rate limits and complicates WebSocket debugging. Plain nginx is
fine until 100 concurrent active rides.

## Apache fallback (cPanel mandatory)

You're not supposed to be on cPanel for this — see the README's
verdict — but if you must, this is the minimum that works:

```apache
<VirtualHost *:443>
    ServerName ride.365sakartvelo.com
    DocumentRoot /home/<cpanel-user>/public_html/current/backend/public

    SSLEngine on
    SSLCertificateFile  /etc/letsencrypt/live/ride.365sakartvelo.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/ride.365sakartvelo.com/privkey.pem

    <Directory /home/<cpanel-user>/public_html/current/backend/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Reverb upstream — requires mod_proxy_wstunnel.
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteRule /(app|apps)/(.*) ws://127.0.0.1:8080/$1/$2 [P,L]

    ProxyPass        /app/  http://127.0.0.1:8080/app/
    ProxyPassReverse /app/  http://127.0.0.1:8080/app/

    ErrorLog ${APACHE_LOG_DIR}/ride.error.log
    CustomLog ${APACHE_LOG_DIR}/ride.access.log combined
</VirtualHost>
```

You still need Redis + Supervisor + Reverb daemon running — which
shared cPanel doesn't allow. Don't waste time on the Apache path
unless you have a managed-VPS-with-cPanel plan.

## Logs to watch

```bash
sudo tail -f /var/log/nginx/ride.access.log
sudo tail -f /var/log/nginx/ride.error.log
sudo journalctl -u php8.3-fpm -f
```

During the first hour after launch, keep all three open in tmux
panes.
