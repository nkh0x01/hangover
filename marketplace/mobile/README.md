# Mobile (Flutter) — deferred

This directory is a placeholder for the marketplace's Flutter app.
It is intentionally empty in this phase.

## Plan

A Flutter codebase will produce both Android and iOS apps consuming the
Laravel API at `marketplace/backend/` via Sanctum bearer tokens.
The API exposes all marketplace functionality under `/api/v1/*` (see
`marketplace/backend/routes/api.php` and module-level
`app/Modules/<X>/routes/api.php`).

When work begins, mirror the conventions of the parallel scooter app's
Flutter monorepo under `/mobile/` at the repo root: Melos workspace,
shared `core`, `auth`, `network`, `ui_kit` packages, freezed + riverpod
+ json_serializable codegen.

## Endpoints the mobile app will consume

| Area | Endpoint base |
|------|---------------|
| Auth | `POST /api/v1/auth/{register,login,logout}` + `GET /me` |
| Catalog | `GET /categories`, `GET /products` |
| Sellers | `GET /sellers`, `GET /sellers/{slug}/products` |
| Cart | `GET|POST|PATCH|DELETE /cart[/items]` |
| Checkout | `POST /checkout` |
| Orders | `GET /orders[/{number}]` |
| Seller dashboard | `/seller/*` (verified-seller-only) |
| Financing | `POST /financing/recommendations`, `/financing/applications/*` |
| CMS | `GET /pages/{slug}`, `GET /hero/{key}` |

The API is mobile-ready today.
