# Term Subscription Access V1

## Production policy

Term 7 is the only protected subscription term in v1. Its policy is stored in
the shared Telegram/Bale commerce SQLite database rather than spread through
handlers:

- mode: `subscription`;
- enabled: `true`;
- monthly price: `1,500,000` rials (`150,000` tomans in UI);
- active from Solar Hijri `1405-07-01` (1 Mehr 1405);
- renewal: a new, user-initiated payment for each Persian calendar month;
- complimentary list: empty at migration and populated only by an explicit
  owner grant.

Before `activeFrom`, the authenticated booklet flow keeps its previous open
behavior. Disabling the policy or changing its mode to `open` also restores the
authenticated open behavior without deleting history. Other terms remain
unchanged until an owner-created policy explicitly protects them.

## Calendar and authorization

`dent_bot.subscriptions` is the single Persian billing utility. It performs an
exact Solar Hijri/Gregorian conversion in the Tehran timezone. A paid period is
named `term<term>-<year>-<month>`, for example `term7-1405-07`; it starts at
Tehran midnight on day 1 and expires at the next Solar Hijri month boundary.
A purchase in the middle of a month charges the full stored price and still
expires at that boundary. There is no rolling-30-day or prorated path.

The canonical subscription subject is derived from the canonically linked
website student's number, never a Telegram username, display name, callback or
old message. A current access decision is:

```text
current-month active paid entitlement
OR
active owner-granted complimentary entitlement
```

The decision is read from SQLite at booklet entry, enqueue, dequeue, cached
`file_id` resend, non-PDF send and immediately before final personalized PDF
upload. Revoking complimentary access therefore takes effect on the next fresh
check; a valid paid entitlement independently keeps access.

## Payment integrity

No payment stack is duplicated. The bot creates a normal `bot-commerce-v2`
order through the existing signed website service and existing gateway,
callback, verification and reconciliation.

Before leaving for the gateway, the bot atomically records a same-platform
checkout intent containing the canonical subject, term, exact billing period,
price and product version. The returned opaque `orderToken` is bound to that
intent. A paid entitlement is created only when either:

1. the existing signed verified-success delivery worker claims a matching
   `success` result with `verifiedAt`; or
2. the same user asks the signed canonical website status action and receives
   that same verified `success` snapshot.

Platform, platform user, amount and order token must match the stored intent.
Pending, failed, mismatched or browser-provided statuses cannot activate
access. The unique `subject × term × billing period × paid` constraint and the
bound checkout make callback/status replay idempotent.

## Shared state

The shared commerce database adds:

- `term_access_policies`;
- `term_access_entitlements`;
- `term_subscription_checkouts`;
- `term_access_audit`;
- `term_subscription_renewal_notices`.

Paid and complimentary rows retain subject, student number, display label,
term, type, period, state, grant/expiry, actor, payment reference, revoke actor,
revoke time and optional note. Audit entries cover policy/price changes,
payment activation, grant, revoke and lazy expiration. Existing offers and old
orders are never reinterpreted as subscriptions.

## UX and operations

The user has a shared `اشتراک جزوات` page. A denied Term 7 entry shows the
current Persian month, full-month price, boundary expiry and purchase action.
Paid, complimentary and both states are explicit. The owner payment center has
a separate Term 7 dashboard, price/policy/reminder controls, canonical student
search, grant/revoke, paid/free/both classification, statistics and UTF-8
CSV/TXT exports. Additional term policies can be created disabled/open and
then configured without editing code. The owner can change the monthly price,
Solar Hijri activation boundary, open/subscription mode, enabled state and
renewal reminders; already-created checkout snapshots remain immutable.

The policy's commerce offer is internal plumbing for the existing gateway. It
is deliberately excluded from the ordinary product catalog, public product
deep links and generic product editor, so subscription purchase can only pass
through the period-bound checkout flow.

On the first three days of a new Persian month, the originating platform may
send one durable reminder to a previous-month payer who has neither a
current-month payment nor complimentary access. The unique notice key prevents
duplicate spam; owner policy controls reminders and no automatic charge exists.

All eligible PDF recipients still pass the existing canonical watermark
identity contract and `recipient-pdf-v9`. Paid, complimentary and owner users
receive only personalized full-identity editions. Voice/non-PDF sends and every
PDF upload/cache resend keep Telegram `protect_content=true`; no entitlement
ever exposes an original or clean PDF.

## Migration and recovery

Migration is additive and idempotent. It creates the Term 7 policy and the
scheduled internal commerce offer, but creates no checkout, entitlement or
complimentary user. The whole shared SQLite file is included in the existing
DPAPI-encrypted, restore-verified laptop snapshots.

After deployment, `scripts/check-term-subscription-iran.ps1` proves both
systemd processes are active with their real `/proc/<pid>/cwd` on the intended
releases, verifies all shared tables and prints only non-PII policy/count data.
