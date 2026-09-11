# Bot Commerce V2

Status: deployed in the shared Telegram/Bale application and signed website
service contract on 2026-08-29. Product discovery, owner reports and result
workers are active. Telegram inline-query code is deployed, but BotFather still
reports inline mode disabled; ordinary opaque links and copy/share fallback
remain available until that external switch is enabled.

## Ownership and sources of truth

- Product definition, lifecycle, audience, share token, saved audiences and
  owner audit live in the one shared Telegram/Bale SQLite database.
- The existing website payment store remains authoritative for orders, gateway
  state, callback verification, transaction references and reconciliation.
- `/buy/` remains the website storefront. Bot products do not mirror website
  items or collections and never accept an amount from callback data.
- Every bot order stores an immutable title, description, amount, product
  version, limits and optional fulfillment snapshot.

## User access

The normal user never sees `پرداخت‌ها`. `🛍 محصولات` is rendered only when the
current authenticated identity has at least one active eligible product with
remaining capacity or a prior receipt. Eligibility is checked again before the
detail screen and immediately before order creation.

An authenticated identity may be either a canonically linked website account
or a verified non-class Contact/OTP onboarding profile. The latter never needs
or receives a website-account link. Its payer identity is an opaque server-only
HMAC key derived from the verified phone; the optional self-declared student
number is not an authorization key. Grades, Navid, account data, owner reports
and every other private action remain canonical-link-only.

Audience modes are authenticated users, link-only, one or more cohorts,
explicit canonical student numbers and reusable saved lists. Link-only products
never enter the normal list. Product lifecycle is draft, scheduled, active,
paused, expired and archived. ISO UTC values are stored and the shared Tehran
Solar Hijri formatter is used for chat display.

## Sharing

The canonical deep link is:

```text
product_<opaque-revocable-share-token>
```

It contains no database ID, price, audience or personal data. Rotation revokes
the previous token. Telegram owner inline queries return only active products
and the same opaque link. Bale uses its ordinary deep link/button fallback;
inline mode is not simulated.

Telegram inline support in code is complete. Telegram's external BotFather
inline-mode switch must be enabled for the platform to deliver inline queries.

## Checkout and financial integrity

`createBotPayment` uses `bot-commerce-v2`. The signed request contains the
server-selected product snapshot; the website rechecks time, global capacity,
per-user successful/pending reservations and a platform/user-bound idempotency
key while holding the existing payment-store lock. A later product edit cannot
change an older order.

Canonical lifecycle timestamps are `createdAt`, `paymentStartedAt`, `paidAt`,
`verifiedAt`, `updatedAt` and optional `expiredAt`. Only provider verification
may create a success. Replayed callbacks and result recovery use existing
status-transition guards and one stable success-delivery key.

Verified results return to the originating bot as `receipt_<opaque-order-token>`.
The signed status call requires the same verified payer identity, originating
platform and bound platform identity. A verified generic user keeps only an
encrypted same-platform return route; this is not an account link. Success
creates one user receipt on the originating platform and owner deliveries on
their linked platforms. Local delivery receipts are written before ACK.

Term subscription checkout reuses this exact stack. The bot records a shared
pre-gateway intent and binds the returned order token to its immutable term,
Solar Hijri billing period and price snapshot. Only the existing signed
provider-verified success delivery, or the same canonical verified status read,
activates the unique monthly entitlement. See
`TERM_SUBSCRIPTION_ACCESS_V1.md`; this is not a parallel payment or callback
system.

## Owner control center

The owner-only `💳 پرداخت‌ها` tree is:

```text
Summary
├── ➕ Product
├── 📦 Products
│   └── detail/edit/audience/schedule/limits/pause/archive/duplicate/share/stats
├── 📊 Statistics
├── 🧾 Transactions, detail, pagination, search and filters
├── 👥 Saved audiences and canonical directory search
├── 📤 UTF-8 Excel-compatible CSV and text reports
├── 🔔 Paid/unpaid reminder preview and explicit confirmation
├── 📚 Term booklet subscription policy, grants, reports and renewal controls
└── ⚙️ Payment invariants
```

