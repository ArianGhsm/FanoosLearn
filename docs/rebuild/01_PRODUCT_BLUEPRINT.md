# FANOOS product blueprint — Stage 1 baseline

Status: architecture/product baseline for the local-first rebuild. This document records product boundaries and behavior; it is not a UI redesign or a deployment plan.

## Product spine

FANOOS turns a scoped learning space into a dependable loop:

```text
identity → workspace context → discover/author content → learn/assess →
progress/grades → commerce/entitlement → protected delivery →
notifications/feedback → audit and improvement
```

Every step is tenant-aware. The web, Telegram and Bale surfaces present the same backend truth and may not create a competing user, payment, entitlement or content authority.

## Personas

| Persona | Main outcomes | Default boundary |
| --- | --- | --- |
| Student/member | Find resources, study, take assessments, submit forms, see progress and purchased access | Own identity plus active workspace membership |
| Instructor/content author | Draft resources, attach provenance, prepare lessons/questions and submit review | Assigned course/resource/workspace scope |
| Reviewer/academic lead | Check quality, approve versions, publish or reject | Explicit review scope; publication is not implied by authorship |
| Representative/workspace administrator | Manage membership, academic setup, announcements, forms and workspace settings | One or more explicitly assigned workspaces |
| Finance/commerce operator | Configure offers, reconcile payments and inspect entitlement outcomes | Explicit commerce/platform scope; no secret values in responses |
| Platform operator/support | Operate the service, investigate audit events and manage platform-owned libraries | Platform scope with named permissions, never a blanket `is_admin` bypass |
| Channel user | Use Telegram or Bale menus, delivery and notifications | Channel identity linked to the canonical user and active workspace |
| Worker/service | Transform content, rasterize/protect media, index or deliver notifications | Least-privilege service identity and job capability; no domain ownership |

## Tenant and directory hierarchy

The directory is data, not application branches:

```text
Country → Province → City → Institution → Campus (optional)
        → Faculty → Department (optional) → Program/major
        → Cohort/entry → Workspace (tenant boundary)
```

An institution or cohort may have several operational workspaces. A workspace owns its memberships, offerings, resources, publications, orders, entitlements, announcements and channel context. Ancestors help discovery and inheritance but do not grant access by themselves. Sharing is an explicit publication/grant edge; it never silently transfers write ownership.

## Shared backend rule

`apps/platform` is the canonical backend and owns the domain tables and object metadata. The browser uses versioned `/api/v1` contracts; Telegram and Bale use the public/internal service contract with a service identity. All callers send actor/workspace/correlation context and idempotency keys for retryable commands. The server resolves membership and scope; a route, header, chat, cached profile or client claim cannot grant scope.

## Major modules and ownership

| Module | Owns | Important boundary |
| --- | --- | --- |
| Identity | Users, authenticators, verified contacts, sessions and channel links | Does not assign workspace permissions or entitlements |
| Directory/Tenancy | Geography, institution/program hierarchy, cohorts, workspaces and settings | Does not infer a tenant from a route string |
| Authorization | Scoped memberships, role assignments and permission decisions | No global admin bypass |
| Academics | Terms, courses, sessions, offerings and enrollments | Does not own grades or resource bodies |
| Content | Resources, versions, provenance, publication/grants and object metadata | Binaries live in object storage; protected originals are never public |
| Exams | Assessments, versions, attempts, answers, scoring and reports | Server owns scoring and access re-checks |
| Grades | Gradebooks, import batches, items and results | Every record is workspace/offering/enrollment scoped |
| Forms | Definitions, versions, audiences, submissions and attachments | Paid forms call Commerce/Entitlements; they do not create a payment engine |
| Commerce | Products, price snapshots, orders, payment attempts and reconciliation | Only verified provider results change payment state |
| Entitlements | Grants, validity, revocation and access decisions | Payment success is not inferred from a callback or bot cache |
| Notifications | Inbox, preferences, outbox intents and delivery receipts | Delivery is not domain truth |
| Search | Rebuildable tenant-scoped index | Re-authorizes before returning protected content |
| Audit | Append-only security/domain/operator events | Never stores secrets or mutable business state |
| Integrations | External aliases, connector metadata and service verification | Does not become the authority for users, grades or payments |
| Jobs/workers | Leases, attempts, capabilities and completion records | Commits results through the owning module/API |
| Migration | Source systems, aliases, checkpoints, rejects and reconciliation | Never writes raw target tables outside owner commands |

## Role and scope model

Authentication answers “who”; membership/RBAC answers “what may they do here”; entitlement answers “does access currently apply”. A decision carries `actor_id`, `workspace_id`, `scope_type`, `scope_id`, permission code and policy version. Initial scopes include platform, institution/faculty/program, cohort, workspace, course offering, resource and assessment.

Typical assignments are platform operator, institution/faculty/program administrator, workspace administrator/representative, instructor/author, reviewer, finance operator, and student/member. The same person may have different roles in different workspaces. Parent roles do not silently acquire high-risk actions such as payment reconciliation, protected-source management or export.

## Content-production pipeline

```text
lecture / voice / slides / references
  → source intake + provenance/license record
  → transcription/normalization
  → structured full note (versioned)
  → summary / study aids
  → question bank and quiz/mock assessment
  → author QA → reviewer approval → publication
  → entitlement check → protected delivery + analytics
```

Every derived artifact records its source version, processor/algorithm version, checksum and workspace/publication scope. Workers receive bounded job capabilities and post idempotent results; they do not write content tables directly. No standalone canonical “DentNote” implementation was found in the inspected legacy evidence, so the structured note/version contract is a Stage 1 design boundary, not a copied legacy schema.

## Payment and access boundaries

Commerce quotes and snapshots the offer, creates the order, verifies the provider result and emits the verified transition. Entitlements grants/revokes access with a reason and validity. Authorization still checks actor/workspace/resource scope. Content re-authorizes before issuing a short-lived upload/download/protected-delivery capability. Provider callbacks, browser return URLs, Telegram file IDs and caches are evidence or projections, never proof. Secrets stay in the external secret boundary.

## Web ↔ bot synchronization contract

1. The web and both channel adapters call the same versioned API/use cases.
2. Requests carry authenticated actor/service identity, active workspace, correlation ID and an idempotency key when retryable.
3. Account linking uses a short-lived, single-use challenge; channel IDs are aliases, not replacement users.
4. Bot menus are presentation/state-machine code only. Active workspace, memberships, orders, entitlements, attempts, notifications and protected issuances come from the platform.
5. Notification outbox events are committed with the domain change; channel delivery records are independently retryable and idempotent.
6. Before protected send, the bot rechecks authorization/entitlement and receives a scoped capability. Revocation must win races.

## Stage 1 decisions and deliberate gaps

- The existing PHP platform and current UI assets remain the implementation baseline; this stage does not redesign them.
- Legacy Dentistry, IntegratedDent1402Tums and VoiceMatnAIBot are read-only evidence and independent products, not FANOOS runtime dependencies.
- The new host, relational version, object provider, job supervisor and restore rehearsal remain Stage 2/Prompt 4 work.
- Anonymous browsing policy, final role vocabulary, licensed content inventory, collaboration scope and retention periods require explicit product decisions before schema/feature work.
