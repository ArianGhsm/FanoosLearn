# FANOOS Website Schoolhouse-Inspired Visual Review

**Design Lock:** `FANOOS-WEB-UX-2026.09-SCHOOLHOUSE-R1`  
**Review type:** source-level structured self-review  
**Live browser review:** required after production activation when browser tooling is available

## Public Home

- **Intentional within first paint:** yes at source level. The root now has a real header, large hero, product explanation and CTA before the login continuation.
- **Strong simple hero:** yes. One headline, one supporting paragraph, one primary CTA and one secondary CTA.
- **Whitespace:** materially increased; public sections use editorial vertical rhythm instead of a dashboard grid.
- **Expressive typography:** public display scale is separated from authenticated functional scale.
- **Educational/human:** language describes real student tasks rather than platform architecture.
- **Corporate dashboard avoidance:** no KPI grid, fake stats, fake logos, testimonials or stock image.
- **CTA hierarchy:** one primary action (“ورود به فضای من”) and one explanatory secondary action.
- **Original visual:** CSS lantern/day composition is authored for FANOOS; no Schoolhouse image, logo, mascot or runtime asset is present.

## Authenticated App

- **Shell recedes:** sidebar/topbar use canvas/surface contrast with fewer borders and no decorative gradient background.
- **Hierarchy:** app H1 scale increases, content edges breathe more, and Home retains a dominant next/today region.
- **Card count:** structural cards remain where domain workers need them, but the Website layer removes or softens borders/shadows from normal grouping and keeps rows for dense content.
- **One dominant purpose per page:** course title, schedule planner controls, library discovery and assessment/grade purpose remain domain-owned and visually emphasized.
- **Persian typography:** one local family with real 400/700 WOFF2 faces is applied across Website namespaces.
- **Mobile:** existing bottom-nav architecture remains, with lighter background, safe-area padding and reduced chrome.

## Global

- FANOOS keeps its own name, lantern metaphor, copy, colors and product spine.
- No `schoolhouse.world` runtime dependency exists.
- No Schoolhouse brand string is used in user-facing Website source.
- No Schoolhouse photo, illustration, logo, icon set, testimonial or marketing sentence is copied.
- No remote font/CDN was added.
- Existing accessibility, DOM-safety and server-authority contracts remain the governing behavior layer.

## Responsive source review

Explicit breakpoints and layout changes cover:

- 320–360px: reduced edge gutters, one-column features/products, protected CTA width;
- 390–430px: mobile hero and illustration composition, bottom navigation;
- 768px: tablet one-column/stacked editorial transitions;
- 1024px: intermediate public/shell density;
- 1280px+: full editorial two-column compositions;
- 1440px+: capped content width and intentional desktop margins.

No critical flow relies on a table-only layout introduced by this redesign.

## Accessibility source review

- public skip link added;
- canonical Shell skip link retained;
- semantic `header`, `nav`, `main`, `section`, `article`, `ol`, `footer`;
- heading order on public page is H1 → H2 → H3;
- focus-visible Foundation behavior retained;
- primary controls remain at least 44px;
- reduced-motion explicitly disables smooth scrolling and decorative transitions;
- status colors continue to be paired with text labels;
- auth labels/password visibility behavior retained;
- Shell modal/drawer focus behavior unchanged.

## Remaining live checks

Production browser validation must inspect at least 390px, 768px and 1440px after activation for computed typography, body overflow, focus traversal, menu/sheet focus return, asset cache correctness and any CSS collision that static source review cannot prove.