Reminder batches are previewed, rate-limited, deduplicated for 24 hours and
audited. A bulk send is never triggered without the owner's explicit callback.
Exports are produced in a per-request temporary directory and removed after the
Bot API upload returns. Export pagination reads all matching rows (up to the
documented safety ceiling) rather than silently stopping at the first 100.

Transaction filters cover product, status, account/name search, date presets or
custom range, originating platform and gateway. Product reports expose today,
7-day, 30-day and lifetime presets, target/paid/unpaid counts, conversion only
when a finite denominator exists, capacity and lifecycle dates. Owner manual
status corrections are limited to non-success states, require a note and append
an actor/time/before/after audit entry to the canonical order snapshot. A
provider-verified success is immutable and is never imitated by that manual
action.

Product sharing includes the opaque link, Telegram inline mode, Bale's regular
link fallback, copyable sanitized text and an explicitly confirmed same-platform
send for finite audiences. The latter is capped, deduplicated for 24 hours and
audited; no callback alone can trigger an unconfirmed bulk send.

## Migration and recovery

The SQLite migration retains every legacy offer, maps inactive to paused and
deleted to archived, assigns opaque share tokens and adds auxiliary audit/list/
reminder tables in one immediate transaction. The additive term-access tables
retain policy, intent, paid/free entitlement, audit and renewal-notice state;
their initial complimentary list is empty and legacy orders are not imported.
The shared offer database and
website payment/bot-link storage must be included in pre- and post-deployment
restore-verified laptop backups. Deploy scripts never replace live SQLite.

## Activation checks

1. Back up website storage and VPS state to the laptop and verify restore.
2. Deploy website contract; confirm ordinary `/buy/` health and signed account.
3. Deploy both bot adapters from the same release and migrate the shared DB.
4. Enable result-push flags only after claim/ACK actions pass signed live smoke.
5. Enable Telegram inline mode in BotFather, then run an owner-only inline smoke.
6. Use no real payment and send no reminder batch in smoke tests.
7. Confirm no test product, order, audience or notification remains.
8. Create the post-deploy restore-verified laptop snapshot.

## Production acceptance (2026-08-29)

- Generic-commerce hotfix is active in website PWA/API release
  `20260829-153231`, Telegram `telegram-20260829-153439` and Bale
  `bale-20260829-153530`. The exact deployed Telegram release passed an offline
  verified-generic deep-link and create-checkout probe; the combined runtime
  check passed with both service CWDs pinned to those releases and zero
  restarts. No real order or product was created by acceptance testing.
- The isolated signed HTTP contract test proved that a verified generic profile
  reaches product-state/payment validation without an account link while its
  grade request still returns `ACCOUNT_LINK_REQUIRED`; its return ID was stored
  encrypted. The full shared suite passed 187 tests.

- Website PWA release `20260829-132909`, shared bot release
  `bots-20260829-133854`, Telegram release `telegram-20260829-134332` and Bale
  release `bale-20260829-134503` are active.
- Both services are enabled and active with zero restarts. Their process CWDs
  resolve to the exact release symlinks.
- Telegram and Bale read the same `0660 root:dentcommerce` SQLite store. Its
  integrity check passed and the one pre-existing product survived migration.
- Both signed owner dashboard and transaction queries passed from their
  deployed runtimes. No product, order, reminder or broadcast was created by
  acceptance testing.
- Both payment-result workers are enabled. A real-money/sandbox gateway callback
  was intentionally not manufactured during deployment, so provider end-to-end
  acceptance remains a separate controlled payment test.
- The final encrypted laptop snapshot
  `vps-state-20260829-134736.tar.gz.dpapi` passed immediate isolated restore
  verification. No plaintext restore directory remains.
