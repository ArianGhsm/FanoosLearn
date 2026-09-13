# Stage 7 — Bot/Worker One-Time Runtime Bootstrap Handoff

This supplements `07_CODEX_ONE_TIME_BOOTSTRAP.md`; it does not replace the canonical deployment runbook.

During the supervised bootstrap, inspect Python, systemd/process manager, disk/memory, `qpdf`, Poppler (`pdfinfo`, `pdftoppm`), Pillow/libraqm, font availability and private state roots. Provision **new** FANOOS runtime users, bot tokens and independent service signing secrets outside Git.

Recommended least-privilege service actions:

- Telegram adapter: `messaging.link.consume,messaging.workspace.read,messaging.workspace.select,commerce.order.create,commerce.order.read,notification.claim,notification.receipt,delivery.issue,delivery.consume,delivery.receipt,protected_media.enqueue,protected_media.forensic.candidates,protected_media.forensic.source,deployment.request,deployment.status`
- Bale adapter: same messaging/commerce/notification/delivery actions, without deployment or protected-media enqueue used for protected direct sends.
- Notification projector: `notification.project`
- Protected-media worker: `protected_media.claim,protected_media.complete,protected_media.fail`

Install the tracked service templates only after adapting paths/users to the real host. Do **not** enable the protected-media service until Platform freezes and implements canonical `object_capability` redemption and final artifact retrieval; its current runtime intentionally exits before claiming a job.

Run deterministic tests from the exact release, then live non-production Telegram/Bale smoke. Configure fixed updater health/smoke hooks on the server; bot callbacks never supply hook commands. Return a redacted bootstrap evidence report with exact SHA, service identities/key IDs only, health/smoke results and remaining gaps. No deployment was performed by this source workstream.
