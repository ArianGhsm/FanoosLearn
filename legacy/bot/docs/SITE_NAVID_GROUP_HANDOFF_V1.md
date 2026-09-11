# Website handoff: Navid class-group reminders v1

This workspace owns the bot adapters only. The site-owning task must implement
the canonical schedule, cropped screenshot and signed claim/ack actions.

## Canonical scheduler

For every assignment with a stable key and valid deadline, create at most one
delivery intent for each threshold: `new`, `week`, and `day`. `new` is emitted
only for assignments discovered after a silent baseline. `week` becomes
eligible at `deadline - 7 days` while more than one day remains; `day` becomes
eligible at `deadline - 1 day` and before expiry. Use Tehran server time and an
atomic unique key over assignment/threshold/platform/delivery-profile.

Do not backfill expired history. If a sync crosses multiple thresholds, choose
only the newest useful threshold. Postpone or suppress later thresholds if the
assignment becomes inactive/cancelled. This is a class reminder and is not
suppressed by one student's personal `submitted` acknowledgement.

## Screenshot contract

Generate a PNG from the canonical assignment card after sync. Crop it to one
assignment. It may contain title, course, creation date, deadline and sanitized
description only. It must not contain site navigation, account identity, Navid
credentials/cookies, other assignments, notifications, admin controls or debug
output. Bound dimensions and encoded size to Bot API limits. Regenerable image
cache is excluded from backups.

## Signed service actions

- `claimNavidGroupDeliveriesV1(limit<=3)` re-authorizes the linked owner and
  platform, leases pending items and returns `deliveryId`, `eventType`,
  `assignment`, and PNG `screenshotDataUri`.
- `ackNavidGroupDeliveryV1(deliveryId,delivered,reasonCode)` finalizes success or
  applies bounded retry/backoff. It is idempotent.

The website never receives or stores the group chat ID. Each bot runtime owns
its root-only destination setting. Delivery remains disabled until a fresh
baseline and owner-private preview are accepted.

Required tests: exact threshold boundaries, timezone, discovery baseline,
crossed/missed windows, duplicate syncs, lease expiry, ACK retry, invalid image,
inactive/expired assignments, authorization and a real owner-private Bot API
preview without sending to the class group.
