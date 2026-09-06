# FANOOS RBAC and scope matrix

Status: implemented authorization baseline

## Decision contract

The current application service exposes the equivalent of:

```text
decide(user_id, permission_key, target_scope_type, target_scope_id, workspace_id)
  -> allowed
  -> decision_code
  -> policy_version
  -> matched_assignment_ids (audit-only)
```

Evaluation is deny-by-default:

1. Require an active, non-deleted user.
2. Load the canonical target scope and require its type and workspace to exactly match the caller's server-resolved context.
3. Rebuild the target's ancestors from canonical institution/faculty/program/cohort/workspace relationships and resolve their explicit scope rows.
4. Find active, unexpired role assignments carrying the named permission.
5. Confirm the role template allows assignment at that assignment's scope type.
6. If the role requires membership, require an active membership in the target workspace.
7. Allow on a matching assignment; otherwise return a stable denial code.

The client-provided workspace is never proof. Callers must resolve the route/resource to a canonical `rbac_scopes` row before this check.

## Scope hierarchy

```text
platform
  -> institution
    -> faculty
      -> program
        -> cohort
          -> workspace
            -> course_offering | resource | assessment
```

An ancestor assignment is considered only when the role template explicitly allows that assignment scope. `parent_scope_id` records the expected navigation tree, but authorization reconstructs ancestry from canonical directory/tenant relationships instead of trusting that pointer. Parent scope does not automatically mean every permission. A platform super administrator is therefore an explicit platform assignment with an explicit permission bundle, not a code bypass.

## Generic roles

| Role key | Allowed assignment scope | Active workspace membership required | Intended use |
| --- | --- | --- | --- |
| `platform-super-admin` | platform | No | Explicit global operations and incident administration |
| `institution-admin` | institution | No | Approved administration over descendant scopes |
| `faculty-admin` | faculty | No | Approved faculty descendant administration |
| `program-admin` | program | No | Approved program descendant administration |
| `workspace-admin` | workspace | Yes | Settings, memberships, academics/content for one workspace |
| `cohort-representative` | workspace | Yes | Limited representative actions in the assigned workspace only |
| `content-manager` | workspace, course offering | Yes | Create, review, and publish content |
| `content-reviewer` | workspace, course offering, resource | Yes | View and review content without publishing |
| `student` | workspace, course offering | Yes | Normal learning actions and own data |
| `finance-manager` | workspace | Yes | Catalog, reconciliation, and explicit entitlement administration |

Commercial roles exist because commerce/payment/entitlement foundations are part of the approved target architecture. They grant nothing until assigned at a scope.

## Permission bundles

Legend: `✓` seeded, `—` not granted. This table is the generic baseline; later high-risk permission changes require a migration and review.

| Permission family | Super | Inst./Faculty/Program/Workspace admin | Representative | Content manager | Reviewer | Student | Finance |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Workspace view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Workspace settings | ✓ | ✓ | — | — | — | — | — |
| Membership view | ✓ | ✓ | ✓ | — | — | — | ✓ |
| Membership manage | ✓ | ✓ | — | — | — | — | — |
| Academic view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Academic manage | ✓ | ✓ | — | — | — | — | — |
| Resource view | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| Resource create | ✓ | ✓ | ✓ | ✓ | — | — | — |
| Resource review | ✓ | ✓ | — | ✓ | ✓ | — | — |
| Resource publish | ✓ | ✓ | — | ✓ | — | — | — |
| Protected resource manage | ✓ | — | — | — | — | — | — |
| Exam take | ✓ | — | — | — | — | ✓ | — |
| Exam manage | ✓ | ✓ | — | — | — | — | — |
| Own grade view | ✓ | — | — | — | — | ✓ | — |
| Grade manage | ✓ | ✓ | — | — | — | — | — |
| Purchase | ✓ | — | — | — | — | ✓ | — |
| Catalog manage | ✓ | — | — | — | — | — | ✓ |
| Payment reconcile | ✓ | — | — | — | — | — | ✓ |
| Entitlement grant | ✓ | — | — | — | — | — | ✓ |
| Notification receive | ✓ | — | ✓ | ✓ | ✓ | ✓ | — |
| Notification broadcast | ✓ | ✓ | ✓ | — | — | — | — |
| Audit view | ✓ | ✓ | — | — | — | — | ✓ |
| Integration manage | ✓ | — | — | — | — | — | — |

Self-only and ownership conditions, commercial entitlement checks, protected-delivery checks, and resource publication policy are additional domain conditions. RBAC alone never makes another user's grade “self,” never confirms payment, and never transfers content ownership.

## Required scenarios

| Scenario | Expected decision | Enforced by |
| --- | --- | --- |
| Student belongs to workspace A and B | May act only through a valid assignment/membership in the selected workspace | Separate memberships and assignments; exact workspace match |
| Representative assigned in workspace A | Representative permissions allowed in A only | Workspace-scoped assignment plus membership |
| Same representative requests B | Denied without revealing resource existence | No matching ancestor assignment in B |
| Global administrator requests A or B | Allowed only for permissions in the super-admin bundle | Explicit platform assignment and ancestor chain |
| Caller sends workspace B with scope A | Denied before role evaluation | `workspace_scope_mismatch` |
| Membership ends but role remains | Membership-required role denied | Active membership check |
| Role expires or is revoked | Denied | Assignment time/revocation predicates |
| Content published from A to B | B receives the explicit publication projection, not source write access | Publication edge plus separate target authorization |

The executable integration test covers the first five cases and both cross-tenant database constraints. Revocation/expiry tests should be extended with the first authorization endpoints.

## Administrative safety

- High-risk permissions remain separate: membership management, protected content, grades, payments, entitlement grants, audit, and integrations.
- No role is inferred from an email, phone, Telegram ID, URL, institution label, or cohort year.
- Role assignments record scope, validity, grantor, revocation timestamp, and reason.
- Sensitive authorization outcomes should emit redacted audit events once request middleware is added.
- Service identities and their narrower action scopes belong to the integration/security implementation in a later prompt; human roles must not be reused as worker credentials.
