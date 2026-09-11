# FANOOS Stage 2 data/RBAC hand-off

Status: implemented locally and ready for review. No production migration or server action was performed.

## What is now canonical

- Directory data is represented by `country → province → city → institution → campus? → faculty → department? → program → cohort → workspace` tables. Values are data/configuration; no Dentistry, university or cohort branch is required in application code.
- `iam_users` is global identity. `tenant_workspace_memberships` is the many-to-many membership boundary, so one user can work in several active workspaces.
- `rbac_role_templates`, `rbac_role_permissions`, `rbac_scopes` and `rbac_role_assignments` provide server-side scoped authorization. Platform, institution, faculty, program, cohort and workspace scopes are resolved through canonical hierarchy joins.
- Authentication, CSRF, session expiry/revocation and workspace selection remain in `AuthService`; authorization remains in `ScopeAuthorizer`/`AccessGate`. No client-side `is_admin` value grants access.
- The `/api/v1/account` and `/api/v1/workspaces` projection now exposes safe workspace context: membership ID/status, hierarchy labels/IDs, timezone, selected marker, effective role keys and permission keys. It excludes ended memberships and never exposes credential material.

## Safe baseline roles

Seeds provide platform owner, institution/faculty/program/workspace administrators, cohort representative, content author/manager/reviewer, student, finance manager and deployment operator. Permissions are capability keys, not UI labels. High-risk payment, entitlement, audit, protected-content and deployment actions remain explicit.

## Migration and performance change

Applied migrations `0001`–`0010` were not rewritten. `0011_stage2_rbac_projection_indexes.sql` adds only additive indexes for active membership and user/scope role-context lookups and carries the expand-compatibility marker. The migration runner checksum ledger and interrupted-additive guard remain authoritative.

## Reuse and deliberate non-reuse

The existing FANOOS schema, `AuthService`, `ScopeAuthorizer`, `AccessGate`, migration runner and tenant-isolation fixtures were reused. Legacy auth/representative behavior remains evidence for future adapters; no legacy runtime, database, token, hard-coded cohort or UI authority was copied.

## Validation and next stage

Schema contracts now cover eleven migrations and the new expand migration. Tenant-isolation coverage verifies multi-workspace account projection, effective roles/permissions and selected-workspace markers in addition to cross-workspace foreign-key and IDOR checks. The next stage can build account/workspace screens against this projection, then proceed to infrastructure/release verification before any deployment.
