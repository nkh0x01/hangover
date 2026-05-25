# კრეატორები.ge — Georgian Creator Marketplace

A modern, mobile-first marketplace that connects Georgian content creators
(TikTok, Instagram Reels, YouTube, UGC, photographers, videographers,
influencers) with businesses, brands, startups, and private clients.

Built as a Next.js 14 (App Router) + TypeScript + Tailwind app. Backend
foundation is in place (API routes, Prisma schema, seed) and the frontend
runs on rich sample Georgian data.

## What's inside

```
creator-marketplace/
├── app/                       # Next.js App Router pages
│   ├── page.tsx               # Homepage (hero, featured creators, categories, how it works, testimonials, CTA)
│   ├── marketplace/           # Catalog + filters + search
│   ├── creator/[slug]/        # Creator profile (portfolio, services, reviews, audience)
│   ├── service/[id]/          # Service detail + sticky order card
│   ├── checkout/[serviceId]/  # 5-step order flow with escrow + Georgian payment placeholders
│   ├── auth/                  # Login + register (role pick: creator/client) + contract step
│   │   └── register/contract/ # Mandatory platform agreement signing (anti-circumvention)
│   ├── dashboard/creator/     # Creator dashboard (orders, services, resume + demo upload, analytics)
│   ├── dashboard/client/      # Client dashboard (orders, saved creators, downloads)
│   ├── admin/                 # Admin panel (approve creators, manage categories, view commission)
│   ├── messages/              # In-app messenger with on-platform-only guard
│   ├── about/ contact/ faq/   # Marketing pages
│   ├── api/                   # REST endpoints (creators, services, orders, messages, resume, agreements)
│   └── globals.css            # Tailwind + design tokens
├── components/                # Reusable UI (Header, Footer, CreatorCard, ServiceCard, FilterSidebar, ResumeUpload, DemoUpload, WatermarkedMedia, MessageComposer, ...)
├── lib/
│   ├── types.ts               # Domain types
│   ├── i18n.ts                # Georgian + English dictionary + formatGEL
│   ├── contact-guard.ts       # Phone/email/handle redaction (middle-man protection)
│   └── data/                  # Seed data: creators, services, categories, reviews, orders, messages, users, agreements
├── prisma/
│   ├── schema.prisma          # Full DB schema (User, Creator, Client, Service, Order, Payment, Conversation, Message, Review, Agreement, Notification)
│   └── seed.ts                # Optional seeder skeleton
├── tailwind.config.ts
├── tsconfig.json
├── next.config.js
├── package.json
└── .env.example
```

## Quick start

```bash
cd creator-marketplace
cp .env.example .env
npm install
npm run db:push   # create the SQLite DB at prisma/dev.db
npm run db:seed   # seed all categories, creators, services, orders, users
npm run dev
# open http://localhost:3000
```

The seed creates these demo logins:

| Email                          | Password   | Role    |
|--------------------------------|------------|---------|
| `admin@kreatorebi.ge`          | `admin1234`  | ADMIN   |
| `nino-beridze@kreatorebi.ge`   | `creator123` | CREATOR |
| `salome-gabunia@kreatorebi.ge` | `creator123` | CREATOR |
| `tata@mera.ge`                 | `client123`  | CLIENT  |
| `partnerships@wolt.ge`         | `client123`  | CLIENT  |

The login page has one-click buttons that pre-fill each role.

## Authentication

NextAuth.js v4 with the Credentials provider, JWT sessions (30-day rolling),
and bcrypt password hashing.

- `lib/auth.ts` — NextAuth options, password hashing helper
- `lib/session.ts` — `getCurrentUser()`, `requireUser()`, `requireRole()`
- `middleware.ts` — route protection:
  - `/dashboard/*`, `/messages/*`, `/checkout/*`, `/orders/*` → must be authed
  - `/admin/*` → must be ADMIN
  - role mismatch redirects between creator/client dashboards
- `app/api/auth/[...nextauth]/route.ts` — NextAuth handler
- `app/api/register/{creator,client}/route.ts` — registration (bcrypt + Prisma)

The `User` model in Prisma has `accounts` + `sessions` relations so an OAuth
provider (Google, Apple) can be added later with no schema change — just
plug in another Provider in `authOptions`.

## Order flow (real DB-backed)

End-to-end the journey is:

1. Client visits `/checkout/[serviceId]`, fills the brief, clicks "გადახდა"
2. `POST /api/payments/create` — creates an `Order` (status `NEW`) + `Payment`
   row + provider intent, returns the BOG (or mock) redirect URL
