# Foundational website redesign

Status: research and planning, not deployed. Branch `design/foundational-redesign`, based on main 85a5d51. Scope is the website only; preserve the bank, APIs, attempts, payment and bots.

## Directions considered

1. **Study atlas:** a reference-aware navigation system, with clear hierarchy between exam, year, subject, source and question. Dense content uses aligned rows and typography; the public page introduces the same relationship as an editorial diagram. Risk: excessive density on mobile.
2. **Study desk:** an action-first workspace centred on the next practice, review and study plan, with a separate navigable library. Risk: without real data, an empty dashboard must still be useful.
3. **Exam paper:** a visual language derived from the structure of a printed examination and reference index, including question numbers and source annotations. Risk: a literal paper metaphor can obstruct the runner and should remain a compositional reference rather than decoration.

Working direction: combine the first direction's information hierarchy with the second's task-first home; use the third only for the public narrative. Preserve brand and reading accessibility. This is an implementation decision inferred from the repository, not a claim of user approval of a particular visual treatment.

## Delivery plan

1. Audit every public and signed-in journey, current screenshots, interactive and data states, RTL, theme and device behavior. Record real content and existing routes.
2. Define visual principles and tokens with type, color, spacing, layouts, focus, contrast and motion; create before/after screen compositions for public and product surfaces.
3. Rebuild landing and authentication; redesign the workspace shell and home; then bank, reference browsing and exam discovery.
4. Redesign the runner and its answer, explanation, timer, error, review and accessibility states without changing assessment semantics; follow with study, account and commerce surfaces.
5. Validate with browser interaction tests and visual inspection in mobile/desktop and both themes, keyboard and reduced motion, plus existing automated suites. Exercise state-changing flows only in isolated test data.
6. Review, green CI, merge and use the updater with backup; verify live SHA and health. The systemd updater's GitHub proxy previously failed TLS; the operator-run updater with the session proxy succeeded, so resolve or explicitly account for this before deployment.

## Research performed

Taste Skill direction generator compared loud poster, printed artifact and typography-only directions; its app-screen guidance calls for task-oriented, honest data and restrained chrome. UI UX Pro Max's education query returned a child-oriented playful style and Latin fonts, which are unsuitable for this Persian professional audience; its accessible focus, responsive and reduced-motion guidance remains applicable. Impeccable context and direction seed were run. The user's no-question instruction overrides the skill's interview and choice gates; `PRODUCT.md` explicitly labels inferred facts. Node 18 cannot run Taste's browser inspector requiring Node 22. Playwright previously could not be installed because the proxy timed out; resolve browser tooling before release. No claim is made that remaining resources or workflows are fully applied.
