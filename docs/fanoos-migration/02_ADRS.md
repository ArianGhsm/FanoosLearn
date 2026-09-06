# FANOOS architecture decision records

Only decisions that constrain several later prompts are recorded here. Lower-level library, folder, UI component, index, and provider choices do not need ADRs unless they change these boundaries.

## ADR-001 — FANOOS-only monorepo

Status: Accepted

### Context

The FANOOS repository started with documentation only. The product needs coordinated PHP platform code, contracts, database migrations, Python bot/media workers, operations and cross-client tests. The user requires strict separation from Dentistry1402TUMS, IntegratedDent1402Tums and VoiceTranscriberBot.

### Decision

Use one monorepo containing only FANOOS-owned components. Legacy repositories remain external, read-only evidence. Do not merge histories, add them as submodules/subtrees, share their data/configuration, or create runtime dependencies on their paths/services.

### Consequences

- Contract and schema changes can update all FANOOS consumers atomically.
- Platform, bot and workers remain independently deployable.
- Any adapted unit must be deliberately reviewed, generalized, attributed and tested; bulk copy is forbidden.
- Repository CI must understand PHP, Python, contracts and migrations.

### Rejected alternatives

- Extend Dentistry1402TUMS in place: violates independent product and multi-tenant goals.
- One repository per FANOOS deployable immediately: adds contract/version coordination before team/scale evidence warrants it.
- Import legacy history/submodules: creates accidental coupling and risks secrets/runtime artifacts.

## ADR-002 — PHP modular monolith as canonical platform

Status: Accepted

### Context

Most proven web behavior is implemented in PHP and the supplied website environment is cPanel-oriented. The target nevertheless needs shared domain behavior for web, bot and a future app. Splitting each domain into a network service would create distributed transactions and operational load before a worker server exists.

### Decision

Implement the canonical backend/API and first web interface as a PHP modular monolith with explicit Identity, Tenancy/Authorization, Academics, Content, Exams, Grades, Forms, Commerce, Entitlements, Notifications, Search, Audit, Integrations and Jobs boundaries. Python remains appropriate for Telegram/channel adapters and media/content workers, which communicate through versioned contracts.

No full PHP framework is selected by this ADR. Composer/autoloading, HTTP routing, migrations and test tooling may be selected incrementally, provided deployability and reuse constraints are proven.

### Consequences

- Existing PHP behavior can be adapted without a language rewrite.
- Cross-module transactional use cases remain possible in one database/process.
- Boundaries must be enforced in code review/tests because process separation does not enforce them.
- A module can be extracted later only after load/ownership evidence, using its existing contract.

### Rejected alternatives

- Rewrite all server behavior in Python/Node: discards audited PHP behavior without requirement.
- Microservices from day one: unjustified operational and consistency cost.
- Let bot become backend: creates a parallel authority and blocks other clients.

## ADR-003 — One transactional SQL authority plus object storage

Status: Accepted with Prompt 4 environment capability gate

### Context

Legacy JSON/CSV/file stores provide useful normalization and migration behavior but cannot safely become a growing multi-tenant transactional authority. The website host direction suggests a MariaDB/MySQL-compatible database, while its exact capabilities are not yet inspected. Large binaries and protected originals do not belong in relational rows or public web roots.

### Decision

Use one MariaDB/MySQL-compatible relational database for canonical FANOOS domain state, with transactions, foreign keys, unique constraints, indexes and ordered migrations. Use an object-storage interface for bytes and store only metadata/checksums/classification/storage keys in SQL.

Prompt 4 must verify the production engine/capabilities. If it cannot meet the required controls, amend this ADR and select a capable relational engine; do not add a second canonical database. Local/CI tests must exercise the selected SQL semantics rather than relying only on permissive SQLite behavior.

### Consequences

- Prompt 3 schemas use a portable MariaDB/MySQL subset and application-enforced scoped repositories/composite constraints.
- Stable target IDs and source-scoped legacy aliases enable idempotent imports.
- Binary lifecycle requires coordinated pending/finalized metadata and object cleanup.
- Database and object backups must be reconciled by manifests/version references.

### Rejected alternatives

- Continue JSON/CSV as canonical storage: insufficient transaction/isolation/query guarantees.
- One database per workspace: excessive migration/operations overhead and hard cross-workspace identity.
- Store files as database blobs: poor fit for large media/protected processing and delivery.
- Use bot SQLite as shared state: violates owner/client boundary.

## ADR-004 — Workspace is the tenant boundary; authorization is scope-based

Status: Accepted

### Context

