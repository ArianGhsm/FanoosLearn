# FANOOS core platform adaptation report

## Outcome

Prompt 5 delivers a generic, tenant-scoped core vertical slice on the Prompt 3/4 data and infrastructure. It does not couple FANOOS to a legacy runtime, database, deployment, secret, product name, institution, cohort, or academic year. The legacy repository remained read-only evidence at its audited Git commit; no literal legacy source was copied.

The implemented path is:

```text
new FANOOS RTL shell / bot or future client
                    |
                 /api/v1
                    |
 canonical account + selected workspace + scoped RBAC
                    |
 academics / schedule / grades / announcements / forms / search
                    |
       orders -> verified payment -> entitlement
                    |
          protected-resource authorization
```

## Source behavior traced before adaptation

The source paths named by `01_REUSE_MAP.md` were inspected from the audited legacy commit. The observed behavior was used as a compatibility oracle:

| Capability | Evidence traced | Behavior retained |
| --- | --- | --- |
| Account/auth | `auth_store.php`, `auth_api.php` | PBKDF2 compatibility, rehash after login, revocable sessions, CSRF, generic login failures |
| Scoped management | auth guards plus `admin_api.php` | representative read/broadcast outcomes, explicit higher-risk admin permission |
| Grades | `grades_store.php`, `grades/grades_api.php` | own published-grade projection and import-batch foundation |
| Announcements | `notifications_store.php`, `notifications_api.php` | per-user inbox/read state and workspace audience expansion |
| Forms | `forms_api.php` | version-pinned submissions, duplicate prevention and lifecycle foundation |
| Search | `search_store.php`, `search_api.php` | result shape and access-filter expectation |
| Payments | `payments_store.php`, `payments_api.php`, `payments_gateway.php` | server price, immutable line snapshot, callback verification, result token, idempotency, history and reconciliation |
| Operations | `admin_api.php` | authorized composition of module projections rather than an unscoped owner bypass |
| Academic feed | `navid_api.php`, `navid_service.php` | normalized schedule outcome only; connector runtime remains deferred and isolated |

## Implemented core

### Identity, membership and administration

- A single `iam_users` identity owns identifiers, authenticators and hashed sessions.
- Login supports the audited PBKDF2 format and current PHP password hashes. PBKDF2 is rehashed to the current PHP default after a successful login. Plaintext, MD5 and SHA-1 credentials are deliberately rejected.
- Login throttling is keyed by SHA-256 digests of normalized identifier and source. The API returns one generic credential error.
- Session tokens and CSRF tokens are stored only as digests. Logout revokes the active session.
- Account projection lists all active workspace memberships. Workspace selection is persisted on the session and checked against route context.
- A representative may read members, broadcast announcements and manage forms only in the assigned workspace. Assigning a representative requires `membership.manage` and an active target membership.
- Scoped dashboards include only sections independently authorized for the actor. The global dashboard requires a platform-scoped `audit.view` grant.

### Academic, schedule and grade projections

- Navigation resolves institution → faculty → program → cohort → workspace from canonical directory rows, then returns terms, courses, offerings and sessions.
- Schedule queries require an explicit workspace and a bounded date interval; exam dates are normal schedule events.
- Student grades join the current workspace membership to enrollment, published gradebook and published result. No CSV file is a runtime authority.
- `grade_import_batches` adds a checksum/idempotency boundary for the fuller import workflow without making Prompt 5 depend on one legacy CSV layout.

### Announcements, forms and search

- Publishing an announcement expands only active memberships in that workspace, writes inbox recipients, appends an audit event and emits a transactional outbox event.
- Forms have immutable schema versions and version-pinned submissions. Server validation checks stable field IDs, supported types, required answers, one-response policy and idempotency keys.
- Search is a disposable `search_documents` projection. The query always filters by `workspace_id`, then rechecks the permission associated with every result type before returning it.

Search was rewritten rather than extracted because the audited implementation assembled product-specific files and hard-coded routes. The replacement preserves query/result behavior but makes the index disposable, tenant-keyed and subject to authoritative RBAC on every read.

