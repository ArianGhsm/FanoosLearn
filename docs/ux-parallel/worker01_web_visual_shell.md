# Worker 01 — Web Visual System + Shell + Accessibility

## Baseline and ownership

- Repository: `ArianGhsm/FanoosLearn`
- Immutable parallel base: `fef86a4adcad99cb75327582750dd4cd2df88dea`
- Branch: `ux/01-web-visual-shell`
- Scope: presentation only. No business logic, API, schema, authorization, payment, entitlement, delivery, tenant, bot, workflow, ops or deployment behavior changed.

## Files changed

- `apps/platform/public/index.php`
- `apps/platform/public/assets/app.css`
- `apps/platform/public/assets/web-shell/icons.svg`
- `tests/ux/worker01_static_quality.py`
- `docs/ux-parallel/worker01_web_visual_shell.md`

`apps/platform/public/assets/app.js` was audited read-only and intentionally remains unchanged.

## Design token system

`app.css` now exposes a semantic token layer instead of page-specific raw values:

- surfaces: `--color-background`, `--color-background-soft`, `--color-surface`, `--color-surface-raised`, `--color-surface-tinted`
- text: `--color-text`, `--color-text-secondary`, `--color-text-muted`, `--color-text-on-primary`
- borders/brand: `--color-border`, `--color-border-strong`, `--color-primary`, `--color-primary-hover`, `--color-primary-soft`, `--color-accent-gold`
- states: success/warning/error/info foreground and soft-surface tokens
- elevation: `--shadow-xs`, `--shadow-sm`, `--shadow-md`
- radius: small through XL plus pill
- spacing: `--space-1` through `--space-10`
- typography: system Persian-capable sans stack, mono isolation stack, font sizes, line-heights and weights
- interaction: `--focus-ring`, `--focus-ring-shadow`, `--motion-fast`, `--motion-normal`, `--touch-target`

The existing green/cream identity is retained and tightened rather than replaced. No final brand palette or logo was invented.

## Persian typography

- Removed Georgia/Times treatment from Persian headings.
- Uses local/system fonts only: `system-ui`, Apple/Windows system faces, `Segoe UI`, `Tahoma`, `Arial`, sans-serif fallback.
- No font binary, CDN, `@import`, or remote icon/font dependency.
- Persian body line-height is increased and heading hierarchy remains semantic (`h1`/`h2`/`h3`).
- `.technical-token`, `code`, `kbd`, and `samp` use LTR direction plus `unicode-bidi: isolate` so URLs/SHA/IDs can remain untouched when Worker 2 needs them.

## Brand and icon decisions

The existing `ف` brand mark remains a polished placeholder because no canonical final logo asset exists in the baseline. It is isolated in `.brand-mark` so a future integration can swap the mark without restructuring the shell.

Visible two-letter module abbreviations were removed. Modules now use a same-origin SVG symbol sprite at `assets/web-shell/icons.svg` with one visual language: 24px line icons, rounded caps/joins, inherited color, and consistent 42px icon containers. Icons are decorative (`aria-hidden`) because adjacent text provides the accessible name.

Symbols:

- `calendar` — برنامه
- `grades` — نمرات
- `announcement` — اطلاعیه‌ها
- `academics` — فضای آموزشی
- `resources` — منابع
- `assessment` — تمرین و آزمون
- `forms` — فرم‌ها
- `access` — خرید و دسترسی

## Preserved `app.js` hooks

The exact IDs consumed by the frozen JS remain present:

`login-panel`, `login-form`, `dashboard`, `workspace-picker`, `workspace-select`, `logout`, `greeting`, `workspace-path`, `today`, `result-list`, `view-title`, `search-form`.

The exact clickable `data-view` values also remain on buttons so existing `querySelectorAll('[data-view]')`, click handlers and `.active` toggling continue to work:

`schedule`, `grades`, `announcements`, `academics`, `resources`, `assessments`, `forms`, `orders`.

No independent UI state/domain truth was added.

## Shell changes

- Sticky product topbar with bounded content width and clearer brand/workspace/account-action grouping.
- Workspace picker remains the same select/ID but gets an explicit label and assistive hint.
- Login is a structured card with explicit input labels, autocomplete preserved, and mobile-safe field sizing.
- Dashboard heading separates greeting, workspace path and localized date.
- Module grid is now a clearer 4/2/1 responsive hierarchy rather than six narrow columns.
- Content panel remains flexible and intentionally generic for Worker 2 to render richer domain presentation.
- Footer is deliberately minimal and does not expose technical/runtime data.

## Accessibility

