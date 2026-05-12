# 09 — Deployment Architecture

## 9.1 Environments

| Env | Purpose | Domain | Data |
|---|---|---|---|
| `local` | Developer laptop | `localhost` | Docker Compose, synthetic seeds |
| `dev` | Always-on cloud dev | `*.dev.hangover.app` | Synthetic seeds, reset weekly |
| `staging` | Pre-prod mirror | `*.staging.hangover.app` | Anonymized prod sample restored weekly |
| `prod` | Production | `*.hangover.app` | Real |
| `dr` | Disaster recovery | warm standby in second AZ/region | Streaming replica of prod |

## 9.2 Reference topology (production)

```
                                Internet
                                   │
                       ┌───────────┴────────────┐
                       │   Cloudflare (CDN +    │
                       │   WAF + DDoS)          │
                       └───────────┬────────────┘
                                   │
                       ┌───────────┴────────────┐
                       │  AWS Application LB    │
                       │  (api., admin., ws.)   │
                       └─────┬───────────┬──────┘
                             │           │
                ┌────────────┘           └───────────────┐
                ▼                                        ▼
       ┌────────────────────┐                  ┌────────────────────┐
       │  API/Admin (ECS)   │                  │  Reverb WS (ECS)   │
       │  3+ tasks, AZ-     │                  │  3+ tasks,         │
       │  balanced          │                  │  sticky by client  │
       │  php-fpm + nginx   │                  │  PHP daemon        │
       └─────┬───────┬──────┘                  └─────────┬──────────┘
             │       │                                    │
             │       ▼                                    │
             │   ┌────────────────────┐                   │
             │   │  Horizon Workers   │                   │
             │   │  (ECS)             │                   │
             │   │  realtime/default/ │                   │
             │   │  low queues        │                   │
             │   └────────┬───────────┘                   │
             │            │                               │
             ▼            ▼                               ▼
       ┌────────────────────────────────────────────────────┐
       │  Amazon RDS for MySQL 8.0  (Multi-AZ, gp3 SSD)     │
       │  ─ writer + 1 replica (read-only)                   │
       └────────────────────────────────────────────────────┘
       ┌────────────────────────────────────────────────────┐
       │  Amazon ElastiCache for Redis 7 (cluster mode off, │
       │  Multi-AZ failover)                                 │
       └────────────────────────────────────────────────────┘
       ┌────────────────────────────────────────────────────┐
       │  S3 (driver-docs, avatars, exports)                │
       └────────────────────────────────────────────────────┘
       ┌────────────────────────────────────────────────────┐
       │  Secrets Manager (DB creds, Stripe keys, FCM,      │
       │  SMS provider tokens)                              │
       └────────────────────────────────────────────────────┘
       ┌────────────────────────────────────────────────────┐
       │  CloudWatch + OTel Collector → Grafana Cloud       │
       └────────────────────────────────────────────────────┘
```

Region: `eu-central-1` (Frankfurt). DR region: `eu-west-1` (Ireland) — cross-region snapshot every 1 h, S3 cross-region replication on.

## 9.3 Container layout

We containerize everything with Docker. Image build via multi-stage Dockerfile:

```
backend/
├── Dockerfile                # base: php:8.3-fpm-alpine + extensions
├── docker/
│   ├── nginx.conf
│   ├── php-fpm.conf
│   ├── opcache.ini
│   ├── supervisord.conf
│   └── entrypoints/
│       ├── api.sh
│       ├── horizon.sh
│       └── reverb.sh
└── docker-compose.yml        # for local
```

A single image, with the entrypoint deciding the role: `api`, `horizon`, `reverb`, `scheduler`. ECS task definitions point at the same image but use different commands.

PHP extensions installed: `pdo_mysql, redis, intl, gd, opcache, bcmath, sodium, igbinary, zip, exif`.

OPcache enabled with preloading of `app/Modules` autoload paths.

## 9.4 Compose for local

```
services:
  mysql:        mysql:8.0       (3306)
  redis:        redis:7-alpine   (6379)
  api:          backend image, entrypoint=api  (8000 → host 8000)
  horizon:      backend image, entrypoint=horizon
  reverb:       backend image, entrypoint=reverb  (8080 → host 8080)
  scheduler:    backend image, entrypoint=scheduler
  mailpit:      mailpit          (1025/8025)
  minio:        s3 fake          (9000/9001)
```

`Make` targets: `make up`, `make migrate`, `make seed`, `make test`, `make tinker`, `make logs`.

## 9.5 Cloud infrastructure (Terraform)

`infrastructure/terraform/` contains modules:

```
infrastructure/
└── terraform/
    ├── modules/
    │   ├── network/        # VPC, subnets, NAT, sg
    │   ├── rds/
    │   ├── elasticache/
    │   ├── ecs-cluster/
    │   ├── ecs-service/    # parameterized for api / horizon / reverb
    │   ├── alb/
    │   ├── s3/
    │   ├── cloudfront-cf/  # we use Cloudflare in front; this is for asset CDN if needed
    │   ├── secrets/
    │   └── observability/  # CloudWatch alarms, OTel collector
    └── envs/
        ├── dev/
        ├── staging/
        ├── prod/
        └── dr/
```

State stored in S3 with DynamoDB lock. Changes go through PR review and `terraform plan` posted to the PR.

## 9.6 CI/CD pipeline

Two pipelines: backend, mobile. Plus a Terraform pipeline.

### Backend (GitHub Actions)

