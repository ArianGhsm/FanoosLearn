# FANOOS UI V2 — Bot Product Forensic Audit

Base: `74e0d4083a5311cd5d2e527af8b3baf4a46c96f6`  
Scope: shared Telegram + Bale presentation/application UX only.

## What existed

The Stage 7 bot foundation already had the important reliability/security pieces: signed canonical backend calls, canonical workspace selection, academic/content read projections, payment/order authority, protected delivery/media, notification claim/receipt, Telegram-only deployment control, callback acknowledgement ordering, Rich-to-plain presentation fallback and separate Telegram/Bale runtime state.

The product layer, however, was mostly a flat button grid. Home exposed direct endpoint-shaped actions; there was no first-class course surface, no predictable contextual back path, resources jumped directly to delivery, purchase copy asked the user for a product identifier, and the notification channel worker could easily be mistaken for a canonical inbox.

## Keep

- `FanoosApiClient` and frozen internal contracts.
- Canonical messaging link and workspace membership checks.
- Backend-owned RBAC, payments, entitlements, grades, resources and notification records.
- Protected delivery issue → consume → provider send → receipt.
- Personalized-media correlation as bounded transport state only.
- Telegram private-chat + `deployment.manage` owner flow.
- Bale prohibition on deployment controls.
- Callback ACK-before-work behavior.
- Presentation failure never replaying an application/business operation.
- Rich → plain fallback and capability-driven provider differences.
- Runtime offset/receipt retry safety.

## Product problems found

| Area | Old behavior | Risk / UX cost | V2 response |
| --- | --- | --- | --- |
| Home | Endpoint/button grid | Low context, no product continuity | Workspace + bounded today/recent context + product spine |
| Courses | No course hub | Users repeatedly scan global lists | Course list/detail derived from already-authorized projections |
| Navigation | Mostly home-level jumps | Context loss/dead ends | Standard contextual back + home on secondary screens |
| Resources | List could jump straight to delivery | No metadata/access explanation | Resource detail before secure delivery |
| Schedule | Today/tomorrow commands | No schedule hub/week journey | Schedule hub, today/tomorrow/week, pagination |
| Grades | One flat list | Weak course context | Course-aware labels plus course-scoped view |
| Announcements | One flat list | No distinction from personal notifications | Explicit `📢 اطلاعیه‌ها` vs `🔔 اعلان‌ها` |
| Notifications | Worker semantics only | Temptation to fake an inbox from local delivery state | Honest no-history state until bot-safe inbox API exists |
| Purchase | `/buy <product_id>` primary instruction | Developer UX + raw-ID burden | Website/catalog CTA; `/buy` retained only as hidden compatibility path |
| Account | Immediate unlink | Destructive tap with little explanation | Explicit unlink confirmation |
| Workspace | Flat list | Limited return context | Selected marker + predictable back/home |
| Owner | Hidden command | Correctly secure but disconnected from product IA | Role-aware `⚙️ مدیریت` in More after canonical overview |
| Bale | Shared semantics but basic formatting | Could feel like Telegram fallback | Dedicated readable semantic layout; no Telegram-only payload |
| Errors | Safe but inconsistent next action | Dead ends | Central Persian taxonomy + home/back where safe |

## Authority findings

The current bot-safe academic/content projections expose course identity in schedule, grades and resources. V2 therefore builds a **presentation-only** course index by deduplicating authorized `course_id/course_title` values from those projections. It is rebuilt before course-sensitive reads. It is not stored as enrollment truth.

The current contracts do **not** expose:
- a complete course/enrollment catalog;
- bot-safe assessment list/attempt/result projections;
- a personal notification-history/list projection;
- a commerce product catalog / entitlement list projection;
- a course identifier on announcement rows.

Those are documented in `BOT_BACKEND_UI_GAPS.md`; V2 does not invent local canonical data to compensate.

## Provider audit

Telegram already had native Rich rendering with a plain fallback and protected-content precedence. The official Telegram Bot API was re-checked on 2026-09-09: Bot API 10.3 exposes Rich Messages, structured blocks, RTL, tables, inline keyboards, Rich drafts and `protect_content`.

No new official Bale Rich-message contract comparable to Telegram was found during the 2026-09-09 re-check. V2 therefore keeps Bale bound to the repository's verified capability matrix: readable text, inline callbacks, edits/chat actions where supported, and fail-closed behavior where required content protection is unavailable.