- Added skip-to-content link targeting a focusable main landmark.
- Retained semantic header/main/nav/section/footer structure.
- Added explicit form labels and a visually-hidden search label.
- `login-message` and `result-list` use polite live-region behavior for asynchronous feedback.
- Visible `:focus-visible` treatment uses a dedicated focus token and does not depend on color alone.
- Interactive controls have a 44px minimum target.
- Module icons are decorative and hidden from assistive tech while button text remains the accessible name.
- State primitives combine color with border/background and a marker, not color alone.
- `prefers-reduced-motion: reduce` collapses motion/animation to near-zero and removes hover/press translation.

No stale `aria-current`/`aria-pressed` attribute was hard-coded because frozen `app.js` does not update it as active views change; a static attribute would become incorrect after navigation. If the integration worker later owns JS presentation hooks, it can synchronize `aria-current` or `aria-pressed` with the existing `.active` state.

## Responsive decisions

- Minimum supported CSS viewport guard: 320px.
- Wide screens: four module columns.
- Up to 980px: two module columns.
- Up to 840px: login becomes one column; heading/content toolbar stack; topbar can wrap.
- Up to 520px: one module column, full-width workspace selector, stacked search controls and 16px form fields to prevent iOS focus zoom.
- Long Persian labels use `overflow-wrap`; grid/flex children use `min-width: 0`; body prevents accidental horizontal overflow.
- Layout uses logical properties where direction matters to preserve RTL behavior.

## Visual primitives for Worker 2

These classes are presentation-only and can be applied to canonical states returned by Worker 2/backend logic:

- state containers: `.ui-state`, `.ui-state-loading`, `.ui-state-empty`, `.ui-state-error`, `.ui-state-warning`, `.ui-state-success`, `.ui-state-info`
- indeterminate loading: `.loading-indicator` (no percentage/ETA)
- skeletons: `.skeleton`, `.skeleton-line`, `.skeleton-card`
- status: `.status-chip`, `.status-chip-success`, `.status-chip-warning`, `.status-chip-error`, `.status-chip-info`
- metadata: `.metadata-row`
- buttons: `.button`, `.button-primary`, `.button-secondary`, `.button-quiet`, `.button-danger`
- containers: `.surface-card`, `.list-stack`, `.card-grid`

The pre-existing `.empty-state` and `.result-card` classes remain styled for frozen `app.js` compatibility.

## Integration notes

1. Worker 2 should reuse these primitives but must derive displayed state from canonical API/domain output; CSS names do not define business truth.
2. Do not replace the preserved IDs or move `data-view` onto nested children unless `app.js` is intentionally updated during integration.
3. `app.js` currently prints raw object keys in generic result cards. That violates the final user-facing vocabulary goal, but Worker 01 is forbidden from editing JS; Worker 2/integration should replace generic field rendering with presentation adapters while preserving API contracts.
4. The local SVG sprite is same-origin and can be extended during integration if another worker adds a new presentation-only module; keep stroke style consistent.
5. If a canonical brand asset lands later, replace only `.brand-mark` content/asset reference and theme tokens; do not infer a new logo from this placeholder.
6. No central workflow/CI wiring was changed. The static check is intentionally standalone.

## Tests/checks

Standalone command:

```bash
python3 tests/ux/worker01_static_quality.py
```

It verifies:

- `lang="fa"` / `dir="rtl"`
- all frozen DOM IDs and `data-view` hooks
- legacy module abbreviation text removed
- local icon sprite present with all module symbols
- no remote font/icon/script dependency
- skip link, landmarks, explicit login labels, live regions, focus and reduced-motion rules
- 320px guard, touch-target token and mobile breakpoints
- required semantic design tokens
- Worker 2 state/button/card primitives
- absence of obvious inline FANOOS runtime variables, auth headers, secrets/tokens and chat IDs in shell HTML

Local pre-commit validation result for this branch content: `worker01 static quality: PASS`; PHP syntax validation of `index.php` also passed.

## Browser/live review remaining

Automated/static validation cannot replace a real rendering pass. Integration should still visually inspect, without deployment:

- Safari iOS at 320/375/430px widths
- Chrome Android at narrow width and form focus
- Chrome/Firefox/Safari desktop around 840/980px breakpoints and wide layout
- keyboard-only login → workspace selector → modules → search → logout order
- VoiceOver/NVDA announcement behavior for login status and result updates
- external SVG `<use>` rendering in the supported browser matrix
- high zoom (200–400%) and long Persian workspace/course names

Production/browser validation remains explicitly pending; this worker does not deploy.
