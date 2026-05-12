# 02 — Database Schema

MySQL 8.0, InnoDB, `utf8mb4_0900_ai_ci`. All money columns are `DECIMAL(12,2)` in the **smallest tradable unit of the local currency** (GEL tetri-precision keeps two decimals; for cents-only currencies the same column type works). Currency code is stored separately as `CHAR(3)` ISO-4217.

All times are `TIMESTAMP(3)` (millisecond precision) **UTC**. The app layer converts to user TZ.

Primary keys: `BIGINT UNSIGNED AUTO_INCREMENT` everywhere, plus a public `ULID` column (`CHAR(26)`, unique) for any entity exposed via API to keep IDs unguessable.

Spatial: `POINT SRID 4326` with `SPATIAL INDEX` for any lat/lng we query geographically (driver last position, ride pickup/dropoff). Redis owns the *hot* geo index; MySQL keeps the canonical history.

Soft deletes (`deleted_at`) only where business needs it: `users`, `vehicles`, `promo_codes`, `cms_pages`. Rides and money tables are never soft-deleted (append-only audit semantics).

## 2.1 Entity overview

```
identity:        users  ─┬─ user_devices
                         ├─ user_oauth_identities
                         ├─ phone_verifications
                         └─ favorite_addresses

rbac:            roles ─┬─ permissions
                        └─ user_roles

driver:          drivers ─┬─ vehicles
                          ├─ driver_documents
                          ├─ driver_shifts
                          └─ driver_availability (cache table)

geo:             cities ─┬─ zones (polygons)
                         └─ live_locations (history, partitioned)

riding:          rides ─┬─ ride_status_logs
                        ├─ ride_offers
                        ├─ ride_route_points (polyline, post-trip)
                        ├─ ride_messages (chat)
                        └─ ratings

pricing:         fare_rules
                 surge_multipliers
                 fare_estimates (ephemeral, 30 min TTL)

payment:         payment_methods
                 payments
                 refunds
                 payouts

wallet:          wallets ─── transactions

promotion:       promo_codes ─── promo_redemptions

communication:   notifications
                 notification_preferences
                 sms_log

support:         support_tickets ─── support_messages
                 fraud_flags
                 sos_events
                 audit_logs

cms:             cms_pages
                 app_configs (key/value, JSON values)
```

## 2.2 Migration order

Migrations must be created in this dependency order (one file each, prefixed with timestamp). Listed by **logical sequence**, not by timestamp.

1. `users`
2. `roles`, `permissions`, `permission_role`, `role_user`, `model_has_permissions` *(Spatie packages will generate most; we extend)*
3. `user_devices`
4. `user_oauth_identities`
5. `phone_verifications`
6. `favorite_addresses`
7. `cities`
8. `zones`
9. `drivers`
10. `vehicles`
11. `driver_documents`
12. `driver_shifts`
13. `live_locations` (partitioned by day)
14. `fare_rules`
15. `surge_multipliers`
16. `fare_estimates`
17. `rides`
18. `ride_status_logs`
19. `ride_offers`
20. `ride_route_points`
21. `ride_messages`
22. `ratings`
23. `wallets`
24. `transactions`
25. `payment_methods`
26. `payments`
27. `refunds`
28. `payouts`
29. `promo_codes`
30. `promo_redemptions`
31. `notifications`, `notification_preferences`, `sms_log`
32. `support_tickets`, `support_messages`
33. `fraud_flags`
34. `sos_events`
35. `audit_logs` (or use Spatie activitylog table)
36. `cms_pages`
37. `app_configs`

## 2.3 Table definitions

Notation: `PK` primary key, `FK` foreign key, `U` unique, `IDX` regular index, `SP` spatial index.

### identity

