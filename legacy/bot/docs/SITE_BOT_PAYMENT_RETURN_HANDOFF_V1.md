# Website Handoff: Bot Payment Return V1

Status: website and bot code are deployed as part of the explicitly transferred
bot-commerce scope. Both production result-worker flags are enabled after signed
site health, owner dashboard/transaction reads and platform-isolation tests
passed. No fake success or real-money order was created during deployment; an
official gateway sandbox/controlled payment remains the final end-to-end
provider acceptance test.

## Required user outcome

A checkout that begins in Telegram ends in Telegram. A checkout that begins in
Bale ends in Bale. The website is the order, payer, gateway callback and payment
verification authority, but its public receipt page is not the final bot
checkout destination.

The browser flow is:

```text
Telegram or Bale offer
  -> signed createBotPayment
  -> canonical website order
  -> configured payment gateway
  -> website callback + provider verification
  -> 302 to the originating bot deep link
  -> /start receipt_<opaqueOrderToken>
  -> signed paymentStatus
  -> canonical result rendered in that bot
```

In parallel, every first verified-success transition creates exactly one
durable delivery intent for the originating platform. This push is necessary
because a gateway callback may complete even if the customer closes the browser
before following the redirect.

Opening a gateway URL, following a return link, receiving a browser callback,
or supplying `status=success` is never proof of payment. Only the website's
existing provider verification may set the order to `success` or create the
success delivery intent.

## Order provenance

`dent_bot_create_offer_payment()` must store these server-derived fields in the
generic `bot-offer` order's `extra_form_data`:

- `bot_origin_platform`: exactly `telegram` or `bale`, taken from the verified
  signed service envelope;
- `bot_origin_identity_hash`: `dent_bot_identity_hash($platform,
  $platformUserId)`, never a callback or bot-supplied URL;
- the existing offer snapshot and request reference.

Do not store a raw platform user ID in the payment order. Do not accept a
return URL, bot username, platform, order status or entitlement from the client
checkout payload. Site environment owns the two expected bot usernames.

The current idempotency reference already includes platform and platform user
identity. An idempotent existing-order response must preserve the original
provenance and must never switch its return platform.

`paymentStatus` must authorize all of the following, not only the canonical
student number:

1. the linked website account owns the order;
2. the request platform equals `bot_origin_platform`;
3. the current `dent_bot_identity_hash()` equals
   `bot_origin_identity_hash`.

This prevents a token created in Bale from being used to finish a Telegram
checkout, even when both bot accounts link to the same website student.

## Browser return

After processing callback verification, replace the generic public-result
redirect for `source=bot-offer` orders with a platform return. Use only
server-configured usernames and an allowlisted URL builder:

- Telegram: `https://t.me/<telegram-username>?start=receipt_<orderToken>`
- Bale: `https://ble.ir/<bale-username>?start=receipt_<orderToken>`

The current order token format must remain 20-46 URL-safe characters so the
Telegram/Bale start and callback payload limits remain satisfied. The start
parameter is opaque and contains no user ID, amount, status, entitlement or
personal data.

The redirect occurs for success, pending, canceled, expired and failed browser
returns. The bot always calls signed `paymentStatus`; the deep link itself does
not declare the result. Non-bot orders retain the existing website result flow.

Required environment values:

```text
DENT_TELEGRAM_BOT_USERNAME=Dent1402Bot
DENT_BALE_BOT_USERNAME=dent1402bot
```

Validate them against `[A-Za-z0-9_]{4,64}`. Do not add a general-purpose return
URL environment value or open redirect.

## Verified-success delivery store

The existing private bot integration store contains a
`paymentResultDeliveries` map. Normalize this key in the same places that
currently normalize `notificationDeliveries`. Each record contains only:

- `id`: an opaque delivery ID;
- `dedupeKey`: a stable unique key for order ID + origin platform;
- `platform`: order origin platform;
- `identityHash`: order origin identity hash;
- `orderId` and `orderToken`;
- bounded safe presentation snapshot: offer title, final canonical amount,
  canonical status and verified timestamp;
- `status`: `pending`, `leased`, `delivered` or terminal `failed`;
- lease, bounded attempt and ACK timestamps.

Create or upsert this record inside the same locked transition that first moves
an order to verified `success`. Every code path that can perform provider
verification, including callback replay and public-result recovery, must call
one common idempotent finalizer. A unique dedupe key prevents duplicate pushes
when callbacks are replayed.

Do not create a success delivery for pending, failed, canceled or expired
orders. Do not fan a payment result out through the general notification feed,
to the other linked platform, to both bots, or to a personal Telegram account.

## Signed service actions

Add these owner-only service actions to `dent_bot_service_dispatch()`:

### `claimPaymentResultDeliveriesV1`

Request fields:

```json
{
  "contractVersion": "bot-payment-return-v1",
  "limit": 10
}
```

The signed envelope already supplies `platform`. Return only pending/expired
lease records with that exact origin platform. Resolve `identityHash` through
the current permanent link and decrypt its platform user ID only when producing
the response. If the link is revoked or mismatched, defer/fail closed rather
than routing to another identity.

Response item:

```json
{
  "deliveryId": "opaque-delivery-id",
  "platform": "telegram",
  "chatId": "numeric-private-platform-id",
  "order": {
    "orderToken": "20-to-46-url-safe-characters",
    "status": "success",
    "title": "bounded offer title",
    "amountRials": 300000,
    "verifiedAt": "2026-08-25T12:00:00+03:30"
  }
}
```

No provider secret, authority, card data, phone, student number or other
platform identity is returned.

### `ackPaymentResultDeliveryV1`

Request fields:

```json
{
  "contractVersion": "bot-payment-return-v1",
  "deliveryId": "opaque-delivery-id",
  "delivered": true,
  "reasonCode": ""
}
```

ACK must verify the current request platform owns the delivery lease. Failed
ACKs use only allowlisted reason codes and bounded retry/backoff. Delivered
records have bounded retention.

The bot writes its local delivery receipt before ACK. Therefore an ACK timeout
causes the next lease to be ACKed without resending the financial confirmation.

## Activation and acceptance

The website actions are deployed and the bot capability flags are now `1`:

```text
DENT_BOT_PAYMENT_RESULT_PUSH_ENABLED=1
DENT_BALE_PAYMENT_RESULT_PUSH_ENABLED=1
```

Before enabling both flags, pass all of these tests with a real configured
gateway in a non-production-money test mode or the provider's official sandbox:

1. Telegram purchase verifies, redirects to Telegram, and pushes once only in
   Telegram.
2. Bale purchase verifies, redirects to Bale, and pushes once only in Bale.
3. A forged/canceled/pending return never renders or pushes success.
4. Callback replay, claim lease retry and ACK timeout do not duplicate a push.
5. A Telegram caller cannot read or claim a Bale-origin order and vice versa.
6. An unlinked/revoked identity fails closed without exposing the order.
7. Ordinary website checkout still returns to its website result destination.
8. UTF-8 Persian, amount and both bot keyboards render correctly.
9. Production data is backed up to the laptop and restore-verified before and
   after deployment.
