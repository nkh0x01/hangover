# Architecture deep dive

This document explains *why* the system is shaped the way it is. The
README is the elevator pitch; this is the engineering rationale.

## 1. Channels behind one contract

Every platform Gadget cares about — WhatsApp, Messenger, Instagram DM,
FB comments, IG comments — has a different webhook shape, a different
auth model, and a different outbound API. We isolate that mess behind
one interface:

```php
// app/Services/Channels/Contracts/ChannelDriver.php
interface ChannelDriver {
    public function platform(): string;
    public function verifyWebhook(Request $r): Response|string;
    public function parseInbound(array $payload): array; // → InboundEvent[]
    public function sendText(string $recipient, string $text): SendResult;
    public function sendMedia(string $recipient, MediaPayload $m): SendResult;
    public function setTyping(string $recipient, bool $on): void;
    public function replyToComment(string $commentId, string $text): SendResult;
}
```

Drivers live in `app/Services/Channels/` — one per platform. Adding a
new channel (e.g. Telegram, Viber) is a single new driver + binding,
zero changes upstream.

## 2. Normalized inbound event

Drivers translate platform payloads into a single shape:

```php
[
    'platform'        => 'whatsapp',
    'platform_msg_id' => 'wamid.xxx',
    'thread_id'       => '<phone>',
    'sender_id'       => '<phone>',
    'sender_name'     => 'Nika',
    'kind'            => 'text|image|audio|interactive|comment',
    'text'            => '...',
    'media'           => [...],
    'received_at'     => 1715800000,
    'raw'             => [...]   // kept for forensics
]
```

The pipeline downstream is platform-agnostic from this point on.

## 3. Debounced reply (the "wait like a human" trick)

Naive bots reply on every message. Real humans wait until you finish
typing. We implement that with a per-conversation debounce:

1. Webhook handler enqueues `ProcessIncomingMessage`.
2. That job upserts everything, then **schedules** `GenerateAIReply`
   with a delay between `chatbot.debounce.min_seconds` and
   `chatbot.debounce.max_seconds` (default 5–15s).
3. The job ID is stored on the conversation row (`pending_reply_job_id`).
4. If another inbound message arrives within the window, we
   **cancel** the previous job and schedule a new one. The bot only
   replies once, to the combined thought.

Cancellation uses Laravel's `Bus::findBatch` pattern plus a flag check
inside the job — even if the queue runner picked it up before we could
delete it, the job sees `should_run=false` and exits.

## 4. The brain

`ReplyEngine` is the conductor. For each turn it:

1. Loads conversation history (`messages` table, last N turns).
2. Hydrates customer memory (preferences, prior orders).
3. Asks `IntentDetector` (cheap Haiku call) for a coarse intent:
   `greeting | browsing | product_question | objection | ready_to_buy |
   complaint | warranty | refund | escalate | spam | off_topic`.
4. If intent is high-risk (complaint, warranty, refund, legal) → straight
   to escalation, no Claude reply.
5. Otherwise calls Claude (Opus 4.7) with tool definitions. Claude can
   call:
   - `search_products(query, filters)` — semantic + filter search
     against our local product mirror.
   - `check_stock(sku, branch?)` — exact stock count.
   - `recommend_alternatives(sku)` — same category, similar price.
   - `create_order_draft(...)` — saves a Lead/Order row in `draft`.
   - `generate_payment_link(order_id)` — only when needed.
   - `escalate_to_human(reason, urgency)` — explicit handoff.
6. The model returns text *and* a confidence score (we ask for it in the
   system prompt). Below `chatbot.ai.min_confidence` → escalate.
7. Reply is split into 1–3 chunks, paced naturally, sent via the driver.

## 5. Product recommendation

`ProductCatalog` is the read-side; `RecommendationEngine` is the
suggestion side. The catalog is mirrored locally from gadget.ge (sync
command runs every 15 min) into the `products` table with:

- `category`, `brand`, `model`, `price`, `price_promo`
- `stock_by_branch_json`
- `compatibility_json` (e.g. for cases, chargers)
- `attributes_json` (color, storage, etc.)
- `embedding` (pgvector or pinecone-via-tool — pluggable)

`RecommendationEngine::suggest(intent, profile, query)`:

