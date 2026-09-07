# Stage 7 — Telegram Owner Update Server Flow

Update Server exists only in Telegram and only in private chat. It is intentionally omitted from ordinary help/menu because the current internal contract has no side-effect-free `deployment.manage` permission probe. A linked user may invoke the unadvertised command, but the canonical backend rechecks `deployment.manage` when the request is submitted and denies non-owners.

Flow:
1. private-chat gate;
2. canonical link resolution through the messaging workspace projection;
3. short-lived local confirmation with opaque <=64-byte callback reference;
4. callback acknowledgement before backend work;
5. second confirmation submits `{platform=telegram, subject, target_key, idempotency_key}` only;
6. backend permission + one-active-deployment + idempotency controls apply;
7. bot renders only durable states (`REQUESTED`, `PREFLIGHT`, `BACKUP`, `TESTING`, `MIGRATING`, `ACTIVATING`, `RESTARTING`, `HEALTHCHECK`, `SUCCEEDED`, `FAILED`, `ROLLED_BACK`);
8. restart recovery reads durable status by request ID.

No percentage/ETA is fabricated. No callback can supply a repository, remote, path, branch, ref, SHA, command, restart command or environment override. Bot credentials are distinct from privileged updater credentials.