FANOOS must support cities, institutions, faculties, programs and cohorts without code branches. A global `is_admin` or legacy cohort-role string cannot safely express a user who belongs to multiple workspaces or an operator delegated to one academic scope.

### Decision

Use `workspace` as the mandatory tenant data/configuration/commercial boundary beneath the geographic/academic hierarchy. Use global users, workspace memberships, permission definitions, role templates and assignments with explicit scope type/ID. Evaluate actor + permission + canonical scope + workspace; deny by default.

Shared content uses explicit platform/workspace ownership and publication grants. `workspace_id = NULL` is not an implicit global scope.

### Consequences

- Adding an institution/program/cohort/workspace is a data operation.
- Every tenant table/query/unique rule needs a workspace route.
- Parent-scope role inheritance must be explicit and tested.
- High-risk capabilities require explicit permission even for broad managers.
- Cross-tenant negative tests are release gates.

### Rejected alternatives

- `is_admin` boolean: no delegation or scope boundary.
- Treat institution or cohort as the only tenant: cannot model multiple operational workspaces cleanly.
- Encode scope in routes/file names: repeats the audited legacy coupling.

## ADR-005 — Canonical backend contracts for every client and worker

Status: Accepted

### Context

Legacy behavior demonstrates the risk of duplicated payment, entitlement, notification and bot state. Web, Telegram and a future app must agree immediately on identity, membership, content access, exam attempts and payment results.

### Decision

The platform backend is the only canonical domain authority. Web, Telegram bot, future app and background workers use versioned public/internal HTTP and event contracts. They do not connect directly to canonical tables. Bot-local/cache/search/offline state cannot grant access and is disposable.

### Consequences

- API availability and contract compatibility become core operational concerns.
- Internal service identities require narrow scopes, replay protection and rotation.
- Sensitive operations reauthorize at execution/delivery time.
- Client-specific presentation can evolve without forking business rules.

### Rejected alternatives

- Shared direct database access: bypasses module authorization and couples deployments/schema.
- Bot-owned subscriptions/entitlements with eventual sync: creates conflicting authorities.
- Duplicate web/mobile business logic: inconsistent security and outcomes.

## ADR-006 — Transactional outbox, durable jobs, and idempotent edges

Status: Accepted

### Context

Payments, notifications, uploads, bot delivery and media processing cross database and unreliable networks. Legacy implementations contain useful idempotency and durable notification patterns. A mandatory external broker cannot be deployed confidently until Prompt 4 inspects the new environment.

### Decision

Commit domain writes and outbox records in one SQL transaction. Represent long-running work as durable jobs with claim leases, attempts, stable completion/idempotency keys and dead-letter state. Use at-least-once delivery with idempotent consumers. Keep the transport behind an adapter; SQL polling/cron is the initial portable mechanism, and a broker may replace transport without changing domain ownership.

### Consequences

- Every external callback/create and worker completion needs an idempotency contract.
- Operators can inspect/retry individual failed deliveries without replaying successful domain work.
- Database polling capacity must be measured; broker adoption is evidence-driven.
- Jobs grant scoped capabilities, not general database/object access.

### Rejected alternatives

- Send network notifications inside the request transaction: cannot atomically guarantee both effects.
- Fire-and-forget background work: loses events and auditability.
- Require Redis/RabbitMQ before host inspection: premature infrastructure dependency.

## ADR-007 — Incremental external adaptation, never an in-place rewrite

Status: Accepted

### Context

The audit found valuable behavior but also hard-coded tenancy and fragmented storage. The user requires the projects to remain entirely separate. A big-bang rewrite or in-place legacy refactor would either lose behavior or violate that boundary.

### Decision

Build FANOOS in vertical slices using legacy behavior as read-only evidence and sanitized compatibility fixtures. Keep legacy products available. Any future data import uses immutable exports, source-scoped aliases, batch/checksum/reject records and rerunnable transactions. Cutover occurs only after parity, isolation, backup/restore and rollback gates in Prompt 8.

### Consequences

- FANOOS can ship capabilities without destabilizing legacy products.
- Temporary feature gaps are explicit in a parity matrix rather than hidden by shared runtime calls.
- Import adapters are offline boundaries; normal FANOOS requests never read legacy stores.
- Intentional behavior changes need documented acceptance.

### Rejected alternatives

- Modify legacy code into FANOOS: violates project separation.
- Proxy FANOOS requests to legacy APIs indefinitely: makes legacy a production dependency and leaks old tenancy assumptions.
- Bulk database/file copy: loses ownership, provenance, conflict and tenant boundaries.