1. **Lint & static** — Pint, Larastan, Phpinsights.
2. **Tests** — Pest unit + feature against MySQL + Redis services. OpenAPI spec generation.
3. **Build image** — multi-arch (`linux/amd64`, future `linux/arm64`); tag with git SHA + branch + (on release) semver.
4. **Push** to ECR.
5. **Deploy**:
   - `main` branch → staging auto-deploy (ECS rolling update, 1 task at a time, health-check gate).
   - Release tags `v*` → prod, requires manual approval in the workflow.
6. **Post-deploy** — run `php artisan migrate --force` as a separate one-off ECS task; smoke tests run against new tasks before they enter the LB.

### Mobile (GitHub Actions + Fastlane)

For each app (customer/driver), each flavor (staging/prod):
1. `flutter analyze` + `flutter test`.
2. Build `.aab` (Android) and `.ipa` (iOS) via Fastlane on macOS runners for iOS, Linux runners for Android.
3. Codepush prevented — all releases go through the stores; rapid fixes via remote config & feature flags.
4. Staging builds auto-uploaded to **Firebase App Distribution**; prod builds upload to TestFlight / Play Internal track, requiring manual promotion.
5. Sentry release + sourcemap upload.

### Terraform

PR-triggered `plan`; merging to `main` runs `apply` for non-prod; prod `apply` requires a manual workflow dispatch.

## 9.7 Database migration strategy

- All migrations are reversible where feasible.
- Zero-downtime rules:
  - Two-step renames (add new, backfill, switch reads, remove old over two releases).
  - Never drop a column referenced by current code.
- Long backfills run as queued jobs, not as part of `php artisan migrate`.
- Schema changes that lock tables > 5 s require an `expand → migrate → contract` plan documented in the PR.

## 9.8 Observability

| Signal | Tool |
|---|---|
| Logs | Stdout in JSON → CloudWatch Logs → Grafana Loki (via collector) |
| Metrics | OpenTelemetry SDK in Laravel; OTel Collector → Grafana Mimir |
| Traces | OpenTelemetry; Tempo backend |
| Mobile errors | Sentry |
| Realtime broker health | Reverb internal metrics scraped by Prometheus exporter |
| Synthetics | Checkly hitting `/health` every 30 s from 3 regions |
| Uptime status page | Atlassian Statuspage |

Key dashboards:
- API health (rps, latency by route group, error rate)
- Realtime (WS connections, message rates, dispatch latency)
- Ride funnel (request → offer → accept → start → complete) with conversion at each step
- Driver supply (online drivers per city / hour heatmap)
- Financial (charges, refunds, payouts, wallet balance growth)
- Cost (RDS CPU, Redis ops, S3 storage, FCM volume)

Alerts go to PagerDuty with severity routing.

## 9.9 Backups & disaster recovery

- **RDS** — automated daily snapshots, 35-day retention; point-in-time recovery (PITR) enabled; cross-region snapshot every 1 h.
- **S3** — versioning + cross-region replication; lifecycle to Glacier for objects > 180 days.
- **Redis** — daily snapshots; treated as **regenerable**: a fresh empty Redis is fine (drivers will rehydrate `drivers:online:*` within ~30 s of reconnect; rides reconcile from MySQL).
- **DR runbook** — documented `docs/runbooks/dr.md` (Phase 4). Tested quarterly with a full failover drill to `dr` env.

## 9.10 Security posture

- TLS via ACM certificates terminating at the ALB; HSTS 1 year on web hosts.
- Cloudflare WAF rules: OWASP core ruleset, custom rules for `/api/v1/auth/*` (bot challenge on excessive volume).
- VPC has only private subnets for app + data tiers; only the ALB is in the public subnet.
- KMS-encrypted RDS, ElastiCache at rest; in-transit TLS for both.
- Mobile pinning: pinned to the Cloudflare-issued public keys for `api.` and `ws.` hostnames, plus one backup pin. Rotation plan documented.
- Secrets rotation:
  - DB password rotated every 90 days (Secrets Manager rotation).
  - SMS / FCM / Stripe API keys rotated every 180 days.
- Image scanning: ECR scan-on-push + Trivy on CI; fail build on `CRITICAL` vulns.
- Dependency scanning: `composer audit`, `flutter pub outdated --mode=security`, Dependabot.
- SOC 2-ish controls (we won't certify on day one, but we wire the controls): access reviews quarterly, prod console MFA required, no shared accounts, change management via PRs.

## 9.11 Cost guardrails

- Reserved instances / Savings Plans for steady-state ECS + RDS once Phase 4 (stable load) is reached.
- S3 lifecycle to Glacier for `live_locations` archives.
- Auto-scaling: ECS API service scales on CPU + p95 latency; Horizon workers scale on queue depth.
- Cloudflare in front absorbs SMS/OTP-flood attempts at the edge.

## 9.12 Release cadence

- Backend: continuous to staging, weekly to prod (Tue 10:00 local), hotfixes on demand.
- Mobile: bi-weekly release train; emergency releases via expedited review when blocking bugs are found.
- Feature flags (`app_configs` table cached in Redis) gate any risky behaviour for staged rollouts.

## 9.13 Runbooks (to be authored in Phase 1+)

- `runbooks/api-incident.md`
- `runbooks/dispatch-degraded.md`
- `runbooks/reverb-down.md`
- `runbooks/db-failover.md`
- `runbooks/payouts-stuck.md`
- `runbooks/security-breach.md`
- `runbooks/dr.md`
