# Gadget AI Sales Chatbot

A production-ready, omnichannel AI sales ecosystem for **Gadget** — Georgia's
gadget retailer. The system behaves like a **digital sales employee**: it
greets customers, recommends real in-stock products, closes sales, collects
order data, generates payment links when needed, replies to public comments,
and escalates the hard cases to a human via WhatsApp.

It speaks Georgian naturally, adapts tone to each customer, and is built so
the brand never sounds like a cheap auto-replier.

---

## Channels

| Channel              | Inbound                | Outbound              | Comments |
| -------------------- | ---------------------- | --------------------- | -------- |
| WhatsApp Business    | Cloud API webhook      | Cloud API send        | n/a      |
| Facebook Messenger   | Messenger Platform     | Messenger Platform    | n/a      |
| Instagram Direct     | Instagram Messaging    | Instagram Messaging   | n/a      |
| Facebook comments    | Page webhook (`feed`)  | Graph API replies     | yes      |
| Instagram comments   | IG webhook (`comments`)| Graph API replies     | yes      |

All channels share a single message bus, a single AI brain, and a single
unified inbox.

---

## Architecture at a glance

```
                       ┌──────────────────────────────┐
   Meta Webhooks ───▶  │  Webhook Controllers          │ ──▶ Signature verify
                       │  (WhatsApp / FB / IG)         │ ──▶ Normalize payload
                       └──────────────┬───────────────┘
                                      │ enqueue
                                      ▼
                       ┌──────────────────────────────┐
                       │  ProcessIncomingMessage Job   │
                       │  - upsert customer            │
                       │  - upsert conversation        │
                       │  - persist message            │
                       │  - schedule debounced reply   │
                       └──────────────┬───────────────┘
                                      │
                                      ▼  (5–15s debounce window)
                       ┌──────────────────────────────┐
                       │  GenerateAIReply Job          │
                       │  - assemble customer memory   │
                       │  - detect intent              │
                       │  - call product engine        │
                       │  - call Claude w/ tools       │
                       │  - confidence check           │
                       │  - escalate OR reply          │
                       └──────────────┬───────────────┘
                                      │
                                      ▼
                       ┌──────────────────────────────┐
                       │  Channel Driver (out)         │
                       │  typing indicator, media,     │
                       │  smart pacing, retries        │
                       └──────────────────────────────┘
```

Sibling pipelines:

- **Comments pipeline** — short public reply, then invite to DM.
- **Escalation pipeline** — push to owner's WhatsApp with a deep link
  back to the unified inbox.
- **Sales pipeline** — lead → checkout collector → optional payment link
  → order created.

---

## Domain model

- `customers` — one per (platform, platform_user_id) with a unified
  `profile_json` (preferences, budget, ecosystem, phone model, etc.).
- `conversations` — one per channel thread, holds lead status, AI state,
  assigned employee, escalation state.
- `messages` — inbound + outbound, with `is_ai`, `confidence`, `tokens`.
- `comments` — FB/IG public comments and the replies we posted.
- `leads` — sales funnel state, attached product candidates.
- `orders` — collected checkout info + payment status.
- `escalations` — audit trail of every human-handoff with full context.
- `products` — local mirror of the live catalog (synced from gadget.ge).
- `ai_prompts` — versioned system prompts the admin can edit live.
- `audit_logs` — every admin action and every AI decision.

See `database/migrations/` for the full schema.

---

## Human-like behavior

Every inbound message starts a **debounce window** (default 5–15s,
configurable in `config/chatbot.php`). If another message arrives the
window resets — so the bot reads the *complete thought*, not fragments.

Outbound replies are paced based on length (so a 200-character message
takes longer than "okay"), with a typing indicator on channels that
support it. The brain also injects natural micro-pauses on long answers.

---

## AI brain

Backed by the Claude API (`claude-opus-4-7` for hard sales conversations,
`claude-haiku-4-5` for cheap classifications and comment replies). The
prompt is composed from:

1. The brand voice / system prompt (`AiPrompt` model, versioned).
2. Customer memory snapshot.
3. Last N turns of conversation.
4. Tool definitions: `search_products`, `check_stock`,
   `recommend_alternatives`, `create_order_draft`, `generate_payment_link`,
   `escalate_to_human`.
5. A confidence rubric Claude must score itself against. Below
   threshold → escalate, never bluff.

Prompt caching is used on the system prompt + brand guide, so every
turn pays only the delta.

---

## Admin panel

A unified inbox under `/admin` (Blade + Alpine for now, designed to be
swapped for a Vue/Inertia SPA later) shows:

- live conversation list across all 5 channels
- one-click "take over from AI" / "release back to AI"
- customer profile sidebar with memory, lead status, order, notes
- recommended products carousel pulled from the recommendation engine
- escalation banner when the bot has handed off
- analytics dashboard at `/admin/dashboard`

---

## Safety rails (non-negotiable)

The brain is **forbidden** from:

- inventing stock, prices, discounts, warranty terms, or delivery dates
- arguing with customers
- replying when confidence < threshold
- deleting comments
- exposing internal data

