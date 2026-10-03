# FANOOS Website V3 — Worker Provenance

Design Lock ID: `FANOOS-UX-2026.09-R1`

Original parallel base: `9e72ef32b331ad41626229c28ed24dcc43a49292`

Integration branch: `rebuild-v3/integration-web`

All worker heads were read twice during integration and remained stable. Every worker descends from the original parallel base, reports source-complete status in its handoff, and changed only its owned V3 source tree plus its own handoff path. No worker server/runtime mutation, production data mutation, secret artifact, or canonical backend authority change was found.

| Worker | Final head | Integrated source | Handoff | Integration modifications |
| --- | --- | --- | --- | --- |
| `rebuild-v3/web-01-foundation` | `300bf0551208c7ad7dee72fb64a6d114cfc2788e` | `apps/platform/public/assets/ui-v3/foundation/**` | `docs/ui-v3/workstreams/web-01-foundation/WORKSTREAM_HANDOFF.md` | Preserved worker files; foundation runtime contract and primitives are canonical shared V3 layer. |
| `rebuild-v3/web-02-shell` | `cccfad0edfabf650555ec0a09da2f435ebd68ea3` | `apps/platform/public/assets/ui-v3/shell/**` | `docs/ui-v3/workstreams/web-02-shell/WORKSTREAM_HANDOFF.md` | Preserved worker files; integration supplies one route-mount adapter and adds the personal-notification route through Shell's supported integration route seam. |
| `rebuild-v3/web-03-home` | `532dd0d5e9fcba5af6a6b64234070a07430cec27` | `apps/platform/public/assets/ui-v3/home/**` | `docs/ui-v3/workstreams/web-03-home/WORKSTREAM_HANDOFF.md` | Preserved renderer; `app/home-module.js` adapts the independent global renderer to the canonical module/runtime contract. |
| `rebuild-v3/web-04-courses` | `f5d9864719ea6591c8c6d5696fa79ba9bdef7250` | `apps/platform/public/assets/ui-v3/courses/**` | `docs/ui-v3/workstreams/web-04-courses/WORKSTREAM_HANDOFF.md` | Preserved worker files; integration fills schedule/resources/assessment/grade slots without creating a second domain store. |
| `rebuild-v3/web-05-schedule` | `b085a90098f7f0272de4225753cd5fa415052b51` | `apps/platform/public/assets/ui-v3/schedule/**` | `docs/ui-v3/workstreams/web-05-schedule/WORKSTREAM_HANDOFF.md` | Preserved worker files; bootstrap preloads `schedule.css` with the worker's duplicate-load marker and adapts course-slot context. |
| `rebuild-v3/web-06-learning` | `a2c4c01cfc87fba30ab57f1f9e6d9db49b23f94e` | `apps/platform/public/assets/ui-v3/learning/**` | `docs/ui-v3/workstreams/web-06-learning/WORKSTREAM_HANDOFF.md` | Preserved worker files; canonical route is `/resources`; `/learning` is compatibility-normalized; secure delivery/binary save adapter is integration-owned. |
| `rebuild-v3/web-07-progress` | `94c720740a2a37b1edafce74ea2106fbcc698781` | `apps/platform/public/assets/ui-v3/progress/**` | `docs/ui-v3/workstreams/web-07-progress/WORKSTREAM_HANDOFF.md` | Preserved worker files; grade slot uses exported seam; assessment course-slot mounts web-07 then applies its own canonical course filter before reveal. |
| `rebuild-v3/web-08-operations` | `6d18a0680b5794e56b2191f8ce3f5fcc1306c1da` | `apps/platform/public/assets/ui-v3/operations/**` | `docs/ui-v3/workstreams/web-08-operations/WORKSTREAM_HANDOFF.md` | Preserved worker files; operations is registered for announcements, notification-gap, forms, orders and capability-gated management. |

## Integration-only source

Central source added or changed only in the integration branch:

- `apps/platform/public/index.php`
- `apps/platform/public/assets/ui-v3/app/bootstrap.js`
- `apps/platform/public/assets/ui-v3/app/home-module.js`
- `apps/platform/public/assets/ui-v3/app/integration.css`
- deterministic V3 tests and retained V2 fixture/test targeting
- minimal CI discovery change
- integration documentation under `docs/ui-v3/integration/web/**`

No canonical backend, OpenAPI contract, database schema, deployment updater, bot source, Telegram provider, Bale provider, server configuration or runtime environment file was modified by Website V3 integration.