3. User pays on the hosted page → `POST /api/payments/webhook/bog` flips
   the payment to `held` AND the order to `AWAITING_CREATOR`
4. Creator opens `/orders/[id]` → accepts (→ `IN_PROGRESS`) → submits a
   deliverable URL via the Submit button (→ `SUBMITTED`)
5. Client opens the same order → "მიღება + შეფასება" opens the review
   modal; on submit the order goes to `COMPLETED`, the review is saved,
   creator's `rating` + `reviewCount` are recomputed, and
   `POST /api/payments/release` is called to release escrow

All transitions validated by `lib/enums.ts → ORDER_TRANSITIONS` so bad
moves return 422. Per-role allowlists in
`/api/orders/[id]/transition` so a client can't accept their own order
or a creator can't approve their own work.

The order detail page (`/orders/[id]`) shows the status timeline,
brief, deliverables, review, payment breakdown, deadline, and the
participants — with role-specific action buttons.

## File uploads + watermarking

`POST /api/uploads` accepts multipart/form-data with `file` + `purpose`
(`portfolio` | `demo` | `deliverable` | `brief`).

- Images (JPG/PNG/WEBP) are passed through **sharp** with a composed SVG
  overlay (`lib/storage.ts → watermarkImage`): brand-violet
  `კრეატორები.ge` badge top-left, diagonal repeating watermark tiles,
  `© 2026 · PREVIEW` bottom-right.
- Videos (MP4/MOV) are stored as-is; the `<WatermarkedMedia>` component
  overlays the watermark in the browser. Replace with a real ffmpeg
  pipeline (`drawtext` + scaled logo) in production.
- Originals land in `public/uploads/originals/` (private — never linked
  publicly). Previews land in `public/uploads/preview/` (the URL surface
  served to the world).
- For creator demo / portfolio uploads, a `PortfolioItem` row is created
  automatically so the public profile updates immediately.
- For deliverables, only the `previewUrl` is returned by the API —
  the original is released to the client only after escrow release.

Caps: 50 MB per file. Accepted MIMEs: jpeg, png, webp, mp4, quicktime, pdf.

## Design system

- **Accent**: `brand-600` (`#7c3aed`) — modern violet, premium but friendly.
- **Ink scale**: slate gray for text and borders.
- **Type**: `Noto Sans Georgian` + `Inter` fallback, via Google Fonts.
- **Currency**: GEL (`₾`) everywhere.
- **Locales**: Georgian primary, English toggle in the header (stored in
  `localStorage` + cookie).

## Platform rules — middle-man protection

The platform sits in the middle of every deal. Two safeguards are wired in:

### 1. Mandatory agreement at sign-up

After registration (creator or client), the user is redirected to
`/auth/register/contract?type=...` to read and sign the platform agreement
(`lib/data/agreements.ts`). The contract is split into common clauses + role-
specific clauses, with a **summary box** at the top:

- All payments only on-platform (no off-platform circumvention, 24-month
  exclusivity from first contact).
- Platform commission on creator: **12%**.
- Funds held in escrow until delivery.
- Disputes resolved within 48 hours.
- Violations: account termination + penalty (30% of last 12 months revenue).

The signed agreement is saved by `POST /api/agreements` with the typed full
name, version, IP, and user agent — see the `Agreement` model in
`prisma/schema.prisma` for the audit-trail fields.

### 2. Contact-info guard

`lib/contact-guard.ts` scans free-text inputs (resume, portfolio descriptions,
chat messages) for:

- Phone numbers (Georgian +995, generic international, local 9-digit)
- Email addresses
- Telegram / WhatsApp / Viber handles
- Off-platform payment URLs (Revolut, PayPal, Wise, etc.)
- Georgian IBANs (`GE` + 18 chars)

Behavior:

- **Resumes (`/dashboard/creator` → ResumeUpload)**: hard-rejected if any
  phone/email/handle/payment detail is detected. Cannot be saved at all.
- **Chat (`/messages` → MessageComposer)**: send button disables and shows
  a warning; the server endpoint at `/api/messages` is also expected to
  re-validate.
- **Demo content (DemoUpload)**: all images/videos are watermarked client-
  side via `<WatermarkedMedia>` for preview, and in production the server
  pipeline burns in the watermark with ffmpeg/sharp before storage. The
  unwatermarked original is held in private storage and released only
  after the client pays and approves.

## MVP scope (delivered)