All facts come from tools backed by the real catalog. If a tool can't
answer, the bot escalates — it never bluffs.

---

## Repo layout

```
app/
  Http/Controllers/Webhooks/    Meta webhook entry points
  Http/Controllers/Admin/       Unified inbox + dashboard APIs
  Http/Middleware/              Signature verification, rate limits
  Models/                       Eloquent models
  Services/Channels/            Per-platform drivers behind a contract
  Services/AI/                  Claude client, reply engine, prompts
  Services/Products/            Catalog, stock, recommendations
  Services/Sales/               Lead, checkout, payment links
  Services/Comments/            Public-comment responder
  Services/Escalation/          Detector + WhatsApp notifier
  Services/Memory/              Per-customer long-term memory
  Services/Inbox/               Aggregation for the admin panel
  Services/Analytics/           KPI rollups
  Jobs/                         Queue workers (the whole pipeline)
  Console/Commands/             artisan tasks (catalog sync, summaries)
config/
  chatbot.php                   Debounce, working hours, tone presets
  channels.php                  WhatsApp/FB/IG credentials
  ai.php                        Claude API config + models
  escalation.php                Triggers and notify targets
database/migrations/            Schema for the whole system
routes/                         web.php / api.php / webhooks.php
resources/views/admin/          Inbox + dashboard Blade templates
tests/                          Feature + unit tests
```

---

## Phased delivery (status)

| Phase | Scope                                          | Status      |
| ----- | ---------------------------------------------- | ----------- |
| 1     | Architecture + DB + admin skeleton             | this PR     |
| 2     | Meta / WhatsApp integrations                   | this PR     |
| 3     | Unified inbox                                  | this PR     |
| 4     | AI reply engine                                | this PR     |
| 5     | Product recommendation                         | this PR     |
| 6     | Order flow + payment links                     | this PR     |
| 7     | Comment automation                             | this PR     |
| 8     | WhatsApp escalation                            | this PR     |
| 9     | Analytics + optimisation                       | this PR     |
| 10    | Memory + advanced sales intelligence           | this PR     |

This PR ships the **complete foundation** for all 10 phases as a working
Laravel application skeleton. Production hardening (load tests,
penetration tests, real Meta App Review, real catalog sync against
gadget.ge's specific schema) happens once credentials and access are
provided.

---

## Getting started locally

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan queue:work redis &
php artisan serve
```

Webhook endpoints:

```
# Meta (verification handshake on GET, events on POST)
POST /webhooks/whatsapp
POST /webhooks/messenger
POST /webhooks/instagram

# gadget.ge (WooCommerce) push events
POST /webhooks/gadget
```

See `ARCHITECTURE.md` for the deep dive.

---

## gadget.ge integration (WooCommerce REST API)

`config/gadget.php` configures everything the chatbot needs to talk to
gadget.ge. Auth uses the standard WooCommerce consumer-key + secret over
HTTPS Basic.

What we read from WC:

- **Products + stock** — paginated `/products`, mirrored into the local
  `products` table every 15 min (`gadget:sync-products`). Per-branch
  stock is read from `meta_data` keys configurable in `config/gadget.php`.
- **Coupons / promos** — `/coupons` every 30 min (`gadget:sync-coupons`),
  exposed to the AI via the `find_active_coupons` tool.

What we write to WC:

- **Customers** — `CustomerLink` matches by phone → email → WC id, and
  upserts the chat customer into WC's customer registry. The WC id is
  stored in `customers.external_id`.
- **Orders** — when `CheckoutCollector::confirm()` succeeds, `OrderPush`
  POSTs the order to `/orders` with the correct `payment_method`,
  `shipping_lines` and `meta_data`. The WC id lands in
  `orders.external_order_id`; re-pushing the same order is a no-op.

What we receive from WC:

- **Webhooks** at `POST /webhooks/gadget` for `product.*`, `order.*`,
  `coupon.*` topics. HMAC-SHA256 signature is verified against
  `GADGET_WC_WEBHOOK_SECRET`. WC order status flows back here — when WC
  marks an order `processing` or `completed` we update `payment_status`,
  `paid_at`, `fulfilled_at` on the local row.

AI tools that hit gadget.ge:

- `search_products` / `check_stock` / `recommend_alternatives` —
  served from the local mirror (fast, cheap).
- `gadget_live_stock(sku)` — bypasses the mirror for a right-now stock
  read directly from WC (use when the customer is impatient).
- `find_active_coupons({sku?, category?})` — returns valid coupon codes
  the model can mention before closing the sale.

Required env:

```
GADGET_WC_BASE_URL=https://gadget.ge
GADGET_WC_CONSUMER_KEY=ck_xxxxxxxxxxxx
GADGET_WC_CONSUMER_SECRET=cs_xxxxxxxxxxxx
GADGET_WC_WEBHOOK_SECRET=<paste from /wp-admin webhook screen>
```

In WordPress: **WooCommerce → Settings → Advanced → REST API → Add key**
(read/write), then **Advanced → Webhooks** for each topic above pointing
to `/webhooks/gadget`.