The cohort catalog was also replaced because source constants cannot represent multiple independent institutions. Navigation now derives entirely from directory/workspace records.

### Commerce, payment and entitlement

- The server selects the active product and price; a client never submits the payable amount.
- Orders retain an immutable line/name/price snapshot and a tenant-local idempotency key.
- Callback and provider authority values are matched through digests. Provider verification decides success; callback query/body status alone cannot mark an order paid.
- Finalization locks the order, makes duplicate successful callbacks no-ops, records failures explicitly and grants entitlements only after verified success.
- Manual reconciliation uses the same finalizer as callbacks and leaves a reconciliation record.
- Entitlement grants are idempotent, workspace/scope constrained, membership-aware and revocable with a reason.
- Protected-resource decisions require published content, `resource.view`, any configured entitlement and a verified object before delivery.

`PaymentGateway` is the provider boundary. Prompt 5 includes only `FakePaymentGateway`, enabled exclusively when `FANOOS_ENV` is `development` or `test` and `FANOOS_PAYMENT_GATEWAY=fake`. No real gateway credentials are needed yet. A live adapter and credentials should be requested only when a provider and integration environment are selected.

## Shared API and user interface

`contracts/openapi/core-v1.yaml` is the common contract for the website, bots and future apps. All success/error payloads use a versioned envelope. Browser requests may use an HttpOnly session cookie; non-browser clients use the same bearer session. Mutating authenticated requests require CSRF.

The new Persian RTL shell is responsive and branded only for FANOOS. Its JavaScript renders server responses and manages navigation/session UX; permissions, prices, form validation, search authorization and access decisions remain on the server.

## Intentional security differences

- Insecure legacy password representations are not accepted.
- There is no source-coded owner identity, institution, cohort, year, course or role bypass.
- Guest-by-IP form identity was not reproduced; Prompt 5 forms are canonical-account submissions.
- Gateway secrets are neither database fields nor admin response properties.
- Search does not trust a prefiltered index as authorization.
- An entitlement does not replace membership or RBAC, and RBAC does not replace entitlement.

## Validation coverage

The integration suite uses two unrelated university/workspace branches and covers:

- successful/failed login, legacy hash rehash, throttling persistence, CSRF and session workspace selection;
- one-workspace representative and a two-workspace student;
- representative permissions, privileged role assignment denial, global-admin assignment and global-dashboard denial;
- academic navigation, schedule, published grades, announcement read state, forms and search in isolated workspaces;
- payment success, failure, duplicate callback and reconciliation;
- entitlement grant/revoke and protected-resource authorization;
- audit creation and API success/error envelopes.

The fake provider performs no network operation or charge. No host, server, cPanel, legacy repository or production service was changed.

## Deferred without hidden coupling

- OTP provider integration, account recovery and device/session management UI.
- Full grade import parser and operator UI; the checksum batch boundary is ready.
- Form response export, receipt/object fields and explicit signed guest-token policy.
- Recurrence expansion, reminder worker and institution/Navid connector adapter.
- Live Zibal/Zarinpal (or another selected provider) adapter and sandbox verification fixtures.
- Search indexing workers and production-shaped ranking/load tests.
- Content production, review and secure delivery jobs, which belong to Prompt 6.

These are explicit follow-ups, not calls back into a legacy runtime.

## Prompt 6 handoff

Prompt 6 can consume these stable boundaries:

- identity: canonical `user_id`, active session and workspace membership;
- tenant context: explicit `workspace_id` plus scoped RBAC decisions;
- content: resource/version/object/binding/publication tables, access policy and protected authorization decision;
- commerce: product target scope, server-owned order snapshot, verified-payment result and entitlement grant;
- storage: Prompt 4 object interfaces and verified-object invariant;
- operations: idempotency, outbox, durable jobs and append-only audit;
- clients: `/api/v1` envelope and OpenAPI contract;
- reviewers/admins: `resource.create`, `resource.review`, `resource.publish`, scoped dashboard and audit permissions.

Legacy content sources remain evidence/import inputs only. Prompt 6 must adapt licensed content into FANOOS resource/version records, preserve provenance, and never introduce a runtime dependency on those sources.