1. ✅ Homepage with hero, featured creators, categories, how-it-works,
   benefits, testimonials, CTA.
2. ✅ Creator registration (full form: name, email, phone, city, platforms,
   social links, category, bio, portfolio, starting price, password).
3. ✅ Client registration (name, company, email, phone, industry, password).
4. ✅ Mandatory platform agreement step after registration.
5. ✅ Creator marketplace with filters (category, platform, city, price,
   rating, audience size, niche, delivery time, verified-only) and search.
6. ✅ Creator profile (cover, avatar, bio, platforms, followers, audience
   demographics, portfolio gallery, services, reviews, response time).
7. ✅ Service listing with title, description, price, delivery, revisions,
   what's included, requirements, add-ons.
8. ✅ Order request flow (5 steps: select package, brief, files, deadline,
   payment with escrow note).
9. ✅ Messaging UI (conversations list + thread + composer with on-platform-
   only guard).
10. ✅ Creator dashboard (stats, orders, services, resume/demo upload,
    reviews, analytics).
11. ✅ Client dashboard (active orders, completed orders, saved creators,
    re-order).
12. ✅ Admin panel (stats, pending creators with approve/reject/verify,
    recent orders, categories, featured creators, users).
13. ✅ About, Contact, FAQ pages.

## Payments — Bank of Georgia e-commerce

Direct on-platform card payments are wired end-to-end behind a generic
`PaymentProvider` interface (`lib/payments/types.ts`) with two
implementations:

- `bogProvider` — the real Bank of Georgia e-commerce flow
  (OAuth2 → create order → hosted 3DS → webhook → refund) in `lib/payments/bog.ts`
- `mockProvider` — a local hosted page at `/payments/mock-bog/[id]` that
  mimics BOG so the entire flow runs in dev with no credentials

Select with `PAYMENTS_PROVIDER=bog|mock` in `.env`. With `mock` (default):

1. Client clicks **გადახდა** on `/checkout/[serviceId]` → `POST /api/payments/create`
2. The provider returns a `redirectUrl` to the mock BOG page
3. User clicks Pay → `POST /api/payments/webhook/bog` flips status `created → held`
4. User is redirected to `/payments/return/[paymentId]` showing escrow confirmation
5. After delivery + client approval → `POST /api/payments/release` triggers
   creator payout (real BOG impl uses the Business payout API; commission
   deducted automatically)
6. Disputes → `POST /api/payments/refund`

Lifecycle: `created → processing → held → released | refunded | failed`.

Real-mode setup:

```bash
PAYMENTS_PROVIDER=bog
BOG_CLIENT_ID=<from business.bog.ge>
BOG_CLIENT_SECRET=<from business.bog.ge>
BOG_CALLBACK_SECRET=<shared secret for webhook HMAC>
```

The webhook handler verifies `callback-signature` against an HMAC-SHA256
of the raw body when not in mock mode. Idempotency is enforced via
`markEventSeen(eventId)` so duplicate deliveries from BOG don't double-
update.

## Scaling later

- **Auth**: swap the link-only register/login into NextAuth (Google,
  Apple, magic-link) + add Prisma adapter — the schema's `User` model is
  ready.
- **More payment providers**: drop a new file next to `lib/payments/bog.ts`
  that exports a `PaymentProvider`, then add it to `getProvider()`. TBC
  iPay and Stripe (with GEL forex) are the obvious next targets.
- **Real-time messaging**: add Pusher / Ably / Soketi and a `MessageEvent`
  table; the `Conversation` + `Message` Prisma models are already keyed
  by order.
- **Notifications**: email (Resend / Postmark) + push (web push API) +
  SMS (SMSOffice for Georgia) — `Notification` model is in the schema.
- **File storage**: S3 / MinIO with signed URLs for portfolio uploads;
  ffmpeg/sharp pipeline for watermarking.
- **Search**: Meilisearch or Algolia for fuzzy search across creators,
  services, and niches; current filtering is in-memory.
- **Analytics**: Plausible / PostHog for product analytics; per-creator
  view counters in the `Creator` model.
- **Admin app**: stand-alone /admin panel hardened with role-based
  middleware (`role === 'ADMIN'` check on every route).

## Sample data

All sample creators, services, reviews, and orders live in
`lib/data/`. They use real-looking Georgian names, Tbilisi/Batumi/Kutaisi
cities, GEL pricing, and Unsplash images for portfolio + Pravatar for
avatars. To replace with real data, populate the database via the seed
script or admin panel.