**users**
| col | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| ulid | CHAR(26) | U |
| type | ENUM('customer','driver','admin','dispatcher') | IDX |
| first_name | VARCHAR(80) | |
| last_name | VARCHAR(80) | |
| phone_e164 | VARCHAR(20) | U (nullable for admin-only users) |
| phone_verified_at | TIMESTAMP(3) NULL | |
| email | VARCHAR(190) NULL | U sparse |
| email_verified_at | TIMESTAMP(3) NULL | |
| password | VARCHAR(255) NULL | nullable — phone-OTP users may never set one |
| avatar_path | VARCHAR(255) NULL | S3 key |
| locale | ENUM('ka','en','ru') | default 'ka' |
| status | ENUM('active','suspended','banned','deleted') | IDX |
| referral_code | CHAR(8) | U |
| referred_by_user_id | BIGINT UNSIGNED NULL | FK users.id |
| last_seen_at | TIMESTAMP(3) NULL | |
| created_at, updated_at, deleted_at | | |

**user_devices**
Holds one row per active mobile install. Tokens are bound to a device.
| col | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | FK |
| device_uuid | CHAR(36) | (user_id, device_uuid) U |
| platform | ENUM('ios','android','web') | |
| app_version | VARCHAR(20) | |
| os_version | VARCHAR(20) | |
| fcm_token | VARCHAR(255) NULL | |
| voip_token | VARCHAR(255) NULL | iOS PushKit, for incoming-call screens (driver app) |
| push_enabled | BOOLEAN | default 1 |
| last_active_at | TIMESTAMP(3) | |
| revoked_at | TIMESTAMP(3) NULL | |

**user_oauth_identities** — for Google/Apple linkage.
| user_id, provider ENUM('google','apple'), provider_user_id VARCHAR(190), email VARCHAR(190) NULL |
U on (provider, provider_user_id).

**phone_verifications**
| id, phone_e164, code_hash CHAR(60) (bcrypt), purpose ENUM('signup','login','rebind'), attempts TINYINT, sent_at, expires_at, consumed_at NULL, ip VARCHAR(45), user_agent VARCHAR(255) |
IDX (phone_e164, expires_at).

**favorite_addresses**
| id, user_id, label VARCHAR(40), address_text VARCHAR(255), location POINT SRID 4326, place_id VARCHAR(255) NULL, is_home BOOLEAN, is_work BOOLEAN |
SP on location.

### rbac

