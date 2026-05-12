# Scooter Ride-Hailing Platform — Architecture

Working name: **Hangover Mobility Platform** (project codename, change before launch).

This directory is the single source of truth for the system design before any code is written. It is intentionally implementation-agnostic where possible (no business logic yet) but **prescriptive** where decisions must be locked in to keep the modules cohesive.

## Document index

| # | Document | Purpose |
|---|----------|---------|
| 01 | [System overview](01-system-overview.md) | High-level component diagram, technology choices, non-functional requirements |
| 02 | [Database schema](02-database-schema.md) | All tables, columns, indexes, relationships, migration order |
| 03 | [Laravel backend structure](03-laravel-backend-structure.md) | Modular folder layout, service / repository / DTO conventions |
| 04 | [Flutter app structure](04-flutter-app-structure.md) | Clean-architecture folder layout for the customer + driver apps |
| 05 | [API routes](05-api-routes.md) | Complete REST surface: customer, driver, admin, webhook |
| 06 | [Authentication flow](06-authentication-flow.md) | Phone OTP, Google, Apple, Sanctum tokens, refresh + revocation |
| 07 | [Realtime & ride lifecycle](07-realtime-ride-lifecycle.md) | Ride state machine, dispatch algorithm, WebSocket channels, Redis usage |
| 08 | [Admin panel structure](08-admin-panel-structure.md) | Filament resources, RBAC, dashboards |
| 09 | [Deployment architecture](09-deployment-architecture.md) | Environments, infra, CI/CD, observability |
| 10 | [Phased development roadmap](10-development-roadmap.md) | Sprint-by-sprint plan from foundation to MVP to v1.0 |

## Reading order

For a new engineer joining the project:

1. Read **01 System overview** end-to-end.
2. Skim **10 Roadmap** to understand which phase is active.
3. Deep-read the documents for the modules you will touch.

## Status

| Item | State |
|------|-------|
| Architecture | **Locked for Phase 0–1** (this branch) |
| Database schema | Locked for Phase 0–1 |
| API contract | Draft — frozen at end of Phase 1 |
| Mobile UI/UX | Out of scope of this branch — separate design deliverable |
| Code | **Not started** — next instruction will trigger Phase 0 scaffolding |

## Change control

Anything in `docs/architecture/` is a contract. Changes must:

1. Land in a PR titled `arch: <module>: <change>`.
2. Include rationale and migration impact in the PR body.
3. Be reviewed by at least one backend + one mobile owner.

Schema changes additionally require a corresponding Laravel migration in the same PR once we are past Phase 1.
