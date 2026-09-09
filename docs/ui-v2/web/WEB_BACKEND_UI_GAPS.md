# FANOOS UI V2 — Backend/UI Gaps

These gaps were found while mapping the audited canonical Web/API contracts. UI V2 does not create parallel authority to hide them.

## BACKEND_UI_GAP-01 — Public Web notification inbox

**Observed:** Announcements have a public authenticated Web projection. Notification projection/claim/receipt flows are internal service contracts for channels/bots.

**UI behavior:** Website presents a strong Announcements destination and does not invent unread personal notifications from bot infrastructure.

**Prompt 3 integration need:** Add a user-scoped public notification inbox/preferences/read-state projection if the Website is intended to own this surface.

## BACKEND_UI_GAP-02 — Purchasable product catalog

**Observed:** Web order creation accepts `product_id`, and order history exists, but no audited public catalog projection provides eligible products, human labels, price/currency, capacity, or entitlement state for browsing.

**UI behavior:** Purchase & Access shows current order state only. There is no product-ID text field and no fabricated purchasable catalog.

**Prompt 3 integration need:** A workspace/user-aware catalog projection with immutable price display metadata and canonical eligibility/access state.

## BACKEND_UI_GAP-03 — Browser binary handoff after secure delivery

**Observed:** Public delivery issue/consume supports `channel=web` and reauthorizes access. `consume` can return a signed `download_token`, but no public browser byte-serving route was identified in the audited Web contract.

**UI behavior:** UI can execute issue/consume authorization, but it does not synthesize an object URL or expose object/storage identifiers. It reports that final binary serving is not available from the current public projection.

**Prompt 3 integration need:** A bounded same-origin browser download endpoint consuming the signed token, with content disposition/type/size controls and no storage path disclosure.

## BACKEND_UI_GAP-04 — Complete Web assessment attempt UX projection

**Observed:** Canonical assessment services support catalog and server-owned attempt/scoring behavior, and start/save/submit/review endpoints exist. The current Website has no complete presentation contract for resume/history/question navigation/result-list composition comparable to a production exam UI.

**UI behavior:** V2 builds catalog/type/course/detail architecture but does not duplicate scoring or hydrate hidden answers in JS.

**Prompt 3 integration need:** Explicit Web attempt projection with current question, revision, allowed actions, timing/status, resume/history/result references and safe feedback visibility rules.

## BACKEND_UI_GAP-05 — Form submission history/status

**Observed:** Active form listing and submission exist; no general self-service submission history/status/result projection was found in the audited Web routes.

**UI behavior:** V2 renders active forms and submits recognized schema fields; unsupported schema is explained rather than dumped.

**Prompt 3 integration need:** User-scoped submitted/completed/result/history projection if required by product definition.

## BACKEND_UI_GAP-06 — Course-scoped announcements

**Observed:** Announcement Web rows do not expose a reliable course/offering relation.

**UI behavior:** Announcements remain workspace-level; Course Detail does not fake an Announcements tab.

**Prompt 3 integration need:** Optional canonical `course_id`/`offering_id` scope in the public projection when a message is genuinely course-scoped.

## BACKEND_UI_GAP-07 — Grade term/completeness policy

**Observed:** Self-grade rows provide published score items and course metadata but not enough policy/completeness data to guarantee a term GPA or weighted average.

**UI behavior:** Group by course and render `score/max`; no GPA/average.

**Prompt 3 integration need:** If summaries are desired, expose server-calculated/declared gradebook weighting and completeness or an authoritative summary projection.

## BACKEND_UI_GAP-08 — Instructor/course metadata completeness

**Observed:** Academic projection reliably supports course/session grouping but does not consistently expose instructor metadata used by the target brief.

**UI behavior:** Instructor-like metadata is shown only when an existing projection supplies it; never inferred.

**Prompt 3 integration need:** Optional canonical instructor/course-offering presentation fields.

## BACKEND_UI_GAP-09 — Linked channel account management

**Observed:** Bot channel identities/linking exist through canonical/internal contracts, but the current public `/account` projection does not provide a full Web linked-channel management surface.

**UI behavior:** Account shows profile/workspaces without pretending Telegram/Bale linkage status.

**Prompt 3 integration need:** Public account projection/action contract for linked channels if Website management is desired.
