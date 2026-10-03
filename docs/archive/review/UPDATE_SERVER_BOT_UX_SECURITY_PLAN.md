# Telegram Update Server button — UX and security plan

Audit date: 2026-09-07  
Audited FANOOS SHA: `5695d3ebcff52aa9bebe2782617e749bb093bdfb`  
Status: **design/audit plan only; not a backend contract and not an implementation**

## Readiness

**Current readiness: NOT READY.**

The audited FANOOS `main` contains a safe deploy/rollback runbook, but it does not contain a durable update request/status control plane, a bot-facing update API, or a dedicated critical platform permission for this action. The expected Platform Chat Stage 7 handoff documents are also absent.

The Telegram button must remain unimplemented/hidden until the Platform Chat defines and tests those controls. The bot must never substitute a shell command, SSH action, Git ref field or Telegram-ID allowlist.

## Product scope

User requirement: expose Update Server from **Telegram owner UI**.

Default Stage 7 decision:

- Telegram: eligible once all gates below pass.
- Bale: **do not expose by default**. The user did not require it, and there is no reason to enlarge the privileged control surface merely for UI parity.

A future Bale control can be reconsidered only through an explicit product/security decision and equivalent canonical permission checks.

## Security invariants

Every update interaction must preserve all of these:

1. **Private chat only.** Group/supergroup/channel/direct-message contexts other than the bot's private user conversation are denied.
2. **Platform ID is not authorization.** Telegram `user_id` must resolve through the secure canonical link to a FANOOS user.
3. **Canonical platform-scoped permission.** The backend must check an explicitly defined critical permission at platform scope. This audit intentionally does not invent its final permission key.
4. **Two-step confirmation.** Opening the owner panel/status is not approval to update.
5. **No arbitrary Git input.** No branch name, ref, SHA, repository URL or path is accepted from Telegram callback/user text.
6. **No shell input.** A callback never becomes a command line, script argument or templated shell fragment.
7. **Backend-owned durable request.** The bot submits only the approved update intent through the final platform contract.
8. **Idempotency.** Duplicate taps/retried network calls cannot create multiple deployments.
9. **One active update lock.** A second request cannot race an existing active update.
10. **Privilege separation.** Telegram runtime cannot deploy, SSH or sudo. Only a separately privileged updater can apply an authorized canonical request.
11. **Approved source policy.** The updater selects from server/platform policy (for example the approved FANOOS main/release target defined by Platform/ops), never free-form chat input.
12. **Durable status survives restart.** Bot/updater/platform restart must not erase whether an update is pending, running, succeeded, failed or rolled back.
13. **No fake percent/ETA.** UI renders only actual durable stages/timestamps/results provided by the control plane.
14. **Safe terminal failure.** Backup/readiness/deploy/health/rollback outcomes are explicit and auditable.
15. **Secret redaction.** Token, SSH/repo credentials, environment values, filesystem secret paths and raw command output are never returned in chat.
16. **Audit trail.** Requester, authorization outcome, request correlation/idempotency, updater result and terminal outcome are canonical/auditable.

## Required architecture

```text
Telegram private owner UI
        |
        | platform update callback (opaque, <=64 bytes)
        v
Telegram adapter
        |
        | immediately answerCallbackQuery
        v
shared FANOOS bot application
        |
        | service-authenticated canonical authorization/status/request
        v
FANOOS backend update control plane
        |
        | durable authorized request + single active lock
        v
privileged updater service
        |
        | approved source policy, backup, install/switch, restart, health, rollback
        v
canonical durable status/audit
        |
        v
bot observes and renders status after any restart
```

The privileged updater and the Telegram process must be different security principals. The updater may possess narrowly scoped deploy/repository/runtime privileges; the bot may not.

## UX flow

Exact endpoint names, permission keys and persistent status enums are intentionally left to Platform Chat. The following is a semantic UX flow, not a central contract.

### 1. Owner menu entry

The Update Server entry is visible only after:

- Telegram private chat check;
- linked canonical user resolution;
- canonical platform-level authorization says the action is allowed.

Do not display an actionable update button and later rely solely on callback secrecy.

The owner menu may show a compact health/status summary, but it must not reveal secrets, server paths or raw environment data.

### 2. Status screen

Before offering confirmation, read canonical update state.

