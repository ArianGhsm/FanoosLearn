# FANOOS module and data ownership

## Purpose

This document prevents duplicate business logic and parallel state. It defines the owner of each fact, the allowed callers, and the contracts between FANOOS modules and deployable processes.

All named modules are FANOOS modules. Legacy repositories are read-only evidence and are not modules, packages, services, or dependencies of this architecture.

## Ownership rules

1. Every durable fact has exactly one owning module.
2. Only the owning module writes its tables and object metadata.
3. Other modules use an application contract or an owner-produced read projection/event.
4. Web, bot and future app call use cases through the platform API; they never read canonical tables.
5. A database transaction may span multiple module-owned writes only through an explicit platform orchestrator and public module methods—not cross-module SQL.
6. Search indexes, caches, offline data and worker-local state are disposable projections.
7. `workspace_id` is resolved/validated by the server before a tenant-owned repository executes.
8. Secrets are not domain fields. Modules keep provider secret references, while Prompt 4’s secret boundary stores values.

## Deployable units

| Unit | Runtime responsibility | Durable authority | Allowed dependencies |
| --- | --- | --- | --- |
| `apps/platform` | Web, `/api/v1`, internal API, admin adapters, CLI/cron entrypoints | All canonical FANOOS domain tables and object metadata through modules | Canonical SQL, object adapter, provider adapters |
| `apps/telegram-bot` | Telegram update handling, conversation presentation, API calls | None for domain state; only update offset/bounded transport cache if required | Public/internal FANOOS API, Telegram |
| `apps/workers/content` | Parsing, transforms, summary/question/artifact jobs | None directly; results committed through internal API | Job capability, scoped object access, external providers |
| `apps/workers/protected-media` | PDF validation/raster/fingerprint and protected artifact creation | None directly; issuance/result committed through internal API | Job capability, scoped object access, PDF tools |
| Notification channel process/cron | Outbox delivery to web push/Telegram/Bale | Delivery leases/receipts through Notifications API/module | Notification job contract and channel providers |
| Future `apps/mobile` | User interface/API client | Device session and bounded client cache only | `/api/v1` |

An optimization may colocate a worker with the platform process, but it does not change ownership or permit direct table access outside its module.

## Module catalog

