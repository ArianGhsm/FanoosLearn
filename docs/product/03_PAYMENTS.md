# Payments

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
   checked and the attempt stays open for reconciliation
   (`POST .../payments/{attempt}/reconcile`, which asks Zibal again). Such a
   payment is never marked failed.

## Configuration

These keys go in the platform config (`FANOOS_PAYMENT_*`):

- `FANOOS_PAYMENT_GATEWAY=zibal`
- `FANOOS_PAYMENT_ZIBAL_MERCHANT`
- `FANOOS_PUBLIC_ORIGIN=https://fanooslearn.ir`
- `FANOOS_PAYMENT_CALLBACK_KEY`, which signs callback tokens

Payments are off unless all of these are present. Amounts are stored in
Rials (`IRR`) and shown in Tomans.

## Products

Until there is a management screen, products are created with:

```
php scripts/ops/create-product.php --workspace=<uuid> --key=<key> --name=<title> \
    --rial=<price> [--scope=<uuid>] [--activate] [--gate-exams]
```

- The default scope is the workspace's own, which covers a whole discipline
  library.
- `--gate-exams` also marks that scope's assessments as requiring the
  purchase. Without it, the product is only on offer and nothing is locked.
- Running the command again with a new price starts that price now; orders
  already placed keep theirs.