Use Spatie `laravel-permission` tables: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`. Guard names: `web` (admin) and `sanctum` (API).

Seeded roles:
- `super_admin` — full access.
- `ops_admin` — driver approval, ride monitor, refunds.
- `finance_admin` — payouts, transactions.
- `support_agent` — tickets, read-only ride view, cannot refund.
- `dispatcher` — manual dispatch & re-offer.
- `driver` — driver app scope.
- `customer` — customer app scope.

### driver

**drivers** — extends user (1:1). A `users.type = 'driver'` always has a `drivers` row.
| col | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | U, FK users.id |
| city_id | BIGINT UNSIGNED | FK cities.id |
| status | ENUM('pending','in_review','approved','rejected','suspended') | IDX |
| approval_notes | TEXT NULL | |
| approved_at | TIMESTAMP(3) NULL | |
| approved_by_user_id | BIGINT UNSIGNED NULL | FK users.id |
| online | BOOLEAN | IDX |
| online_since | TIMESTAMP(3) NULL | |
| current_vehicle_id | BIGINT UNSIGNED NULL | FK vehicles.id |
| rating_avg | DECIMAL(3,2) | denormalized, recalculated nightly |
| rating_count | INT UNSIGNED | |
| trips_completed | INT UNSIGNED | |
| commission_rate_override | DECIMAL(5,4) NULL | overrides city default |
| id_number_encrypted | VARBINARY(255) NULL | |
| iban_encrypted | VARBINARY(255) NULL | |
| created_at, updated_at | | |

**vehicles**
| id, driver_id FK, type ENUM('scooter_electric','scooter_petrol','moped','bicycle_electric'), brand VARCHAR(60), model VARCHAR(60), plate VARCHAR(20), color VARCHAR(30), year SMALLINT, vin VARCHAR(40) NULL, is_active BOOLEAN, photos JSON (array of S3 keys), telemetry_supported BOOLEAN default 0, created_at, updated_at, deleted_at |
U (plate) per city; IDX (driver_id, is_active).

**driver_documents**
| id, driver_id FK, doc_type ENUM('id_front','id_back','license_front','license_back','insurance','vehicle_registration','selfie_with_id'), file_path VARCHAR(255), file_sha256 CHAR(64), expires_on DATE NULL, status ENUM('pending','approved','rejected'), reviewed_by_user_id NULL, reviewed_at NULL, review_notes TEXT NULL |
IDX (driver_id, doc_type, status). One latest row per (driver_id, doc_type) by `created_at DESC`.

**driver_shifts** — append-only log of online/offline transitions.
| id, driver_id, started_at, ended_at NULL, started_lat, started_lng, ended_lat NULL, ended_lng NULL, online_duration_seconds INT generated, total_distance_km DECIMAL(8,2) NULL, total_earnings DECIMAL(12,2) NULL |

### geo

**cities**
| id, country_code CHAR(2), name VARCHAR(80), slug VARCHAR(80) U, timezone VARCHAR(50), default_currency CHAR(3), default_commission_rate DECIMAL(5,4), is_active BOOLEAN, center POINT SRID 4326, bounding_polygon POLYGON SRID 4326 |

**zones** — surge / service-area polygons used in pricing and dispatch filtering.
| id, city_id FK, name VARCHAR(80), polygon POLYGON SRID 4326, kind ENUM('service_area','surge','no_go','airport','event'), priority TINYINT |
SP on polygon.

**live_locations** — append-only history (canonical), **partitioned by RANGE on `recorded_at` daily**. Retention: 90 days hot, archived to S3 monthly. Redis holds the moving window.
| id BIGINT UNSIGNED PK, driver_id, ride_id NULL, recorded_at TIMESTAMP(3), location POINT SRID 4326, heading SMALLINT (0-359), speed_kmh DECIMAL(5,2), accuracy_m DECIMAL(5,1), battery_pct TINYINT NULL, source ENUM('mobile_gps','telematics') |
IDX (driver_id, recorded_at).

### pricing

**fare_rules** — one row per city + vehicle type + time slot.
| id, city_id, vehicle_type ENUM(...), name VARCHAR(60), base_fare DECIMAL(8,2), price_per_km DECIMAL(8,2), price_per_min DECIMAL(8,2), minimum_fare DECIMAL(8,2), booking_fee DECIMAL(8,2), commission_rate DECIMAL(5,4), free_waiting_minutes TINYINT, waiting_fee_per_min DECIMAL(8,2), cancellation_fee DECIMAL(8,2), active_from DATETIME, active_until DATETIME NULL, day_of_week_mask TINYINT (bitmask Mon=1..Sun=64), starts_at_local TIME, ends_at_local TIME |
IDX (city_id, vehicle_type, active_from).

**surge_multipliers** — current and forecast multipliers per zone.
| id, zone_id, multiplier DECIMAL(3,2) (1.00 – 5.00), valid_from, valid_until, source ENUM('manual','algorithm') |
IDX (zone_id, valid_from).

**fare_estimates** — ephemeral, 30-minute TTL, referenced by ride creation to lock the quoted price.
| id, ulid U, customer_id, city_id, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng, distance_km, duration_min, base_fare, surge_multiplier, promo_code_id NULL, total_amount, currency, expires_at, created_at |

### riding

**rides** — the central entity.
| col | type | notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| ulid | CHAR(26) | U — public identifier |
| customer_id | BIGINT UNSIGNED | FK users.id |
| driver_id | BIGINT UNSIGNED NULL | FK drivers.id (NULL until accepted) |
| vehicle_id | BIGINT UNSIGNED NULL | FK vehicles.id |
| city_id | BIGINT UNSIGNED | FK cities.id |
| status | ENUM('requested','searching','offered','accepted','driver_arriving','driver_arrived','in_progress','completed','cancelled','no_drivers','failed') | IDX |
| cancellation_reason | ENUM('customer_cancelled','driver_cancelled','no_driver_found','timeout','payment_failed','admin_cancelled','sos') NULL | |
| cancellation_by_user_id | BIGINT UNSIGNED NULL | |
| pickup_address | VARCHAR(255) | |
| pickup_location | POINT SRID 4326 NOT NULL | SP |
| dropoff_address | VARCHAR(255) | |
| dropoff_location | POINT SRID 4326 NOT NULL | SP |
| fare_estimate_id | BIGINT UNSIGNED NULL | FK fare_estimates.id |
| quoted_amount | DECIMAL(12,2) | locked at request time |
| final_amount | DECIMAL(12,2) NULL | computed at completion |
| surge_multiplier | DECIMAL(3,2) | |
| distance_km | DECIMAL(8,3) NULL | |
| duration_seconds | INT UNSIGNED NULL | |
| waiting_seconds | INT UNSIGNED NULL | |
| currency | CHAR(3) | |
| payment_method | ENUM('cash','card','wallet','apple_pay','google_pay') | |
| payment_id | BIGINT UNSIGNED NULL | FK payments.id |
| promo_code_id | BIGINT UNSIGNED NULL | |
| commission_amount | DECIMAL(12,2) NULL | |
| driver_earnings | DECIMAL(12,2) NULL | |
| requested_at | TIMESTAMP(3) | |
| accepted_at | TIMESTAMP(3) NULL | |
| arriving_at | TIMESTAMP(3) NULL | |
| arrived_at | TIMESTAMP(3) NULL | |
| started_at | TIMESTAMP(3) NULL | |
| completed_at | TIMESTAMP(3) NULL | |
| cancelled_at | TIMESTAMP(3) NULL | |
| customer_rating | TINYINT NULL | mirror; canonical in ratings table |
| driver_rating | TINYINT NULL | mirror |
| created_at, updated_at | | |

Indexes: (customer_id, requested_at), (driver_id, requested_at), (city_id, status, requested_at), (status, requested_at), SP(pickup_location), SP(dropoff_location).

**ride_status_logs** — append-only state-machine audit.
| id, ride_id, from_status, to_status, actor_type ENUM('system','customer','driver','admin','dispatcher'), actor_id NULL, reason VARCHAR(120) NULL, payload JSON NULL, occurred_at TIMESTAMP(3) |
IDX (ride_id, occurred_at).

**ride_offers** — every driver we offered the ride to during dispatch.
| id, ride_id, driver_id, offered_at, expires_at, response ENUM('pending','accepted','rejected','timeout'), responded_at NULL, distance_to_pickup_m INT, eta_seconds INT |
U (ride_id, driver_id).

**ride_route_points** — actual polyline written **after** trip ends (decimated GPS trace).
| id, ride_id, seq INT, location POINT SRID 4326, recorded_at, speed_kmh |
IDX (ride_id, seq).

**ride_messages** — in-app chat between customer and driver, scoped to a ride.
| id, ride_id, sender_user_id, body VARCHAR(1000) NULL, type ENUM('text','quick','system','image'), attachment_path NULL, sent_at, read_at NULL |
IDX (ride_id, sent_at).

**ratings**
| id, ride_id, rater_user_id, ratee_user_id, score TINYINT (1-5), tags JSON NULL, comment VARCHAR(500) NULL, created_at |
U (ride_id, rater_user_id).

### payment

**payment_methods** — saved customer cards / wallets (tokenized; we never store PANs).
| id, user_id, provider ENUM('stripe','bog','tbc_pay','apple_pay','google_pay'), provider_method_id VARCHAR(255), brand VARCHAR(20), last4 CHAR(4), exp_month TINYINT, exp_year SMALLINT, is_default BOOLEAN, status ENUM('active','expired','removed'), created_at |

**payments** — one row per ride charge attempt (a ride can have multiple if retried).
| id, ride_id, customer_id, provider, provider_intent_id VARCHAR(190) NULL, method ENUM('cash','card','wallet','apple_pay','google_pay'), amount DECIMAL(12,2), currency, status ENUM('pending','authorized','captured','failed','refunded','partially_refunded','cancelled'), failure_code VARCHAR(60) NULL, captured_at NULL, raw_response JSON NULL, created_at, updated_at |
IDX (ride_id), IDX (provider_intent_id).

**refunds**
| id, payment_id, amount, currency, reason VARCHAR(120), initiated_by_user_id, status ENUM('pending','succeeded','failed'), provider_refund_id NULL, created_at |

**payouts** — driver bank transfers.
| id, driver_id, amount, currency, period_start DATE, period_end DATE, status ENUM('pending','processing','paid','failed'), provider ENUM('stripe_connect','manual_bank'), provider_payout_id NULL, processed_at NULL, created_at |

### wallet

**wallets** — one per user (customer or driver). Balance is `DECIMAL(12,2)`, **never** updated in place: rebuilt from `transactions` for audit. The `balance_cached` column is denormalized and refreshed in the same transaction as the inserting `transactions` row.
| id, user_id U, currency, balance_cached DECIMAL(12,2), held_amount DECIMAL(12,2), updated_at |

**transactions** — append-only ledger.
| id, ulid U, wallet_id, kind ENUM('topup','ride_charge','ride_payout','refund','promo_credit','referral_bonus','withdrawal','adjustment','hold','release'), direction ENUM('credit','debit'), amount DECIMAL(12,2), currency, ride_id NULL, payment_id NULL, payout_id NULL, balance_after DECIMAL(12,2), description VARCHAR(255), meta JSON NULL, occurred_at TIMESTAMP(3) |
IDX (wallet_id, occurred_at), IDX (ride_id), IDX (kind, occurred_at).

### promotion

**promo_codes**
| id, code VARCHAR(40) U, kind ENUM('percent_off','fixed_off','free_ride','wallet_credit'), value DECIMAL(8,2), currency NULL, max_uses INT NULL, max_uses_per_user TINYINT, min_ride_amount DECIMAL(8,2) NULL, applicable_city_ids JSON NULL, valid_from, valid_until, status ENUM('active','paused','expired'), created_by_user_id, created_at, updated_at, deleted_at |

**promo_redemptions**
| id, promo_code_id, user_id, ride_id NULL, amount_applied DECIMAL(8,2), redeemed_at |
U (promo_code_id, user_id, ride_id).

### communication

**notifications** — Laravel's polymorphic `notifications` table (UUID id, `notifiable_*`, `type`, `data` JSON, `read_at`).

**notification_preferences**
| user_id PK, channel_push BOOLEAN, channel_sms BOOLEAN, channel_email BOOLEAN, marketing BOOLEAN |

**sms_log** — for cost tracking and abuse detection.
| id, phone_e164, purpose, provider VARCHAR(40), provider_msg_id VARCHAR(120), cost DECIMAL(8,4) NULL, status ENUM('queued','sent','delivered','failed'), sent_at, delivered_at NULL |

### support

**support_tickets**
| id, ulid U, user_id, ride_id NULL, category ENUM('payment','driver_behaviour','lost_item','app_bug','safety','other'), subject VARCHAR(140), status ENUM('open','in_progress','waiting_user','resolved','closed'), priority ENUM('low','normal','high','urgent'), assigned_to_user_id NULL, created_at, updated_at, closed_at NULL |

**support_messages**
| id, ticket_id, sender_user_id, body TEXT, attachments JSON NULL, internal_note BOOLEAN, created_at |

**fraud_flags**
| id, user_id, kind ENUM('multi_account','payment_chargeback','manipulated_location','document_forgery','ride_fraud','abuse'), severity ENUM('info','warn','block'), evidence JSON, raised_by ENUM('system','admin'), raised_by_user_id NULL, resolved_at NULL, resolved_by_user_id NULL, resolution_notes TEXT NULL, created_at |

**sos_events**
| id, ride_id NULL, user_id, location POINT SRID 4326, body TEXT NULL, status ENUM('open','acknowledged','resolved','false_alarm'), acknowledged_by_user_id NULL, acknowledged_at NULL, resolved_at NULL, created_at |

**audit_logs** — admin actions and sensitive mutations. (Spatie activitylog table; we add `ip`, `device_uuid`, `request_id` to the `properties` JSON.)

### cms

**cms_pages**
| id, slug U, locale CHAR(2), title VARCHAR(180), body LONGTEXT, status ENUM('draft','published'), published_at NULL, created_by_user_id, updated_at, deleted_at |

**app_configs** — runtime feature flags and tunables. Loaded into Redis on boot, invalidated on write.
| key VARCHAR(120) PK, value JSON, scope ENUM('global','city'), city_id NULL, description VARCHAR(255), updated_by_user_id, updated_at |

## 2.4 Critical indexes & query patterns

| Query | Index used |
|---|---|
| Nearest N drivers to a point | **Redis** `GEOSEARCH drivers:online:<city> FROMLONLAT lon lat BYRADIUS r km ASC COUNT n`. Fall back to MySQL `ST_Distance_Sphere` only for admin live-map historical replays. |
| Customer's ride history (paginated) | `(customer_id, requested_at DESC)` on `rides` |
| Driver's earnings between dates | `(driver_id, occurred_at)` on `transactions` filtered by `kind IN ('ride_payout','adjustment','promo_credit')` |
| Active rides board (admin) | `(status, requested_at)` on `rides` where status in active set |
| Heatmap of demand | aggregated nightly to `analytics_demand_hex` (Phase 5+, not in this schema) |

## 2.5 Concurrency & invariants

- **Driver-double-assignment is the cardinal bug to prevent.** A driver may be on at most one ride with `status NOT IN (completed, cancelled, no_drivers, failed)`. Enforced by a **partial unique index expression** via a generated column:
  - `active_driver_lock` = `driver_id` when `status IN (offered, accepted, driver_arriving, driver_arrived, in_progress)`, NULL otherwise.
  - `UNIQUE KEY uniq_active_driver (active_driver_lock)`.
- **Customer-double-request** prevented similarly:
  - `active_customer_lock` = `customer_id` when `status IN (requested, searching, offered, accepted, driver_arriving, driver_arrived, in_progress)`, NULL otherwise.
  - `UNIQUE KEY uniq_active_customer (active_customer_lock)`.
- Money mutations always wrapped in a DB transaction that:
  1. `SELECT ... FOR UPDATE` the wallet row.
  2. Insert into `transactions`.
  3. Update `wallets.balance_cached` to match `balance_after` from the new row.
- Idempotency: any external-facing mutating endpoint requires an `Idempotency-Key` header; stored 24 h in Redis `idem:{user_id}:{key} = response_hash`.

## 2.6 Retention & archival

| Table | Retention online | Archive |
|---|---|---|
| `live_locations` | 90 days | S3 Parquet daily; analytics cluster |
| `ride_route_points` | 1 year | S3 cold |
| `ride_messages` | 1 year | S3 cold, then delete |
| `sms_log` | 6 months | delete |
| `audit_logs` | 7 years | required for compliance |
| `phone_verifications` | 30 days | delete |
| User accounts | until deletion request + 30-day grace | hard delete; ride financial rows retained anonymized (FK set to a `deleted_user` sentinel) |

## 2.7 GDPR considerations

- `users.deleted_at` triggers a queued job `RedactUserData` that:
  - replaces PII columns with `'[redacted]'` and nulls `phone_e164` / `email`,
  - removes `avatar_path` blob,
  - drops `user_devices` rows,
  - keeps `rides` and `transactions` linked but reassigns to a `deleted_user` placeholder id for accounting consistency.
- A separate **export** job emits a JSON+CSV bundle for the user upon request.