| Module | Owns | Exposes | Consumes | Must never do |
| --- | --- | --- | --- | --- |
| Identity | users, authenticators, verified contacts, sessions, recovery/verification challenges, channel identities/link challenges | authenticate/revoke, current actor, link/verify channel, public identity projection | Audit, SMS/channel adapter | Assign workspace permission, grant entitlement, expose credential material |
| Directory/Tenancy | geographic/institution/program hierarchy, cohorts, workspaces, workspace settings | hierarchy queries, workspace resolution/config | Audit | Infer tenant from route string alone, share workspace data implicitly |
| Authorization | memberships, role templates, permissions, scoped role assignments | `authorize(actor, permission, scope)`, membership context | Identity, Directory, Audit | Use a global `is_admin` bypass, accept a client claim as proof |
| Academics | terms, courses, sessions, offerings, enrollments | academic navigation, offering/enrollment lookup | Directory, Authorization | Store grades, exam answers or resource bodies |
| Content | resources, resource types, versions, provenance, publications/grants, bindings, object metadata, user resource interactions | catalog/detail/publication, create/version/review/publish, scoped download/upload capability | Authorization, Academics, Entitlements, Jobs, Audit | Store binary bytes in SQL, evaluate gateway callbacks, expose protected originals publicly |
| Exams | assessments, assessment versions/question links, attempts/revisions, answers, flags, reports/scoring records | catalog, start/resume/update/submit/reset/report | Identity, Academics, Content, Entitlements, Authorization, Audit | Trust client scoring, price products, create payment success |
| Grades | gradebooks/items/import batches/results | own-grade projection, scoped import/manage/export | Identity, Academics, Authorization, Audit | Use student number as global key, expose another workspace’s grades |
| Forms | definitions/versions, audience rules, submissions, responses, private attachment links | list/detail/submit/manage/export | Identity, Authorization, Academics, Content objects, Commerce/Entitlements when paid, Audit | Derive durable guest identity from IP/user-agent, create a separate payment engine |
| Commerce | products, price versions, orders, order lines, payment attempts, provider snapshots, reconciliation actions | quote/create order, begin/verify/result/reconcile | Identity, Directory, Authorization, provider ports, Entitlements orchestrator, Audit/Outbox | Store/return provider secret values, trust callback query as proof |
| Entitlements | grants, sources, validity/revocation, resource/product/access policies | check/grant/revoke/list with decision reason/version | Identity, Directory, Commerce events/commands, Authorization, Audit | Depend on bot cache, infer success from an unverified payment callback |
| Notifications | notification records, audiences, preferences, outbox channel intents, deliveries/receipts | create/list/read/preferences/broadcast, lease/complete delivery | Domain events, Authorization, Identity channel identities, Audit | Become source of domain truth, loop its own delivery event back into a duplicate send |
| Search | indexed documents, indexing cursor/failure state | tenant-scoped query and reindex | Owner module projections/events, Authorization | Become canonical content, return a protected object without reauthorization |
| Collaboration (deferred) | conversations, participants, messages, reactions, polls, read receipts and collaboration-media links | conversation/message/poll commands and authorized streams | Identity, Authorization, Content objects, Notifications, Audit | Recreate authentication, write other domain tables, or create a cross-workspace conversation without explicit membership |
| Audit | classified append-only audit/security events and retention metadata | append, authorized query/export | Actor/workspace context from all modules | Store raw secrets/tokens/passwords or silently mutate events |
| Integrations | service identities/keys metadata, nonces, connector configurations/status, external aliases | signed-service verification, connector command/status | Identity, Authorization, Academics/Notifications through contracts, Audit | Own canonical grades/users/payments, store secrets in response payloads |
| Jobs | jobs, attempts, leases, idempotent results, dead letters, scheduling metadata | enqueue/claim/heartbeat/complete/fail | Domain commands/events, service identity, Audit | Grant domain access or let workers select arbitrary workspace/object paths |
| Migration | source systems, batches, source snapshots, aliases, rejects, checkpoints | plan/import/status/reconcile/reverse where supported | Every owner’s validated import command | Write raw target tables, mutate source, hide rejects or conflicts |

## Canonical table-family ownership for Prompt 3

Names are prefixes/families, not the final complete schema.

| Family | Owning module | Required workspace rule |
| --- | --- | --- |
| `iam_*` | Identity | Global identity; workspace never inferred here |
| `directory_*`, `tenant_*` | Directory/Tenancy | Workspace row is the tenant root; hierarchy relationships are explicit |
| `rbac_*` | Authorization | Assignment includes scope type/ID; workspace assignments reference active membership |
| `academic_*` | Academics | Offerings/enrollments are workspace-bound; catalog identities may be global with explicit bindings |
| `content_*` | Content | Resource owner scope explicit; workspace publications/grants are explicit rows |
| `exam_*` | Exams | Assessment publication and attempts carry workspace context |
| `grade_*` | Grades | Every gradebook/import/result is tied to workspace/offering/enrollment |
| `form_*` | Forms | Definition publication and submission resolve to one workspace |
| `commerce_*` | Commerce | Product offer, order and payment attribution include workspace; provider configuration is environment/scope referenced |
| `entitlement_*` | Entitlements | Grant has subject, workspace, target, source and validity/revocation |
| `notification_*` | Notifications | Audience and receipt have explicit workspace or explicit platform scope |
| `search_*` | Search | Every indexed document includes authoritative workspace/publication scope |
| `collab_*` (only if approved) | Collaboration | Conversation and every message/poll/receipt have one workspace; participant membership is explicit |
| `integration_*` | Integrations | Connector/service configuration is environment plus allowed scope; external aliases include provider namespace |
| `job_*`, `outbox_*` | Jobs/Notifications as designated | Job/event stores workspace where relevant and least-privilege capability |
| `audit_*` | Audit | Explicit platform/workspace scope and retention class |
| `migration_*` | Migration | Source system/batch/snapshot/legacy key namespace; not tenant authority by itself |

