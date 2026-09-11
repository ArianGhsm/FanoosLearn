# FANOOS Website Stage 4 — shell and workspace hand-off

Status: implemented and validated locally. This stage is presentation and client orchestration only; it does not change authentication, CSRF, tenant authorization, payment, entitlement or protected delivery truth.

## Delivered surface

The existing V3 shell workstream was reused and hardened rather than duplicated:

- `shell/index.js` is the single authenticated-shell entry and keeps the canonical session bridge.
- `shell/router.js` owns route matching and navigation; domain modules do not create a second router.
- `shell/module.js` composes the RTL desktop rail/topbar and the mobile bottom navigation/More sheet.
- `shell/ui.js` owns safe text DOM construction, local SVG icons and focus-contained sheets.
- `shell/shell.css` owns responsive shell presentation, safe-area padding, focus-visible styling and reduced motion.

The primary route vocabulary is `خانه، درس‌ها، برنامه، منابع، آزمون‌ها، نمرات، اطلاعیه‌ها` with `بیشتر` exposing forms, purchase/access, account and capability-gated management. Account remains available without a workspace.

## Onboarding states

The shell now distinguishes four client presentation states without inventing server data:

1. `uninitialized`: the authenticated session has no usable public user projection; only a bounded refresh/logout recovery surface is shown.
2. `zero-memberships`: the account is known but has no active workspace membership; the full navigation remains usable and each workspace-bound route opens the same truthful onboarding/help state.
3. `memberships-no-selection`: active memberships exist but the canonical session has no selected workspace; the user must explicitly choose one.
4. `active-workspace`: the selected workspace is server-confirmed and domain modules receive its ID, timezone and epoch.

The client never promotes the first membership implicitly. Workspace switching clears the old outlet, emits a `start` invalidation event before the mutation, performs the canonical select request, revalidates `/account`, refreshes capability projection and only then remounts the current route. Epoch checks and abortable route contexts prevent an older workspace response from painting into the new workspace.

## Account and channel projection

Account includes the display profile, active membership list, selected workspace, safe logout and a factual read-only information section. A channel-link section is rendered only when a future/public account projection supplies `channel_links`, `messaging_links` or `channels`; it maps known Telegram/Bale labels and bounded statuses without exposing IDs or raw provider errors. The current backend account projection does not expose that field, so no connection state is claimed today. Editable profile/password/recovery controls remain out of scope until a public canonical contract exists.

## Accessibility and responsive contract

Landmarks, skip links, `aria-current`, visible labels, live status regions, focus trapping/restoration for sheets, keyboard Escape/Tab handling, 44px controls, RTL logical properties, reduced-motion behavior and mobile safe-area padding remain owned by the shell. Desktop uses the light sidebar/compact header; screens at and below the compact breakpoint use the five-item bottom navigation plus a More sheet. Onboarding and account pages remain usable at 320px and above.

## Validation and next hand-off

Stage-specific static contracts cover the route registry, one navigation authority, all onboarding states, full zero-membership navigation, account/logout/channel projection hygiene, workspace epoch invalidation, responsive navigation and accessibility hooks. Existing V3 integration, UX, DOM-safety, PHP/JS syntax and security/text guards remain required.

The next stage can build authenticated domain screens against the existing route/context contract. It must keep workspace ID and server capability projections authoritative, honor `fanoos:v3:workspace-switch`, and avoid adding another shell, session store or route listener.