If an update is active:

- show its real current durable stage/status and start timestamp;
- do not offer a second start button;
- provide a refresh/status action using an opaque reference.

If the control plane is unavailable or status cannot be authenticated, fail closed and show a neutral unavailable/error message.

### 3. First intent action

When the authorized owner presses Update Server:

- acknowledge callback immediately;
- recheck canonical permission;
- ask the backend/control plane for a short-lived confirmation intent/token bound to the canonical actor and intended action, if that is how the final contract models confirmation;
- render a confirmation screen explaining that services may restart and that health/rollback is automatic according to the approved deploy policy.

No branch/SHA textbox is offered.

### 4. Second confirmation

The confirmation callback carries only an opaque, bounded server-issued action reference. It must be short-lived, single-purpose and useless for a different actor/action.

On confirmation:

- acknowledge callback;
- recheck private chat + linked identity + canonical permission;
- submit one idempotent update request through the final platform contract;
- render the durable request reference/status, not an invented progress percentage.

A cancelled/expired confirmation produces no update request.

### 5. Running status

The bot may render actual durable stages such as preflight, backup, install/switch, restart, health verification and rollback **only if those stages exist in the final Platform control-plane status model**. The names in this sentence are illustrative operational phases derived from the existing deploy runbook; they are not frozen enums.

Rules:

- do not estimate completion time;
- do not convert stage count into fake percentage;
- do not declare success before terminal health verification;
- if Telegram runtime restarts during deployment, status must be recoverable from the backend after restart.

The updater, not Telegram, owns the deployment lifecycle.

### 6. Terminal result

Terminal UI must distinguish at least the final meanings defined by the platform control plane, including successful deployment and unsuccessful outcomes. If rollback occurred, state that explicitly rather than showing a generic success because the service is healthy again.

Safe details can include:

- terminal state;
- approved deployed release/SHA if the backend explicitly exposes it as non-secret result data;
- start/completion timestamps;
- health/rollback summary/error code designed for operator display;
- correlation/request identifier.

Do not send raw shell output, environment, stack traces with secrets, access tokens or private repository credentials.

## Callback design

Telegram documents callback data as 1–64 bytes. Update callbacks therefore must use a compact typed/opaque format.

Example **shape only**, not a frozen wire contract:

```text
<version>:<action>:<opaque_ref>
```

Security does not come from obscurity of the callback reference. The backend reauthorizes every sensitive callback.

Callback handling order:

1. parse version/action/ref with strict length/charset bounds;
2. verify private-chat context and expected Telegram actor envelope;
3. `answerCallbackQuery` promptly;
4. call backend through signed service auth;
5. backend resolves linked canonical user and authorizes action;
6. render result.

Never deserialize executable data, repository refs or shell fragments from callback data.

## Authorization model requirements for Platform Chat

The final platform design must define a critical platform-scoped permission for requesting updates. This audit does not choose its permanent permission key.

Required semantics:

- permission exists in canonical RBAC, not in Telegram config alone;
- only platform scope is valid;
- a workspace admin/teacher/content manager cannot inherit update authority by accident;
- service authentication identifies the Telegram service but does not grant the human actor update permission;
- linked actor must be active/not revoked;
- permission is re-evaluated at confirmation/request time;
- denial is audited without leaking role topology or secrets.

A Telegram owner ID may be used as a defense-in-depth runtime allowlist **only if Platform explicitly wants it**, but it cannot replace canonical authorization.

## Durable control-plane requirements

Before Prompt 2 implements the button, Platform Chat must make the following machine-testable:

### Request

- durable identity/reference;
- canonical requesting user;
- request source/channel where useful for audit;
- idempotency key/request digest semantics;
- creation/authorization timestamps;
- final approved release/source policy resolved outside chat input.

### Concurrency

- one active update per relevant deployment target;
- atomic claim/lease/lock semantics;
- duplicate idempotent request returns the same logical operation or another explicitly contracted idempotent result;
- a stale/crashed updater can be recovered safely without a second conflicting deploy.

### Status

- durable observable current/terminal status;
- updater heartbeat/lease if needed by the platform model;
- safe operator-facing error/result code;
- rollback outcome distinct from simple failure/success;
- status remains queryable across service/bot restart.

### Updater boundary