## Global, directory, platform, and workspace ownership

Do not use nullable tenant columns as a shortcut for “global.” Use explicit owner/scope types.

| Data | Owner type | Sharing/authorization rule |
| --- | --- | --- |
| User identity | Platform/Identity | Same person may have multiple memberships; private identity projection is minimized per caller |
| Country/province/city/institution/program directory | Platform directory, with governed edit roles | Discoverable as configured; does not grant access to descendant workspaces |
| Permission definitions | Platform/Authorization | Versioned code/data vocabulary; assignments grant them at scope |
| Role templates | Platform default or workspace-defined | Template alone grants nothing; scoped active assignment required |
| Workspace settings/data | Workspace | No implicit read from ancestor/sibling; cross-workspace access requires explicit platform permission |
| Resource | One workspace or explicit platform library | Publication grant to each target; write ownership is not transferred |
| Product/price | Platform or one workspace with explicit offer | Order snapshots the resolved offer/workspace/currency/amount |
| Notification | Platform or one workspace | Audience resolution produces explicit recipients/receipts; sender permission checked in source scope |
| Connector | Institution/workspace/environment as modeled | Credentials and data flow limited to configured scopes |

## Key contracts

### Actor and request context

Every use case receives a server-created context equivalent to:

```text
actor_id
actor_type: user | service
session_or_service_id
workspace_id (required for tenant commands/queries)
requested_scope
correlation_id
idempotency_key (when retryable)
```

Clients may request a workspace, but middleware derives membership and scope from canonical records. Repositories never accept only a free-form workspace string from an HTTP body.

### Authorization decision

```text
authorize(actor_id, permission_code, scope_type, scope_id, workspace_id)
  -> allowed
  -> decision_code
  -> policy_version
  -> matched_assignment_ids (audit-only projection)
```

User-facing responses never disclose whether an out-of-scope resource exists. Sensitive denials record a redacted audit event.

### Entitlement decision

```text
check_entitlement(subject_user_id, workspace_id, target_type, target_id, at_time)
  -> allowed
  -> decision_code
  -> entitlement_id/version when allowed
  -> valid_until when applicable
```

Authorization and entitlement are both required where policy demands them. One cannot substitute for the other.

### Object lifecycle

```text
create upload -> pending object metadata
upload parts -> verify size/checksum/type
finalize -> verified object version
review/publish -> visible publication
request download -> reauthorize -> short-lived capability
archive/delete -> lifecycle state + retention/audit
```

The object adapter owns bytes; Content owns metadata/lifecycle. A failed upload never becomes a published resource.

### Payment lifecycle

```text
quote -> order snapshot -> payment attempt -> provider request
provider callback/return -> server verify -> verified/failed transition
verified transition -> entitlement command + outbox event
result/reconciliation -> same persisted state, never client assertion
```

The Commerce application service orchestrates the verified transition and Entitlements grant in one database transaction when both modules are local. Notifications are emitted through the same transaction’s outbox. Duplicate callbacks return the existing result.

### Exam attempt lifecycle

```text
authorize + entitlement -> start attempt(version)
save answers/flags(expected_revision) -> next revision
submit(expected_revision, idempotency_key) -> server score/report
review/reset per policy -> new state/event
```

The client never sends a trusted score or answer key.

### Job/worker lifecycle

