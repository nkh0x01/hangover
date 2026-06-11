# ქართული წარმოება — Made in Georgia Market

A Georgian small-entrepreneur marketplace platform: Laravel 11 backend + REST API + Blade/Livewire web UI + Filament admin + financing/grants matcher.

**This app is fully isolated from the scooter ride-hailing platform under `/backend`.** Different DB, different ports, different CI workflow. The two apps do not share infrastructure.

## Concept

A platform for products made by Georgian small entrepreneurs and local producers — not just handmade items, but food & beverages, cosmetics, fashion, household goods, agricultural products, and yes, crafts too. The financing module connects sellers to programs like Enterprise Georgia, Rural Development Agency, GITA, and grants.gov.ge.

## Layout

```
marketplace/
├── backend/                  Laravel 11 monolith (API, Filament admin, queues)
│   ├── app/Modules/          Domain modules: Identity, Catalog, Seller, Commerce,
│   │                         Review, Financing, Notification, Cms, Admin
│   ├── lang/ka/              Georgian copy (canonical)
│   ├── resources/views/      Blade + Livewire
│   └── config/marketplace.php Business knobs
├── mobile/                   Flutter app placeholder (deferred — see mobile/README.md)
├── docker-compose.yml        Local dev stack, isolated ports
└── Makefile                  Common dev commands, scoped to this app
```

## Quick start

```bash
cd marketplace
cp backend/.env.example backend/.env
make up           # bring up MySQL, Redis, Meilisearch, MinIO, Mailpit
make install      # composer install + key:generate + migrate + seed
```

Service URLs:

| Service           | URL                                |
|-------------------|------------------------------------|
| Web (Georgian)    | http://localhost:8001              |
| API health        | http://localhost:8001/api/v1/health|
| Filament admin    | http://localhost:8001/admin        |
| Scramble API docs | http://localhost:8001/docs/api     |
| Meilisearch       | http://localhost:7700              |
| MinIO console     | http://localhost:9003              |
| Mailpit UI        | http://localhost:8026              |

## Demo credentials (seeded)

| Role       | Email                            | Password   |
|------------|----------------------------------|------------|
| admin      | admin@marketplace.local          | password   |
| consultant | consultant@marketplace.local     | password   |
| seller     | seller1@marketplace.local        | password   |
| buyer      | buyer1@marketplace.local         | password   |

## Common tasks

```bash
make fresh        # drop, re-migrate, re-seed (destructive)
make test         # Pest test suite
make pint         # format
make stan         # PHPStan L6
make shell        # bash into api container
```

## Locale

Default is `ka` (Georgian). URLs without prefix are Georgian. Browse `/en/*` for English. Add languages by extending `lang/` and `config/localization.php`.

## Ports cheat-sheet (vs scooter `/backend`)

| Service       | Marketplace | Scooter |
|---------------|-------------|---------|
| api           | 8001        | 8000    |
| mysql         | 3307        | 3306    |
| redis         | 6380        | 6379    |
| minio api     | 9002        | 9000    |
| minio console | 9003        | 9001    |
| meilisearch   | 7700        | —       |
| mailpit smtp  | 1026        | 1025    |
| mailpit ui    | 8026        | 8025    |

## Mobile (deferred)

A Flutter app will live under `marketplace/mobile/` in a later phase. The API under `/api/v1/*` is Sanctum-token-based and mobile-ready today.

## Demo funding programs

All seeded programs carry `is_demo=true`. Verify each entry against the official source before flipping the flag off. See `database/seeders/FundingProgramsSeeder.php`.

## Important policy

The financing module **never** auto-submits applications to government programs. It generates a Georgian-language checklist, recommends matching programs, and links to the official `application_url`. Internal consultant workflow exists for hand-holding only.
