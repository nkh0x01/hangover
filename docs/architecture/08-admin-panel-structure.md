# 08 — Admin Panel Structure

## 8.1 Stack

- **Filament 3** on Laravel 11 (same monolith, separate panel).
- Hosted at `https://admin.hangover.app` (separate vhost + ingress rule for IP allowlisting).
- Session auth, 2FA required, IP allowlist optional.
- Each module contributes its Filament resources, pages, and widgets from inside `app/Modules/<Module>/Filament/`.

```
app/Filament/
└── (only cross-module pages, e.g. Dashboard, GlobalSearch)

app/Modules/Identity/Filament/
├── Resources/
│   ├── UserResource.php
│   ├── UserResource/Pages/{List, View, Edit}UserResource.php
│   └── RoleResource.php
└── Widgets/
    └── ActiveUsersWidget.php

app/Modules/Driver/Filament/
├── Resources/
│   ├── DriverResource.php
│   ├── VehicleResource.php
│   └── DriverDocumentResource.php
├── Pages/
│   ├── DriverApprovalQueuePage.php
│   └── LiveDriverMapPage.php
└── Widgets/
    ├── OnlineDriversWidget.php
    └── PendingApprovalsWidget.php

app/Modules/Riding/Filament/
├── Resources/
│   ├── RideResource.php
│   └── RideOfferResource.php
├── Pages/
│   ├── ActiveRidesPage.php
│   ├── DispatcherConsolePage.php
│   └── LiveRideMapPage.php
└── Widgets/
    ├── ActiveRidesWidget.php
    ├── RideStatusBreakdownWidget.php
    └── DispatchLatencyWidget.php

app/Modules/Pricing/Filament/
├── Resources/
│   ├── FareRuleResource.php
│   ├── ZoneResource.php
│   └── SurgeMultiplierResource.php
└── Pages/
    └── SurgeBoardPage.php

app/Modules/Payment/Filament/
├── Resources/
│   ├── PaymentResource.php
│   ├── RefundResource.php
│   └── PayoutResource.php
└── Pages/
    └── PayoutBatchPage.php

app/Modules/Wallet/Filament/
└── Resources/TransactionResource.php

app/Modules/Promotion/Filament/
└── Resources/{PromoCodeResource, PromoRedemptionResource}.php

app/Modules/Support/Filament/
├── Resources/SupportTicketResource.php
├── Resources/FraudFlagResource.php
├── Resources/SosEventResource.php
└── Pages/SosBoardPage.php

app/Modules/Cms/Filament/
├── Resources/{CmsPageResource, AppConfigResource}.php
└── Pages/FeatureFlagsPage.php

app/Modules/Communication/Filament/
└── Pages/BroadcastNotificationPage.php
```

## 8.2 Navigation groups

The Filament sidebar is organized into groups:

1. **Dashboard** — top-level
2. **Operations**
   - Active rides
   - Live driver map
   - Live ride map
   - Dispatcher console
   - SOS board
3. **Drivers**
   - Approval queue
   - All drivers
   - Vehicles
   - Documents
4. **Rides**
   - All rides
   - Ride offers
   - Cancellations
5. **Customers**
   - All users
   - Fraud flags
6. **Finance**
   - Payments
   - Refunds
   - Payouts
   - Transactions
7. **Pricing**
   - Fare rules
   - Zones
   - Surge multipliers
   - Surge board
8. **Promotions**
   - Promo codes
   - Redemptions
9. **Support**
   - Tickets
   - Broadcast notifications
10. **CMS**
    - Pages
    - App configs / feature flags
11. **System**
    - Roles & permissions
    - Audit logs

## 8.3 Dashboard widgets (top page)

Required metrics from the brief, implemented as Filament widgets fed by cached aggregates (5 s TTL):

| Widget | Source |
|---|---|
| Total rides today / this week / this month | `rides` aggregate, materialized via `analytics_rides_daily` table refreshed every 5 min |
| Active rides count | Redis `SCARD rides:active:<city>` |
| Online drivers count | Redis `SCARD drivers:online:<city>` |
| Revenue (gross + net) today | `transactions` sum where `kind='ride_charge'` minus refunds |
| Commission earned today | `rides.commission_amount` sum |
| Cancellation rate (last 24 h) | (`cancelled` count) / (`completed + cancelled` count), broken down by reason |
| Dispatch latency p50/p95 | telemetry metric |
| Acceptance rate (24 h) | offers accepted / offers sent |
| Active SOS events | `sos_events` where `status='open'` |
| New driver applications | `drivers` where `status='pending'` |

Widgets are city-scoped via a top-bar city picker stored in the admin session.

## 8.4 Key resource specs

