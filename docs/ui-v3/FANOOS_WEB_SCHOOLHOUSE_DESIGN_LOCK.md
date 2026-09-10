# FANOOS Website — Schoolhouse-Inspired Visual Design Lock

**ID:** `FANOOS-WEB-UX-2026.09-SCHOOLHOUSE-R1`  
**Scope:** Website presentation only  
**Parent semantic lock:** `FANOOS-UX-2026.09-R1`  
**Reference review date:** 2026-09-10

## Precedence

1. Security and business invariants.
2. Canonical FANOOS architecture, OpenAPI contracts, data/module ownership and deployment contracts.
3. This document for Website visual and presentation behavior.
4. `docs/ui-v3/FANOOS_UI_UX_DESIGN_LOCK.md` for shared semantics, accessibility and behavior.
5. Previous Website V3 visual implementation.

This addendum does not change Telegram/Bale presentation, provider behavior, callback semantics, domain logic, database ownership, payment/entitlement authority, protected-delivery rules or updater architecture.

## Reference intent

The Website takes inspiration from the current Schoolhouse.world design direction only at the level of design principles:

- large, confident educational typography;
- generous whitespace and low visual density;
- friendly geometry with selective rounded surfaces;
- bright but controlled color;
- editorial section flow on the public page;
- human/student-centered tone;
- minimal navigation and clear CTAs;
- hierarchy through composition and spacing before borders/shadows.

FANOOS must remain original. Runtime dependencies, brand names, logos, copy, testimonials, photos, illustrations, mascots, proprietary icons and other Schoolhouse assets are forbidden.

## Website color tokens

```css
--f3-bg: #F7F4EC;
--f3-bg-elevated: #FCFAF6;
--f3-surface: #FFFFFF;
--f3-surface-subtle: #F1EFE7;
--f3-surface-strong: #EAE6DA;

--f3-text: #22243A;
--f3-text-secondary: #62657A;
--f3-text-tertiary: #85879A;
--f3-text-inverse: #FFFFFF;

--f3-border: #E4E0D5;
--f3-border-strong: #D4CEBF;

--f3-primary: #4954D6;
--f3-primary-hover: #3D47C1;
--f3-primary-pressed: #313AA5;
--f3-primary-soft: #ECEEFF;

--f3-accent: #D86658;
--f3-accent-soft: #FFF0EC;
--f3-lantern: #F0B43C;
--f3-lantern-soft: #FFF3CD;

--f3-success: #287A5A;
--f3-success-soft: #E7F5EE;
--f3-warning: #94620D;
--f3-warning-soft: #FFF0CF;
--f3-danger: #B43E4D;
--f3-danger-soft: #FDECEF;
--f3-info: #3B63C8;
--f3-info-soft: #EAF0FF;
```

Use color blocks selectively. The default canvas is warm off-white. White is for real content surfaces, not every group. Primary blue-violet is the main action/selection color. Amber is the FANOOS light/lantern accent. Coral and green are secondary semantic accents, not decorative wallpaper.

## Typography

Primary Website family: `IRANSansX Fanoos`, sourced only from the user-supplied `IRANSansXVF.ttf` and served locally as a WOFF2 format conversion.

Rules:

- `@font-face` must use `font-display: swap`.
- Variable weight range `100 1000` is valid and real; do not synthesize bold from a regular master.
- No external font CDN.
- Use the family throughout public and authenticated Website surfaces.
- Public hero may reach approximately `3.1rem–5.65rem` when viewport permits.
- Authenticated H1 is approximately `2rem–2.8rem`; internal UI remains more compact than marketing content.
- Persian body copy uses comfortable line-height near `1.85–2`.
- Avoid bold-everywhere. Default body is regular; metadata regular/medium; key actions semibold/bold; page/hero headings bold/extra-bold only when hierarchy calls for it.
- Mixed Latin/numeric tokens use the same family where possible; unavoidable technical tokens use isolated monospace and must never become user-facing authority.

## Spacing and density

Public sections use large vertical rhythm, generally `72–124px`, with stronger hero/final-CTA spacing. Authenticated content uses approximately `24–52px` between major groups.

Separate content by:

1. whitespace;
2. typographic hierarchy;
3. background shift;
4. light divider;
5. border;
6. shadow, only when the surface must float.

Do not default to equal cards for unrelated facts.

## Shape

- controls: `12px` or pill where the control is intentionally compact/CTA-like;
- standard content surfaces: about `16–20px`;
- feature/hero surfaces: about `24–40px` where composition benefits;
- dialogs/sheets: about `22–26px`;
- status/chips: pills.

Rounded geometry is a friendliness tool, not a requirement that every section become a card.

## Depth