1. Coarse filter by category + budget + ecosystem.
2. Semantic rerank using embeddings on title + description.
3. Boost by margin × stock × promo flag.
4. Return top 3 with photos, prices, branch availability.

## 6. Sales flow

We keep the customer *in chat*. The bot:

- sends 1–3 product images via `sendMedia`
- writes a short Georgian description
- handles objections (price, alternatives, ecosystem)
- asks for: name, phone, city, address/branch, delivery preference
- creates an `Order` in `draft` status
- offers payment options:
  - branch pickup (default)
  - cash on delivery
  - card (only here we mint a payment link via the configured PSP)

`CheckoutCollector` is a small state machine that knows what's still
missing and asks for *just one thing* at a time — no forms, no link
spam.

## 7. Comments

`CommentResponder` handles FB and IG comments. Policy is conservative:

- detect sentiment (Haiku call)
- if negative or sensitive → escalate, do NOT reply publicly
- otherwise post a short, branded reply ("მოგწერთ პირადში ❤️")
- attempt a Private Reply (Messenger's `comment_id → DM` feature) to
  pull the customer into a real conversation
- never delete or hide comments — that's a human decision

## 8. Escalation

`EscalationDetector` is rules + classifier:

- intent ∈ {complaint, refund, warranty, legal} → escalate
- sentiment very negative → escalate
- AI confidence below threshold → escalate
- explicit phrases ("მენეჯერი", "ვუჩივი", "გავცვალო") → escalate
- product unavailable but customer ready to buy → escalate (don't lose
  the sale, hand to human)

When triggered, `WhatsAppNotifier` sends the owner's WhatsApp:

```
🚨 [INSTAGRAM] Nika Beridze
"vasiat moval salonshi xval ras vqna" (15:32)

Why: refund_request, conf=0.42
Suggested reply: ...
Open: https://admin.gadget.ge/inbox/c/9381
```

The conversation is flagged `escalated`, AI replies are paused, and any
new inbound message buzzes the owner again.

## 9. Memory

`CustomerMemory` keeps a per-customer JSON document:

```json
{
  "language_style": "formal-casual",
  "ecosystem": "apple",
  "phone_model": "iPhone 15 Pro",
  "budget_range": "300-500",
  "preferred_branch": "Saburtalo",
  "last_categories": ["cases", "chargers"],
  "vip": false,
  "do_not_recommend": ["sku-1234"],
  "last_seen_at": "..."
}
```

It's updated by a small post-turn job that asks Haiku to extract any
new structured facts. The admin can edit this document directly in the
inbox sidebar.

## 10. Admin panel

`/admin` is the unified inbox. It's currently server-rendered Blade +
Alpine + HTMX-style polling (every 5s for the active conversation, every
30s for the list). It's intentionally simple — the contract is the JSON
API under `/api/admin/*`, so we can swap the frontend for Vue/Inertia
without touching the backend.

Admin can:

- read/reply to any thread
- "take over from AI" (sets `conversations.ai_paused=true`)
- "release back to AI"
- edit customer memory inline
- mark VIP / spam / blocked
- view product recommendations the engine has for this customer
- create/edit/refund orders
- approve/reject pending payment links

## 11. Analytics

`AnalyticsRollup` writes hourly aggregates:

- conversations started / answered / escalated
- AI vs human reply ratio
- avg response time
- conversions (order completed within 24h of first AI reply)
- most-requested categories
- top-converting products
- comment volume / sentiment

The dashboard reads aggregates only — no `count(*)` on hot tables.

## 12. Safety rails in code

Three layers:

1. **System prompt** explicitly forbids inventing facts.
2. **Tools-only facts**: prices/stock/discounts/warranty come exclusively
   from tool returns. The prompt says: "if a tool didn't tell you, say
   you'll check and call `escalate_to_human`".
3. **Confidence gate**: every reply carries a confidence score; below
   threshold the reply is suppressed and the conversation escalates.

## 13. Things we explicitly didn't do (yet)

- Multi-tenant SaaS — this is Gadget's single tenant for now.
- Self-serve admin signup — admins are seeded.
- Real-time websocket inbox — polling is good enough for the team size.
- Voice messages — transcription path is stubbed but not wired.

Each of those is a clean extension; none changes the core shape.
