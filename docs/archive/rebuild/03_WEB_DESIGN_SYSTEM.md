# FANOOS Website Stage 3 — design-system hand-off

Status: implemented locally on top of the existing V3 website stack. This stage changes presentation only; backend authority, authentication, CSRF, workspace isolation, payment, entitlement and protected-delivery behavior remain unchanged.

## Design principles

FANOOS is a Persian, RTL, student-centered learning product. The visual language is bright, calm and spacious: typography and whitespace establish hierarchy before borders and shadows. The Schoolhouse reference is used only for these qualities; no Schoolhouse brand, copy, image, logo, screenshot or runtime URL is used.

## Source-of-truth stack

```text
local YekanBakh font faces
  → foundation/tokens.css
  → foundation/base.css + components.css
  → V3 shell/domain modules
  → app/web-schoolhouse-r1.css
  → app/web-mobile-r1.css
```

`web-schoolhouse-r1.css` is a bounded Website presentation layer. It does not contain domain state or a second router. `bootstrap.js` remains the only V3 composition entry point and the shell remains the navigation/auth/session authority.

## Tokens

`foundation/tokens.css` owns the shared vocabulary:

- warm canvas and white/subtle/strong surfaces;
- charcoal text with secondary/tertiary contrast levels;
- FANOOS blue-violet primary, amber lantern accent and restrained coral/green semantic states;
- borders, surface/floating/hero shadows and control/surface/dialog/feature radii;
- `4px`-based spacing through generous section rhythm;
- local `YekanBakh Fanoos` typography with real 400/700 WOFF2 faces;
- 44px minimum controls, focus ring, reduced-motion timings and responsive container/edge sizes;
- explicit z-index layers for skip links, navigation, overlays, dialogs and toasts.

No screen should invent a new color, font family or interaction timing when a token exists. Semantic states always include readable text and are never communicated by color alone.

## Shared primitives

`foundation/base.css` and `foundation/components.css` provide namespaced V3 primitives for buttons, icon buttons, fields, select/search controls, badges/chips, page headers, surfaces, data lists, segmented controls, empty/loading/error/success states, skeletons, toasts, overlays, dialogs and sheets. Existing domain modules consume these classes rather than creating an admin-template visual language.

Icons use the local stroke SVG language in `foundation/icons.svg`; emoji are not primary Website navigation or action icons. CSS lantern, orbit and timeline geometry are original FANOOS motifs.

## Signed-out experience

`public/index.php` has one public root and one V3 app root. The public home order is:

1. minimal header and navigation;
2. truthful hero with one dominant login CTA;
3. real capabilities;
4. course-first learning explanation;
5. schedule/resource/assessment/announcement overview;
6. three-step “how it works” section;
7. factual access/security explanation;
8. final CTA and simple footer;
9. login continuation.

There are no fabricated partner logos, testimonials, user counts or institutional claims. The lantern/day-card hero is made from local HTML/CSS primitives and carries no external asset dependency.

## Authentication continuation

The login surface is rendered by the existing V3 shell with the same tokens and local font. It keeps visible Persian labels, password reveal control, `autocomplete`, 44px+ controls, focus-visible styling and an `aria-live` status region. Error text is mapped to concise human messages; raw provider/server payloads are never rendered. After authentication, only active account workspaces are shown and the canonical session/CSRF flow is untouched.

## Responsive and RTL contract

The source is mobile-first and intentionally covers 320px, 360px, 390/430px, 560px, 768px, 1024px, 1280px and wide desktop. Public sections collapse to one column, the product strip becomes a compact two-column grid, login inputs stay at a mobile-safe size, and authenticated navigation retains the canonical bottom navigation/more sheet. Logical CSS properties and RTL DOM order are used; body overflow and clipped Persian copy are forbidden.

## Accessibility contract

The page uses skip links, `header`/`nav`/`main`/`footer` landmarks, one meaningful heading path, visible labels, `aria-current`, status/error association, keyboard focus-visible rings, semantic buttons/links and reduced-motion handling. Dialog/sheet focus behavior is owned by the V3 shell. Status chips and states pair color with text/icon meaning.

## Validation and next hand-off

The Stage 3 contract tests cover local WOFF2 integrity, no remote UI dependencies, truthful landing copy, landmarks, focus/reduced-motion hooks, 320px minimum and responsive guards, XSS-safe DOM rendering, single V3 bootstrap/router and auth/session integration. The next stage may build authenticated account/workspace surfaces using the Stage 2 projection; it should not duplicate this token or auth layer.