Shadows are restrained. Normal content should be readable with background, spacing and borders alone. Use a larger shadow only for truly floating UI such as a modal/sheet or the original hero illustration layer.

## Original FANOOS visual motifs

Allowed motifs include:

- original lantern/light geometry;
- circles/orbits and abstract academic paths;
- original timeline/course compositions;
- small hand-drawn-feeling accents built from simple CSS/SVG primitives.

The public hero uses an original CSS lantern/day composition. It must not use Schoolhouse illustrations or runtime URLs.

## Icons

Website iconography must remain one local stroke-language. Existing V3 local icons are preferred. Emoji are not primary Website navigation or action icons.

## Motion

Motion is quick and subordinate:

- micro interaction near `140ms`;
- surface change near `180ms`;
- overlay change near `220ms`;
- no scroll-jacking, parallax, bounce loops or decorative autoplay;
- `prefers-reduced-motion: reduce` disables nonessential transitions and smooth scrolling.

## Public / signed-out page

The root Website must present a real public educational landing experience before the login continuation.

Required order:

1. minimal header;
2. hero with truthful product copy and one strong primary CTA;
3. capability explanation;
4. course-first learning explanation;
5. schedule/resources/assessment/announcement showcase;
6. three-step usage explanation;
7. factual access/security explanation;
8. final CTA;
9. simple footer;
10. login continuation.

Forbidden: fake users/statistics, fake partners, fake testimonials, stock photography, fabricated institutional claims, admin-dashboard first impression.

## Sign-in

The login form is a continuation of the same product:

- minimal fields;
- existing safe password visibility toggle retained;
- visible labels;
- accessible inline status/error;
- no raw server payload;
- mobile input size must avoid viewport zoom;
- no empty-space floating admin card or glassmorphism.

## Authenticated shell

Desktop navigation must recede behind content. Sidebar is light, low-contrast and around `232px`; active state uses a subtle primary-soft field. Workspace and account remain predictable and visible.

Mobile keeps the canonical bottom navigation and More sheet. Safe area, 44px+ targets and focus behavior remain mandatory.

## Home / Today

Home answers:

1. where am I?
2. what matters today?
3. what is next?

The existing canonical data composition remains unchanged. Presentation uses one dominant next/today hero, one timeline, and lower-priority support sections. Do not turn every item into an equal KPI card.

## Courses

Course title is dominant. Metadata is compact. Two-column cards may be used on larger screens but must collapse to one column and remain low-noise. Course Detail preserves the course context and existing safe domain embeds.

## Schedule

Agenda/Today is visually primary. Week and Upcoming remain distinct canonical modes. Workspace timezone is the only business-time authority.

## Learning / resources

Search/filter controls are compact and obvious. Dense library content is rendered as rows where possible; featured/recent items may use cards. Protected-state visuals never weaken or infer access.

## Assessments and grades

Assessment UI is task-focused, not admin-tabular. Grade UI shows only canonical published values and score/max when provided. No browser GPA/average fabrication.

## Announcements, forms, purchase/access, workspace, account, management

All share the same tokens and surface economy. Management may be denser but may not become a separate admin template. Payment and access remain different facts. Internal IDs remain hidden.

## Empty, loading and error states

Normal empty data is calm and concise, with a short title, explanation and next action when available. Do not use giant warning banners for normal zero states. Zero-workspace remains useful and complete.

## Responsive contract

Intentionally support:

- 320px;
- 360px;
- 390/430px;
- 768px;
- 1024px;
- 1280px+;
- wide desktop.

No horizontal body overflow, clipped Persian text, hidden primary CTA, table-only critical path, unsafe bottom navigation or giant desktop gutter is acceptable.

## Accessibility

WCAG 2.2 AA intent remains inherited and mandatory:

- semantic landmarks;
- skip link;
- sensible headings;
- keyboard navigation;
- `:focus-visible`;
- 44px minimum primary control targets;
- `aria-current`;
- labels and accessible errors;
- dialog/drawer focus containment and return;
- reduced motion;
- contrast;
- no color-only meaning;
- sensible RTL DOM order.

## Architecture rule

The Website presentation stack is:

`Foundation tokens → existing V3 structural/domain CSS → web-schoolhouse-r1.css presentation authority`

The final layer is intentionally bounded to Website presentation. It must stay materially smaller than a parallel framework and must not contain client business state or duplicate domain JavaScript. New visual work should prefer modifying central tokens or this explicit Website layer over adding ad-hoc inline styles or per-screen override files.

## Runtime dependency lock

The Website must have:

- no `schoolhouse.world` runtime dependency;
- no Schoolhouse asset URL;
- no remote font/CDN dependency;
- one V3 bootstrap;
- one canonical router;
- no V2 primary boot;
- local FANOOS font and icon assets only.
