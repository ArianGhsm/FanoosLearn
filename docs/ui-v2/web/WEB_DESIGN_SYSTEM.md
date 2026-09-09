# FANOOS UI V2 — Web Design System

## Direction

FANOOS Web V2 is a calm academic product: Persian-first, RTL-native, high-information but low-noise, with the existing green/cream identity advanced rather than replaced. Visual hierarchy is driven by typography, spacing, dividers, lists and restrained surfaces rather than turning every datum into a floating card.

No external font or icon CDN is introduced. The project uses the local SVG sprite and system font stack. No new logo is invented.

## Core primitives

- App shell: sticky global header + desktop sidebar + responsive product content.
- Mobile shell: bottom navigation for high-frequency destinations + modal drawer for secondary destinations.
- Page context: eyebrow, H1, subtitle, local workspace date.
- Global workspace selector.
- Global search field + keyboard shortcut.
- Surface cards only for meaningful grouped content.
- Course card, timeline item, resource card, assessment card, grade row/table, announcement row/detail, form row/detail, order row.
- Filter bar, segmented control, tabs, chips/status badges.
- Loading skeleton, empty/error/info states, toast region.
- Button hierarchy: primary, secondary, quiet, danger.

## Typography and mixed direction

- Persian content inherits RTL.
- Technical/human course codes use `<bdi dir="ltr">` or isolated LTR presentation.
- Long tokens may wrap without forcing horizontal overflow.
- Persian numeric/date rendering comes from existing domain formatters rather than string reversal or manual shaping.

## Spacing and density

The shell preserves touch target `44px` minimum. Desktop content density increases through grouped lists/tables; mobile adapts tables into readable card-like rows. Rounded corners are restrained and surfaces do not float independently unless they represent a semantic group.

## Status semantics

Status is never color-only. Each badge includes readable Persian text. Payment and entitlement are intentionally distinct. Unknown provider/backend status maps to a human “وضعیت نامشخص” style rather than echoing raw codes.

## Async states

Every destination supports:

- initial skeleton
- empty state
- safe error state
- retry for safe reads
- partial failure on Home/Course composition
- mutation pending via disabled controls
- mutation failure via toast/state
- stale response suppression after workspace change

Progress percentages and ETAs are not invented.

## Accessibility

Implemented hooks include:

- `lang="fa"`, `dir="rtl"`
- semantic header/main/nav/aside/section landmarks
- skip link to `#main-content`
- logical H1/H2/H3 hierarchy
- `aria-current="page"` for active navigation
- visible `:focus-visible`
- drawer `role="dialog" aria-modal="true"`, Escape/backdrop close, focus return
- tab roles + `aria-selected`
- async `aria-live` and `aria-busy`
- form label/control associations
- status text independent of color
- `prefers-reduced-motion: reduce`
- decorative SVG `aria-hidden=true`

## Responsive contract

Target layout logic covers 320, 360, 390/430, 768, 1024, 1280+ and wider desktops using fluid widths and breakpoints including 840px and 520px. Below the desktop threshold the sidebar is removed from layout and bottom navigation becomes primary. Filters stack, grade tables adapt, safe-area inset is respected, and body overflow is guarded.

## Icon family

`/assets/web-shell/icons.svg` preserves the original symbols and adds local line icons for home, search, menu, account, management, clock and chevron. The family uses currentColor, consistent stroke geometry and no emoji navigation decoration.

## Security-related presentation rules

- API strings are assigned by `textContent`/safe DOM properties; no API-derived `innerHTML`.
- URLs are not created from arbitrary API strings in V2 surfaces.
- UUID/object/storage/provider identifiers are never product labels.
- Hidden navigation does not authorize; backend endpoints remain authoritative.
- Product state is never derived from visual badge state.
