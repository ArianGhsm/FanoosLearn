# Stage 7 — Telegram Owner Update Server Flow

Update Server exists only in Telegram and only in private chat. It is intentionally omitted from ordinary help/menu. The Platform now exposes a side-effect-free signed `deployment.overview` contract, so the bot checks canonical `deployment.manage` before it creates any local confirmation or exposes deployment metadata.

Flow:
1. private-chat + Telegram gate;
2. signed `POST /api/internal/v1/deployments/overview` with only `platform=telegram`, messaging `subject` and fixed configured `target_key`;
3. canonical link resolution + platform-scoped `deployment.manage` check;
4. unauthorized user receives only the generic denial UX and no confirmation is created;
5. authorized user sees only safe current/canonical-main SHA prefixes, update availability and health status returned by the read-only snapshot contract;
6. bot creates a short-lived local confirmation with opaque <=64-byte callback reference;
7. callback acknowledgement precedes backend work;
8. second confirmation submits `{platform=telegram, subject, target_key, idempotency_key}` only;
9. backend permission + one-active-deployment + idempotency + canonical-main/CI/health gates apply again;
10. bot renders only durable states (`REQUESTED`, `PREFLIGHT`, `BACKUP`, `TESTING`, `MIGRATING`, `ACTIVATING`, `RESTARTING`, `HEALTHCHECK`, `SUCCEEDED`, `FAILED`, `ROLLED_BACK`);
11. restart recovery reads durable status by request ID.

The overview endpoint itself executes no Git/shell/process command and holds no updater credential. Candidate resolution is performed separately by the privileged updater-side status refresher and exposed only as a safe bounded snapshot.

No percentage/ETA is fabricated. No callback can supply a repository, remote, path, branch, ref, SHA, command, restart command or environment override. Bot service credentials are distinct from privileged updater credentials. Bale has no deployment-control path.
