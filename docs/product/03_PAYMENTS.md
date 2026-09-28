# Payments

**Status (2026-09-28):** the owner has put the gateway aside. The Zibal code
stays in the repository, `FANOOS_PAYMENT_GATEWAY` is `disabled` in production,
and no merchant is configured. Everything below applies once a gateway is
configured.

The owner decided on 2026-09-27 that FANOOS takes payments through Zibal, the
same gateway the Dent1402 site uses.

## How a purchase works

1. A student opens **فروشگاه** (`/app/store`). The bot uses the same
   commerce API.
2. Choosing a product creates an order (`POST .../orders`, idempotent per
   product and browser tab). The server asks Zibal for a `trackId` and sends
   the payer to `https://gateway.zibal.ir/start/<trackId>`.
3. Zibal returns the payer to `/pay/return?token=…&trackId=…`. The server
   settles the order by calling Zibal's verify endpoint itself; it never
   trusts the query string. A payment counts only if Zibal verifies it
   **and** Zibal's amount equals the order's amount.
4. A verified payment grants an entitlement on the product's target scope.
   Any assessment whose access policy requires that entitlement opens.
5. If the gateway cannot be reached, the page says the payment is being
   checked and the attempt stays open. Such a payment is never marked
   failed.
6. **Automatic settlement.** Every five minutes, `fanoos-reconcile-payments.timer`
   runs `scripts/ops/reconcile-payments.php` (`CommerceService::reconcileStale`).
   It asks the gateway about every attempt that is still open and has been
   quiet for 20 minutes, whether the payer never came back or came back while
   the gateway was down:
   - paid: marked paid, and what was bought opens;
   - refused: marked failed;
   - no answer: left open for the next run.

   Attempts older than two days are left to a person
   (`POST .../payments/{attempt}/reconcile`). With payments off, the timer does
   nothing.

## Configuration

These keys go in the platform config (`FANOOS_PAYMENT_*`):

- `FANOOS_PAYMENT_GATEWAY=zibal`
- `FANOOS_PAYMENT_ZIBAL_MERCHANT`
- `FANOOS_PUBLIC_ORIGIN=https://fanooslearn.ir`
- `FANOOS_PAYMENT_CALLBACK_KEY`, which signs callback tokens

Payments are off unless all of these are present. Amounts are stored in
Rials (`IRR`) and shown in Tomans.

## Products and prices

The owner manages products at **محصولات و قیمت** (`/app/admin/products`).
The page appears for anyone holding `commerce.manage_catalog` in the selected
workspace, and every call checks that permission again.

- **Create** a product with a name and a price in Tomans. It starts as a
  draft and targets the workspace's own scope, which covers a whole discipline
  library.
- **Change the price at any time.** The current price version closes and a
  new one starts at the same instant. The page shows the history. Orders
  already placed keep the price they were placed at.
- **Status:** draft, on sale, or archived. Only products on sale are offered.
- **Lock the exams** behind a product, or free them again. This sets
  `requires_entitlement` on every assessment that points at the product's
  scope. Selling a product does not lock anything by itself.

`CatalogAdminService` holds these rules, behind `GET/POST /admin/products`,
`PATCH /admin/products/{id}` and `POST /admin/products/{id}/exam-lock`.
