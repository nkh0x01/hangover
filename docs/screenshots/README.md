# Phase 1 UI — Visual Preview

Captured at 1440×900 (desktop, @2x) and 390×844 (mobile, @2x) against a
freshly seeded database with three reservations covering the full state
machine: confirmed (R1 / Anna), checked-in with partial payment (R2 / John),
checked-out with paid invoice (R3 / Maria), plus four additional confirmed
reservations so the calendar isn't empty.

## Mapping to the request

| # | Requested | File |
|---|-----------|------|
| 1 | Login                                  | [01-login.png](./01-login.png) |
| 2 | Dashboard                              | [02-dashboard.png](./02-dashboard.png) |
| 3 | Calendar                               | [03-calendar.png](./03-calendar.png) |
| 4 | Rooms                                  | [04-rooms.png](./04-rooms.png) |
| 5 | Reservations list                      | [05-reservations-list.png](./05-reservations-list.png) |
| 6 | Wizard — Step 1 (dates)                | [06-wizard-step-1-dates.png](./06-wizard-step-1-dates.png) |
| 7 | Wizard — Step 2 (room selection)       | [07-wizard-step-2-room.png](./07-wizard-step-2-room.png) + [07b-wizard-step-2-room-picked.png](./07b-wizard-step-2-room-picked.png) (with selection + quote) |
| 8 | Wizard — Step 3 (guest)                | [08-wizard-step-3-guest.png](./08-wizard-step-3-guest.png) |
| 9 | Wizard — Step 4 (confirm)              | [09-wizard-step-4-confirm.png](./09-wizard-step-4-confirm.png) |
| 10 | Reservation detail                    | [10-reservation-detail-confirmed.png](./10-reservation-detail-confirmed.png) |
| 11 | Payment modal                         | [11-payment-modal.png](./11-payment-modal.png) |
| 12 | Check-in / check-out states           | [12-reservation-detail-checked-in.png](./12-reservation-detail-checked-in.png) and [13-reservation-detail-checked-out.png](./13-reservation-detail-checked-out.png) |
| 13 | Invoice                                | [14-invoice.png](./14-invoice.png) |
| 14 | Mobile dashboard                       | [15-mobile-dashboard.png](./15-mobile-dashboard.png) |
| 15 | Mobile calendar / menu                 | [16-mobile-sidebar-open.png](./16-mobile-sidebar-open.png) (sidebar drawer) and [17-mobile-calendar.png](./17-mobile-calendar.png) |

## Seeded data used for these captures

- 1 property (Hotel Tbilisi, GEL, VAT 18%) · 12 rooms across 4 types
- Reservations:
  - **R1** `R-2605-7FBOBV` — Anna Mikadze, Room 101, tomorrow + 2 nights, **confirmed**
  - **R2** `R-2605-QFSIG7` — John Smith, Room 102, today + 3 nights, **checked-in**, **partial payment**
  - **R3** `R-2605-USNXEG` — Maria Garcia, Room 103, 2 days ago + 2 nights, **checked-out**, **paid**, invoice **HT-2026-000001**
  - Plus Wei Chen, Sara Müller, Levan Tabidze, Niko Pirosmani — confirmed, future, varied sources

## Reproducing locally

```sh
brew install php composer            # if you don't have them
cd ~/Desktop/hangover/backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate:fresh --seed
npm install && npm run build
php artisan serve                    # http://127.0.0.1:8000
```

Seeded login: `admin@example.test` / `password`.
