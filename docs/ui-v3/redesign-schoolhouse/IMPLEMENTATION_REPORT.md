# FANOOS Website Schoolhouse-Inspired Redesign — Implementation Report

**Branch:** `redesign/web-schoolhouse-r1`  
**Starting main:** `bff3a973cb4ba6692ced404c232a335e2637c7b9`  
**Website Design Lock:** `FANOOS-WEB-UX-2026.09-SCHOOLHOUSE-R1`

## Scope implemented

Website presentation only.

Unchanged on purpose:

- Telegram/Bale UX architecture and provider renderers;
- callback/business semantics;
- OpenAPI and database contracts;
- auth/session authority;
- workspace membership authority;
- assessment scoring authority;
- grades authority;
- payment/entitlement authority;
- protected-media/delivery authority;
- updater/control-plane architecture.

## Current reference review

The current Schoolhouse.world public site and the official “next chapter” design announcement were reviewed on 2026-09-10. Only composition principles were extracted: expressive type, generous whitespace, low visual density, friendly geometry, controlled bright color, editorial public-page flow and minimal navigation.

No Schoolhouse runtime URL, logo, illustration, photo, testimonial, copy or branded icon is included in FANOOS source.

## Font

`Persian Fonts.zip` was inspected before source changes.

Selected family: the supplied `YekanBakh-Regular.ttf` and `YekanBakh-Bold.ttf`. Persian/Arabic UI glyphs are converted locally to WOFF2 and stored under:

`apps/platform/public/assets/fonts/yekanbakh/`

The 400 and 700 faces are real supplied weights rather than synthetic browser bolding. Latin/ASCII text falls through to the system stack for mixed RTL/LTR stability.

`font-display: swap` is used. No external font source or CDN is introduced.

`FONT_PACKAGE_NOT_SUPPLIED=false`

## Source changes

- `index.php`: adds complete Persian public landing composition and Website Design Lock marker while preserving one V3 application root/bootstrap.
- Foundation tokens: changes Website color, typography, spacing, radius, shadow and compatibility aliases centrally.
- `fonts.css`: two local WOFF2 faces using the supplied real 400/700 weights and a bounded Persian/Arabic Unicode range.
- `web-schoolhouse-r1.css`: bounded Website presentation authority for public landing, auth continuation, shell and domain visual reconciliation.
- Documentation: visual audit, Website visual addendum and source-level visual review.
- Tests: deterministic redesign contract validates public sections, local font, design-lock marker, original/no-runtime-Schoolhouse rule, responsive guards, local-only dependencies and preserved single V3 boot.
- CI: `web-ux` executes the redesign contract in addition to the existing V3 integration contract.

## Architecture choice

The V3 worker/domain split remains the structural layer because it already owns responsive behavior and interaction semantics. The redesign does not create a second frontend framework.

The visual cascade is:

`Foundation tokens → V3 structural/domain CSS → web-schoolhouse-r1.css`

The final Website layer is presentation-only, under 1,000 source lines, and contains no business state or API logic.

## Public Home composition

1. minimal FANOOS header;
2. large Persian hero;
3. truthful capability explanation;
4. course-first learning showcase;
5. schedule/resources/assessment/announcement product strip;
6. three-step usage explanation;
7. canonical access/security explanation;
8. final CTA;
9. clean footer;
10. sign-in continuation.

No fake users, statistics, partnerships, logos or testimonials were added.

## Authenticated visual changes

- lighter 232px desktop navigation;
- no decorative shell radial gradient;
- subtler active navigation;
- more content breathing room;
- stronger page-heading hierarchy;
- Home next/today region made visually dominant;
- calmer Course surfaces;
- lower-noise Schedule toolbar;
- Learning keeps rows for dense content and cards only for featured content;
- normal empty/state presentation reduced in visual severity;
- mobile bottom navigation retained and refined.

## Security and behavior confirmation

No Website redesign source writes canonical domain state beyond existing code paths. Existing DOM-safe node/text construction remains unchanged. No new `innerHTML` API rendering is introduced. No client authorization/payment/entitlement/scoring truth is added.

## Deployment

No server change belongs to the source phase. Production activation must happen only after exact PR-head CI, review, merge, exact main SHA resolution, FanoosLearn Server preflight/backup and canonical updater deployment.
