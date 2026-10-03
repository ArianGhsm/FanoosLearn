# FANOOS Bot V3 — Screen Acceptance

Design Lock: `FANOOS-UX-2026.09-R1`

This matrix is source-level acceptance for Telegram and Bale. It does not replace live provider validation after deployment.

| Surface | Canonical behavior | Telegram | Bale | Source acceptance |
| --- | --- | --- | --- | --- |
| Unlinked / onboarding | Clear account-link continuation; no fake local account state | Rich RTL when available + plain fallback | Provider-native text | PASS by source contract |
| Linked, no workspace | Full product shell; no invented workspace creation or membership | Compact actions | Compact actions | PASS by source contract |
| Workspace picker | Explicit selection; no automatic single-membership mutation | Inline buttons | Inline buttons | PASS by deterministic test |
| Home | Canonical bot-01 active Home; schedule/announcement empty vs unavailable kept distinct | Rich RTL primary path | Readable provider-native text | PASS by deterministic test |
| Courses | Canonical attached course projection only; no activity-derived course truth; IDs remain callback correlation | Bounded list/actions | Bounded list/actions | PASS by deterministic test |
| Course detail | Schedule/resources/grades only; assessment and course-announcement actions stay hidden until bot-safe/course-bound contracts exist | Compact buttons | Compact buttons | PASS by deterministic test |
| Schedule | Workspace-timezone authority; bounded navigation | Semantic list | Semantic list | PASS by source contract |
| Grades | Published self-grade data only; no fabricated average | Semantic list | Semantic list | PASS by source contract |
| Announcements | Canonical published announcements; bounded pagination | Semantic sections | Semantic sections | PASS by source contract |
| Resources | Authorized catalog only; no storage path/key exposure | Rich/detail actions | Text/detail actions | PASS by source contract |
| Protected text | Reauthorize through existing backend delivery contract | One atomic protected send | Fail closed when equivalent protection is unavailable | PASS by deterministic test |
| Protected PDF | Existing personalized derivative + receipt path; no source fallback | Protected document send | Existing application fail-closed rule | PASS by existing deterministic regression |
| Assessments | No local attempt/scoring authority when bot-safe projection is absent | Safe web continuation | Safe web continuation | PASS by source contract |
| Commerce | Order, payment and entitlement remain independent facts | Semantic status / web continuation | Semantic status / web continuation | PASS by deterministic test |
| Forms | No bot-local submission authority without explicit bot-safe contract | Web continuation | Web continuation | PASS by source contract |
| Account / unlink | Disconnects only current messaging link | Confirmation + completion states | Confirmation + completion states | PASS by source contract |
| Owner management | `deployment.manage` + private Telegram only | Permission-gated | Original management unavailable | PASS by deterministic test |
| Error / expired route | Safe recovery; no raw backend internals | Human message + navigation | Human message + navigation | PASS by source contract |

## Global acceptance rules

- Persian-first RTL hierarchy is preserved.
- Semantic emojis are retained where they improve scanning.
- Actions are bounded and provider density policy controls row packing.
- Raw UUIDs, storage paths, object keys, HMAC material, service credentials and backend error codes are not user-facing product copy.
- Callback payloads are routing correlation only and stay within provider bounds.
- Oversized callback state uses an expiring platform+subject-bound route reference.
- No presentation state creates membership, authorization, entitlement, payment proof, grading truth or deployment permission.
- Protection requirements are fail-closed.
- Protected business actions are not replayed because rendering or receipt delivery fails.
- Telegram rich rendering is presentation-only; a non-protected rich-render failure may fall back once to the same already-computed screen without replaying business logic.

## Deferred live acceptance

The following remain mandatory after deployment and are deliberately not claimed here:

- Telegram real-bot rich-message rendering and callback interaction;
- Bale real-bot formatting/callback interaction;
- protected Telegram forward-protection behavior on real media/text;
- protected-media worker end-to-end derivative delivery;
- live owner-management private-chat behavior;
- production browser/provider cross-channel smoke.
