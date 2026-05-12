# Phase 1 UI — Visual Preview

Captured against `claude/hotel-pms-channel-manager-jj7LT` at 1440×900 (desktop) and 390×844 (mobile).
Demo data: 6 reservations across 6 rooms, 2 checked in, 1 checked out and invoiced.

## Desktop

| # | Page | URL | Screenshot |
|---|------|-----|------------|
| 1 | Login                 | `/login`                | [01-login.png](./01-login.png) |
| 2 | Dashboard             | `/dashboard`            | [02-dashboard.png](./02-dashboard.png) |
| 3 | Calendar              | `/calendar`             | [03-calendar.png](./03-calendar.png) |
| 4 | Rooms                 | `/rooms`                | [04-rooms.png](./04-rooms.png) |
| 5 | Reservations list     | `/reservations`         | [05-reservations-list.png](./05-reservations-list.png) |
| 6 | Reservation wizard    | `/reservations/create`  | [06-reservation-wizard.png](./06-reservation-wizard.png) |
| 7 | Reservation detail    | `/reservations/{id}`    | [07-reservation-detail.png](./07-reservation-detail.png) |
| 8 | Invoice               | `/invoices/{id}`        | [08-invoice.png](./08-invoice.png) |
| 9 | Guests                | `/guests`               | [09-guests.png](./09-guests.png) |

## Mobile (iPhone 14 Pro viewport)

- [mobile-02-dashboard.png](./mobile-02-dashboard.png)
- [mobile-03-calendar.png](./mobile-03-calendar.png)
- [mobile-04-rooms.png](./mobile-04-rooms.png)
- [mobile-07-reservation-detail.png](./mobile-07-reservation-detail.png)

## Reproducing locally

```sh
brew install php composer            # if you don't have them
cd ~/Desktop/hangover/backend        # or wherever you cloned the repo
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm install && npm run build
php artisan serve                    # http://127.0.0.1:8000
```

Seeded login: `admin@example.test` / `password`.
