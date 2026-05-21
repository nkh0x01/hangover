# Hangover Mobility Platform

A scooter ride-hailing platform: Laravel 11 backend, Flutter customer + driver apps, Filament admin panel, real-time dispatch.

> Note: the top-level `app/` directory contains a legacy IBSU student project (HTML5 webcam demo) preserved for history. The new platform lives under `backend/` and `mobile/`.

## Repository layout

```
/
├── backend/                  Laravel 11 monolith (API, Filament admin, queues, Reverb broker)
├── mobile/                   Flutter monorepo (Melos): customer_app, driver_app, shared packages
├── docs/
│   ├── architecture/         Source-of-truth design docs (locked for Phase 0–1)
│   └── phase-0/              Phase 0 deliverables & onboarding
├── docker-compose.yml        Local dev environment
├── Makefile                  Common dev commands
├── .github/workflows/        CI/CD: backend + mobile + Terraform
└── app/                      Legacy IBSU project (unrelated, do not touch)
```

## Quick start

```bash
# 1. Clone & enter
git clone https://github.com/nkh0x01/hangover.git && cd hangover

# 2. Bootstrap backend
cp backend/.env.example backend/.env
make up               # bring up the stack
make install          # composer install + key:generate + migrate + seed
make logs

# 3. Bootstrap mobile
cd mobile
dart pub global activate melos
melos bootstrap
melos run gen         # codegen for freezed / json_serializable / riverpod
```

Local service URLs:

| Service      | URL                              |
|--------------|----------------------------------|
| API          | http://localhost:8000            |
| Admin panel  | http://localhost:8000/admin      |
| Horizon      | http://localhost:8000/horizon    |
| Telescope    | http://localhost:8000/telescope  |
| Reverb WS    | ws://localhost:8080              |
| MailPit UI   | http://localhost:8025            |
| MinIO UI     | http://localhost:9001            |

## Documentation

| Doc | Purpose |
|---|---|
| [docs/architecture/README.md](docs/architecture/README.md) | The 11-document architecture contract |
| [docs/phase-0/deliverables.md](docs/phase-0/deliverables.md) | Phase 0+1 deliverables and how to run them |

## Branches

Active development branches follow the pattern `claude/<slug>-<id>`.
- `master` — protected, do not push directly
- `claude/scooter-platform-architecture-Wvmeu` — current Phase 0+1 work

## Contributing

- Run `make pint && make stan && make test` before pushing.
- All PRs must link the architecture doc that justifies any non-obvious decision.
- No secrets committed. `.env.example` is the source of truth for required keys.