```text
owner module enqueues job + capability requirements
service identity claims lease
worker obtains scoped input object capability
worker reports heartbeat/progress
worker submits checksumed versioned result with completion key
owner validates and commits domain result
retry returns prior completion or obtains a new lease; no duplicate domain effect
```

Workers do not receive general database credentials or arbitrary object prefixes.

### Notification delivery

```text
domain event -> notification record/audience -> channel intents
channel worker leases intent -> sends -> idempotent receipt
partial channel failure -> retry that intent only
```

Web inbox, Telegram, Bale and web push are channels over the same notification record. A bot is not a second notification authority.

## Module dependency direction

```text
Identity <-- Authorization --> Directory/Tenancy
                       |
                       v
                  Academics
                 /    |    \
             Content Exams Grades
                |       |    |
                +---- Entitlements <---- Commerce
                         |
                  Forms/Search/Clients

All domain modules -> Audit and Outbox
Jobs/Integrations -> owner module contracts, never owner tables
```

Circular code imports are prohibited. Where two modules need coordination, an application orchestrator depends on both public interfaces or an event connects them.

## Read models and reporting

- A module may publish a stable query DTO or event for a read model.
- Admin dashboards compose authorized projections; they do not join arbitrary private tables in templates.
- Search stores only the minimum indexed projection and source version. It rechecks authorization before opening a result.
- Analytics receives minimized events with classification/retention. It is not a substitute for immutable audit.
- Exports are jobs with explicit permission, workspace, filter, requester, expiry and audit metadata.

## Client ownership

### Web

Owns templates, FANOOS design components, accessible interactions, browser session/CSRF handling and optional offline shell. It may show optimistic UI but reconciles with server revisions.

### Telegram bot

Owns Telegram command/callback parsing, keyboards/messages, update offset, rate-friendly channel behavior and user-visible delivery status. It does not own account links, active workspace, payments, subscriptions, exams, grades, content catalog or authorization.

### Future app

Owns device UI, secure credential storage and bounded offline cache. It receives no privileged database/service credentials and uses the same API permission semantics.

## Protected-delivery ownership

| Fact/action | Owner |
| --- | --- |
| Resource/version and protected classification | Content |
| User access | Authorization + Entitlements |
| Delivery request and issuance ID | Content delivery use case |
| Job lease/retry | Jobs |
| Input/output object bytes | Object adapter via scoped capabilities |
| Fingerprint algorithm execution/version result | Protected-media worker |
| Canonical issuance/output checksum | Content delivery records |
| Telegram send and channel response | Telegram adapter |
| Final delivery receipt | Notifications/Content delivery contract as designated, keyed by issuance |

Authorization is checked when a request is accepted and immediately before a short-lived send capability is issued. Worker completion alone cannot authorize delivery.

## Failure and consistency behavior

| Failure | Required outcome |
| --- | --- |
| SQL commit fails | No domain change and no outbox event |
| Provider times out | Payment remains non-success; retry/reconcile with same attempt/idempotency identity |
| Worker crashes after upload | Completion retry finds checksumed result or orphan cleanup removes unreferenced object after retention |
| Notification channel fails | Other channel receipts remain; only failed intent retries |
| Bot cache is stale | Backend decision wins; sensitive action rechecks canonical API |
| Search result is stale | Detail/download reauthorization denies or returns current projection |
| Workspace membership revoked | New requests deny immediately; active sessions remain identities but lose workspace authorization |
| Object metadata commits but upload fails | Object remains pending/failed and invisible |
| Import row conflicts | Batch records conflict/reject; no silent overwrite or partial record mutation |

## Forbidden dependencies

- No FANOOS module reads a legacy repository path in production.
- No bot/worker direct connection to canonical SQL.
- No browser direct object listing or storage credential.
- No module writes another module’s tables.
- No feature-local authentication, payment, entitlement, notification, upload or audit store.
- No global mutable singleton containing current workspace.
- No route/year/university string used as an authorization decision.
- No runtime credential or operator specification committed to the repository.
