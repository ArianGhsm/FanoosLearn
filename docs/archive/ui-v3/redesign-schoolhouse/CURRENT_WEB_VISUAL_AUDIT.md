# Current FANOOS Website Visual Audit

**Audited source:** `bff3a973cb4ba6692ced404c232a335e2637c7b9`  
**Audit date:** 2026-09-10  
**Scope:** Website V3 presentation only

## Executive finding

The current V3 source has a materially stronger product/contract architecture than its perceived visual quality. The main failure is not missing functionality: it is that the UI still reads as a carefully engineered admin/application shell rather than a polished student product.

The redesign should therefore preserve the V3 domain/runtime architecture and replace presentation decisively.

## What is already good and must be kept

- one canonical V3 bootstrap and Shell router;
- explicit RTL document semantics;
- abort-before-replace workspace routing behavior;
- canonical workspace selection and zero-workspace behavior;
- secure protected-resource delivery bridge;
- server-authoritative assessment/payment/entitlement logic;
- course-first route architecture;
- mobile bottom navigation;
- accessible password visibility control;
- focus, reduced-motion and dialog foundations;
- no client-side GPA/average fabrication;
- no raw storage path in normal presentation.

## Why the current site feels weaker than the product

### 1. Root experience is still fundamentally login-first

`renderAuth()` is visually improved over a bare admin login, but the root remains a split authentication screen. There is no coherent public story, product explanation, editorial progression or confident hero before asking for credentials.

Effect: the product feels internal and utilitarian before the user understands what FANOOS is.

### 2. The old emerald palette dominates every interaction

Foundation tokens use an emerald primary and many V3 files reinforce it through local fallbacks. The result is coherent but conservative and visually closer to institutional tooling than the selected friendly EdTech direction.

### 3. Typography is too cautious

Authenticated headings generally peak around the low/mid 30px range and the public auth hero is the only place with a true display scale. Body/metadata sizing is functional but does not create an editorial rhythm.

Effect: major page purpose and secondary metadata are too close in visual weight.

### 4. Navigation carries too much visual weight

Desktop uses a persistent bordered sidebar with a boxed workspace selector, grouped navigation, and topbar/context layers. The Shell is competent but remains visually present at all times.

Effect: chrome competes with course/schedule/content.

### 5. Borders are doing work that spacing should do

Many screens rely on bordered cards, bordered toolbar containers, bordered state panels, bordered featured items and nested bordered regions.

Effect: the eye parses containers before content.

### 6. Cardification is unevenly high

Home correctly has a timeline/rail concept, but it still combines a framed hero, framed attention item, quick-action cards and multiple sub-surfaces. Courses use a card grid; Learning uses featured cards plus bordered detail/delivery surfaces; zero-workspace uses a grid that visually behaves as three equal panels.

Effect: different information priorities can appear equally important.

### 7. Shell proportions feel like a dashboard

A 252px sidebar, sticky topbar, breadcrumbs/context row and wide 1480px content canvas produce a recognizable admin/productivity-tool silhouette.

Effect: the interface feels managed rather than student-centered.

### 8. Public/auth and app do not yet feel like one brand journey

The current auth story uses one visual treatment, while the signed-in UI quickly becomes a dense operational shell. There is no shared high-confidence Website identity across the transition.

### 9. Mobile is technically deliberate but visually dense

The existing bottom navigation and sheets are good structural choices. The topbar + workspace context + page content + bottom bar can still feel chromed-in at 360–430px, especially when pages begin with additional filter/tool surfaces.

### 10. Empty states are structurally correct but often over-contained

V3 does not expose raw “No records found” strings, which is good. However, normal zero states are frequently placed in fully bordered panels that visually imply error/exception.

## CSS architecture finding

The V3 split by Foundation/Shell/domain is worth preserving. The problem is not the modular split; it is duplicated visual fallback values and each worker carrying its own conservative surface decisions.

The redesign therefore:

- changes Foundation tokens as the color/type/spacing source of truth;
- adds one explicit Website presentation authority after structural V3 CSS;
- does not create another JavaScript app layer;
- does not rewrite domain CSS/JS wholesale;
- does not use inline style proliferation or `!important` carpet bombing.

## Target change by area

| Area | Current issue | Redesign response |
| --- | --- | --- |
| Public root | split auth screen | complete editorial landing + login continuation |
| Color | emerald/institutional | warm cream + ink + blue-violet + amber |
| Type | compact hierarchy | expressive public display + clearer app page scale |
| Shell | dashboard silhouette | lighter sidebar/topbar, calmer selected states |
| Home | multiple framed priorities | one dominant next region + open timeline/support rail |
| Courses | conventional card grid | calmer, roomier course surfaces with title dominance |
| Schedule | boxed toolbar | planner-like low-noise controls |
| Resources | mixed rows/cards | rows for density; cards only for featured/recent |
| Empty states | over-contained | open/quiet where normal, bordered only when useful |
| Mobile | technically safe, visually dense | lighter chrome, retained bottom nav/safe areas |
| Auth | separate visual world | same palette/type/rhythm as public page |

## Non-visual findings intentionally not “fixed”

The audit found no justification to redesign:

- OpenAPI;
- database/domain ownership;
- bot callback semantics;
- Telegram/Bale provider presentation;
- payment/entitlement authority;
- protected-media contract;
- schedule timezone authority;
- assessment scoring authority.

Changing those in a visual task would increase risk without improving the stated problem.
