# FANOOS UX Integration Report

## Baseline and provenance

- Repository: `ArianGhsm/FanoosLearn`
- Original parallel base: `fef86a4adcad99cb75327582750dd4cd2df88dea`
- Integration branch: `integration/ux-presentation-wave`
- Main was still exactly the original parallel base when integration started.

Workers consumed:

| Worker | PR | Exact head | Scope |
| --- | ---: | --- | --- |
| Web visual shell | #10 | `55f3fac976cb84ed3794efa010991b8ff403efa7` | shell, design tokens, accessibility |
| Web domain presentation | #11 | `2edc396c4d7720e5de036d0971d8ec7c682b6396` | domain renderers, localization, interaction states |
| Bot semantic presentation | #13 | `0ab8796b89f062108a4c927577382d599423bd32` | Persian semantic screens and formatting |
| Channel-native presentation | #12 | `84146eba26ba2637fb893f530d01eb6ef5dd8174` | Telegram Rich UI, Bale fallback, runtime feedback |

All four PR heads had successful CI before integration and their merge base was the shared parallel base. Their changed-file sets respected the planned ownership boundaries.

## Integration order

1. Worker 1 → integration branch
2. Worker 2 → integration branch
3. Worker 3 → integration branch
4. Worker 4 → integration branch
5. Integration-only harmonization commits

The worker PRs were retargeted to the dedicated integration branch rather than merged separately into `main`.

## Semantic conflicts resolved

### Web design-token mismatch

Worker 2 had been developed against the pre-wave shell variables (`--line`, `--muted`, `--ink`, `--card`, `--green`). Worker 1 replaced those with the canonical semantic token layer.

`domain-ux.css` now consumes Worker 1 tokens directly, including:

- `--color-border`
- `--color-border-strong`
- `--color-text`
- `--color-text-muted`
- `--color-surface`
- `--color-surface-raised`
- `--color-primary`
- `--color-success/error/warning` and soft counterparts
- shared radius, spacing, focus, typography and touch-target tokens

No legacy token aliases were added to `:root` solely for compatibility.

### Domain asset loading

The authoritative strategy remains Worker 2's deterministic local dynamic loader in `app.js`:

- `/assets/domain-ux.css` is injected once using `data-fanoos-domain-ux`.
- `/assets/domain-ux.js` is injected once and `boot()` awaits successful module load before binding/rendering.
- `index.php` does not also statically include these assets, preventing duplicate load.
- No remote dependency or CSP relaxation was introduced.

### Web active accessibility state

The JS navigation layer now synchronizes visual and accessibility state:

- selected module: `.active` + `aria-pressed="true"`
- all other modules: `aria-pressed="false"`
- search view clears module active/pressed state

This avoids stale static ARIA while preserving the existing module buttons and `data-view` contract.

### Worker 3 → Worker 4 semantic adapter

Worker 3's `ScreenPresentation` is the canonical platform-neutral semantic model. Worker 4 transports previously understood explicit `blocks` metadata.

A new presentation-only adapter, `packages/python/fanoos_bot/semantic_adapter.py`, converts either:

- existing explicit block metadata, or
- Worker 3 `ScreenPresentation` / equivalent mapping

into the bounded transport block vocabulary.

Mapping:

- `title` → heading
- `intro` → paragraph or severity quote for warning/success/error
- `facts` → semantic list of labeled facts
- `list_items` → list
- `sections` → section heading + paragraph/items
- `footer` → paragraph
- `rtl` → Telegram Rich RTL flag

The adapter contains no authorization, entitlement, payment, callback or domain state. `Screen.text` remains the complete functional fallback and `Screen.rows`, `edit`, and `protect_content` are unchanged.

### Notification semantic wiring

`NotificationPump` now builds notification presentation through Worker 3's `notification_detail_screen()` for the normal single-message path.

Preserved unchanged:

- backend claim/lease authority
- local sent-delivery dedupe
- delivery receipt idempotency key
- delivered/retry/failed outcomes
- retry behavior
- transport separation

If a notification must be chunked, fallback chunks remain plain `Screen` instances so the semantic model is not incorrectly split. No inbox/list business feature was added.

## Cross-channel presentation policy

Website, Telegram and Bale retain one semantic vocabulary for shared concepts:

- خانه
- برنامه
- نمرات
- اطلاعیه‌ها
- منابع
- تمرین و آزمون
- خرید و دسترسی
- فضای آموزشی
- حساب
- تنظیمات
- به‌روزرسانی

Website uses the vector icon system. Bots use the moderate semantic emoji vocabulary. Presentation differs by platform capability, but business meaning and backend authority do not.

Telegram remains Rich-first with plain fallback. Bale uses documented native/plain capabilities and keeps protected direct delivery fail-closed where no official forward-protection primitive exists.

## Tests integrated

Existing worker tests are retained. Integration adds:

- `tests/bots/test_ux_integration.py`
  - real `BotApplication.home()` semantic metadata → Telegram Rich RTL
  - same screen → readable Bale output
  - Rich disabled → exact plain fallback
  - callback preservation
  - error presentation without raw backend code
- `tests/ux/integration_web_ux_test.js`
  - no legacy domain CSS tokens
  - shared shell token consumption
  - one authoritative domain asset-loading path
  - active ARIA synchronization
  - required shell/domain view coverage
  - raw-object/unsafe HTML presentation guards

The deterministic bot runner now compiles `tests/ux` and explicitly runs Worker 3 UX tests. Worker 4's suite continues to run through the existing runtime transport test bridge.

CI now includes a dedicated `web-ux` job that runs:

- PHP syntax for `index.php`
- JS syntax for app/domain modules
- Worker 1 static quality check
- Worker 2 domain test
- integrated web UX guard

Existing PHP/static, repository safety, exact-release artifact, Python bot/worker and MySQL integration jobs remain intact.

## Business/API/schema boundary

No backend API contract, database schema, authorization rule, payment truth, entitlement rule, protected-delivery authority, notification receipt contract or deployment-control contract was changed by the integration-specific work.

Central CI was extended only so previously standalone UX checks are part of the repository integration gate.

## Runtime/live validation remaining

Repository tests cannot replace live rendering/provider validation. Remaining later-runtime checks include:

- iOS Safari / Android Chrome / desktop browser rendering and keyboard/screen-reader pass
- Telegram live Rich Message RTL/edit/fallback/protection smoke
- Telegram activity/draft behavior against the live Bot API
- Bale live send/edit/keyboard smoke
- Bale protected-content fail-closed verification
- production runtime/service configuration

These are `RUNTIME_VALIDATION_REQUIRED` / `LIVE_BROWSER_VALIDATION_REQUIRED`, not repository-side claims of failure.

## Integration status

The branch is ready for the final integration PR once its full CI is green. The final `UX_INTEGRATED_MAIN_SHA` is intentionally not recorded until the integration PR is merged into `main` and the resulting SHA is verified.
