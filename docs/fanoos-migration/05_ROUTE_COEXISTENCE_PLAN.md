# FANOOS route coexistence plan

## Rule

FANOOS routes use opaque tenant/entity IDs under `/api/v1/workspaces/{workspaceId}` and a new client shell. They never proxy to a legacy PHP API, read a legacy file store, share cookies, or infer a workspace from an old path.

## Route families

| Purpose | Canonical FANOOS route | Legacy compatibility action |
| --- | --- | --- |
| Account/workspaces | `/api/v1/account`, `/api/v1/workspaces` | no shared session; users authenticate to FANOOS |
| Academic space | `/workspaces/{workspaceId}` | old cohort path maps through an audited redirect table |
| Course/session | workspace route plus canonical entity ID | import aliases resolve once during redirect generation, not per request |
| Announcement/form | `/workspaces/{workspaceId}/announcements|forms` | only migrated public IDs may redirect |
| Payment return | `/api/v1/payments/callback` | old in-flight orders complete in the old system; never move an unverified attempt between authorities |
| Protected resource | authorization API followed by signed/private delivery | no redirect to a protected legacy original |

## Coexistence stages

1. Inventory old public URLs and classify each as active, redirectable, private, or retired.
2. Import entity aliases through `migration_legacy_id_mappings`; record source snapshot/checksum.
3. Generate a reviewed, static old-path → FANOOS-path redirect manifest. Ambiguous mappings remain on the old route.
4. Run both products independently. New writes go only to the explicitly selected authority; there is no dual write.
5. Activate redirects only after parity, entitlement and rollback checks for that route family.
6. Observe 404/redirect/error/payment metrics through an agreed window.
7. Retire a legacy route only with product-owner approval and proof that no unique data or active payment remains.

No redirect is activated by Prompt 5 because the server is unavailable and an approved route inventory/cutover window has not been supplied.