- privileged updater consumes only canonical authorized requests;
- repository/source policy is allowlisted/server-configured;
- no arbitrary repository, ref, path or command supplied by the bot;
- exact approved SHA/release is verified before install;
- backup/readiness steps from current deploy runbook are enforced;
- health failure triggers the contracted safe failure/rollback path;
- updater writes terminal result even when deploy fails after restart where technically possible;
- secrets are redacted before durable user-visible status.

## Interaction with existing deploy runbook

`docs/fanoos-migration/04_DEPLOY_RUNBOOK.md` remains the operational safety source for exact-SHA deployment, backup, readiness, immutable release/switch and rollback behavior. The new control plane should orchestrate/authorize that behavior; it should not weaken it.

The bot does not replace the runbook. It becomes a restricted requester/status UI in front of an audited updater.

## Failure modes and required UX

| Failure | Required behavior |
| --- | --- |
| Group/channel update callback | Deny; no backend update request. |
| Telegram ID not linked | Deny and offer normal secure link flow; never infer owner from ID alone. |
| Linked user lacks critical permission | Deny; audit. |
| Platform/service-auth unavailable | Fail closed; no shell fallback. |
| Confirmation expired/replayed | Deny without creating a new update. |
| Duplicate confirm/network retry | Idempotent same logical request; no second deployment. |
| Another update active | Show active status; no second start. |
| Backup/preflight fails | Terminal safe failure; deployment does not proceed. |
| Deploy/restart health fails | Show canonical failure/rollback status; never claim success because the callback returned 200. |
| Telegram bot restarts mid-update | On restart, recover status from backend by durable request state. |
| Updater crashes | Backend lock/lease/recovery policy prevents parallel unsafe update; eventual status reflects recovery/failure. |
| Raw updater error contains secret | Redact before durable/user-visible output. |

## Tests required before enabling button

### Telegram adapter tests

- update menu only in private chat;
- callback parser rejects oversize/malformed/unknown action;
- callback is acknowledged before backend/update work;
- no command/ref/SHA can be represented as an executable parameter;
- confirmation cancel/expiry/replay behavior;
- safe status formatting with Persian/Unicode and long error summaries.

### Shared/backend contract tests

- linked owner + critical platform permission allowed;
- linked non-owner denied;
- workspace-scoped admin denied;
- revoked link/user/permission denied immediately;
- service identity valid but human permission absent => denied;
- idempotent duplicate request;
- conflicting idempotency request rejected;
- one-active-update atomic concurrency;
- durable status visible after simulated bot restart;
- updater terminal success/failure/rollback representation;
- secret redaction fixture.

### Updater/operations tests

- approved repo/source only;
- arbitrary ref/path/command rejected by construction;
- exact release/SHA verification;
- backup precondition;
- install/switch/restart health sequence;
- induced health failure exercises rollback/safe terminal state;
- updater restart/lease recovery;
- live non-production smoke before production enablement.

## Logging and telemetry

Bot logs may contain:

- correlation/request IDs;
- platform name;
- safe action type;
- canonical actor ID only according to the repository's logging/privacy policy;
- authorization outcome/error code;
- timing/attempt metadata.

Bot logs must not contain:

- Telegram/Bale tokens;
- service signing secret;
- repository deploy credentials;
- raw environment files;
- callback confirmation secret/token in full;
- raw shell command output;
- protected-content user identifiers beyond approved redacted/audit forms.

## Bale disposition

Do not expose Update Server in Bale by default.

Reasons:

- explicit user requirement is Telegram;
- privileged controls should use the smallest necessary surface;
- transport parity is not a security requirement;
- the shared backend control plane can remain channel-neutral without every channel receiving a UI entry.

Bale can still render ordinary service health/user features according to its capability matrix.

## Gate for Prompt 2

The Telegram Update Server implementation remains **DEFER / WAIT_FOR_PLATFORM** until all of these exist on merged FANOOS `main`:

1. secure bot service authentication;
2. secure Telegram account-link resolution;
3. canonical critical platform-scoped update authorization;
4. durable update request/status/idempotency/lock contract;
5. privileged updater ownership and approved-source policy;
6. restart-safe terminal success/failure/rollback semantics;
7. machine-testable contract fixtures sufficient for bot code to consume without inventing endpoints or enums.

Until then, no production handler/button is safe to create.