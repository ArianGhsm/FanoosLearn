# FANOOS UI V2 — Bot Backend UI Gaps

These are contract gaps discovered while implementing the product journeys. No PHP/database/contract change is made on this parallel branch.

## BACKEND_UI_GAP-01 — complete course/enrollment list

**Need:** bot-safe list/detail projection for the linked user's courses in the selected workspace.

Current schedule, grades and resources rows expose canonical `course_id/course_title`, so V2 builds a safe presentation index from those authorized rows. A course with no current schedule, published grade or accessible resource can be absent.

Suggested future projection: linked subject + workspace → authorized enrollment/course list with stable course ID, code/title, term/offering-safe metadata and opaque cursor.

## BACKEND_UI_GAP-02 — course-scoped server filters

Current schedule/grades/resources APIs can return course IDs but do not accept a course filter. V2 re-fetches bounded authorized pages and filters the returned projection by canonical course ID.

A native `course_id` filter would reduce overfetch and make pagination complete for high-volume tenants.

## BACKEND_UI_GAP-03 — assessments

No bot-safe assessment catalog/detail/attempt/result projection exists in the frozen internal API.

Required before native bot exams:
- authorized assessment list/detail;
- start/resume/update/submit contracts or safe website-delegated actions;
- server scoring only;
- no answer-key leakage;
- revision/idempotency semantics;
- bot-safe status/result projection.

Until then the bot presents the IA destination and website CTA only.

## BACKEND_UI_GAP-04 — personal notification history

`NotificationPump` has project/claim/receipt delivery contracts but is not a canonical inbox/list query.

Need a subject/workspace-authorized notification-history projection with read state and bounded cursor. V2 does not build inbox truth from local delivery receipts.

## BACKEND_UI_GAP-05 — announcement course binding

The current announcement bot projection returns message/title/body/published/read fields but no canonical course/offering binding.

Course-specific announcements therefore cannot be filtered truthfully. Add a safe course/offering scope projection if product policy supports it.

## BACKEND_UI_GAP-06 — commerce catalog / access center

The current bot API can create an order and read order status when a product/order identifier is already known, but it lacks a user-facing bot-safe product catalog and entitlement/access list.

Needed for a fully native bot purchase journey:
- authorized product/offer list;
- safe product detail/price snapshot;
- current access/entitlement projection;
- order list/status lookup without asking the human for UUIDs.

V2 removes `/buy <product_id>` from primary UX and sends normal users to the configured FANOOS web surface.

## Integration rule

Prompt 3 may wire future backend additions only after reconciling both parallel UI branches. Do not add local bot tables or alternate APIs to close these gaps.