### DriverResource

Columns: avatar, name, phone, city, status (badge), rating_avg, trips_completed, online (toggle), last_seen_at, created_at.

Filters: status, city, online, rating range, registered between.

Actions: view, edit, **approve** (only when `pending`/`in_review`), **reject**, **suspend**, **unsuspend**, **revoke sessions**, **view live location**, **view documents** (drawer), **view earnings**.

Bulk actions: export to CSV, send broadcast notification, suspend.

Edit form sections:
- Personal details
- Status & approval (with required `approval_notes` on reject/suspend)
- Vehicles (relation manager)
- Documents (relation manager with preview + approve/reject per doc)
- Commission override
- Recent rides (read-only relation manager, last 20)
- Activity log (read-only)

### RideResource

Columns: ulid, customer, driver, city, status, fare, requested_at, accepted_at, completed_at.

Filters: status, city, date range, payment method, has_refund, surge_applied (>1.0).

Actions: view (with map of pickup, dropoff, and trace polyline), **cancel** (admin reason required, refunds preauth), **refund**, **reassign** (dispatch to specific driver), **flag for fraud review**, **export receipt**.

Relations on view: status logs (timeline), offers (list of who declined), messages (chat replay), ratings, payment + refunds, transactions.

### LiveRideMapPage

- Filament custom page rendering a Mapbox/Google map.
- Subscribes (via Laravel Echo + Reverb) to `presence-city.{cityId}.rides` and `.drivers`.
- Markers updated in-place; clicking a ride opens the slide-over with full state.
- Search by ride ulid, customer phone, driver phone.

### DispatcherConsolePage

For the `dispatcher` role. Lists currently `searching` rides that have failed to find a driver in 30 s. Allows manual selection of a driver from a side-panel sorted by distance, with one-click `dispatch-to`. Confirmation required.

### FareRuleResource

Reorderable list per city + vehicle type; effective dates clearly visualized on a timeline widget below the table to prevent overlaps. Form validates that no two active rules overlap for the same (city, vehicle_type, day_of_week, time range).

### PromoCodeResource

CRUD with:
- a "preview" tab that simulates the discount on a sample fare,
- a "redemptions" relation manager showing usage,
- a "deactivate" action (sets `status='paused'`).

### SupportTicketResource

Two-panel layout: ticket details on the left, conversation thread on the right. Internal notes flagged with a different background. Status changes audited.

### SosBoardPage

Live grid of open SOS events with map preview, audio of the original event (if captured), one-click "acknowledge", "call user", "dispatch local emergency", "mark resolved" / "false alarm". Audible alert on new event for the on-call agent.

## 8.5 RBAC matrix

Permission checks are enforced both at the route level (custom REST) and the Filament resource level (`can*` policies + `getEloquentQuery` scoping).

| Permission | Roles |
|---|---|
| `dashboard.view` | all admin roles |
| `livemap.view` | super_admin, ops_admin, dispatcher |
| `user.view` | all admin roles |
| `user.suspend` | super_admin, ops_admin |
| `driver.view` | all admin roles |
| `driver.approve` | super_admin, ops_admin |
| `driver.suspend` | super_admin, ops_admin |
| `ride.view` | all admin roles |
| `ride.cancel` | super_admin, ops_admin |
| `ride.dispatch` | super_admin, dispatcher |
| `refund.create` | super_admin, finance_admin |
| `payout.manage` | super_admin, finance_admin |
| `pricing.manage` | super_admin |
| `promo.manage` | super_admin, ops_admin |
| `support.view` | all admin roles |
| `support.respond` | super_admin, ops_admin, support_agent |
| `fraud.manage` | super_admin, ops_admin |
| `sos.manage` | super_admin, ops_admin |
| `cms.manage` | super_admin |
| `config.manage` | super_admin |
| `audit.view` | super_admin |
| `transaction.view` | super_admin, finance_admin |

## 8.6 Audit & change tracking

- Every mutating action in Filament is audited via Spatie activitylog; properties include `before`, `after`, `request_id`, `ip`, `user_agent`.
- Sensitive actions (driver suspend, refund, fare rule change) require **a typed reason** in a modal and re-prompted password. The reason is stored verbatim in the activity log.

## 8.7 Filament theming

- Brand color palette imported from `core` design tokens (kept in sync via a small generated `tailwind.config.js`).
- Dark mode enabled by default.
- A custom panel logo, favicon, and login backdrop.

## 8.8 Notifications inside admin

Filament's database notifications channel is used for in-panel alerts:
- New SOS event
- Driver approval submitted
- Payment refund failure
- System health degraded

Critical alerts also push to a Slack channel via webhook.
