# Stage 8 — Staging Rehearsal Runbook

Status: **prepared; execution is one-time Codex runtime work**.

Staging must use the exact Stage 8 merged `main` SHA supplied as `STAGE8_RELEASE_CANDIDATE_SHA`. Never substitute the Stage 8 branch head, a dirty checkout, a manually assembled archive, or a newer `main` commit.

## Preconditions

The staging host/database/object storage and bot identities are Fanoos-only. Do not reuse Dentistry1402TUMS or VoiceMatnAIBot databases, bot tokens, runtime directories, queues, offsets, env files, object roots, service users, logs or backups. Legacy is accessed read-only only for the approved snapshot export.

All secret values are injected from the runtime secret boundary. Commands below use variable names/placeholders only.

## Exact rehearsal sequence

| # | Action | Required evidence / stop condition |
| ---: | --- | --- |
| 1 | Checkout exact release candidate | `origin` resolves exactly to `ArianGhsm/FanoosLearn`; `HEAD=$STAGE8_RELEASE_CANDIDATE_SHA`; commit belongs to `main` |
| 2 | Clean-tree verification | `git status --porcelain` empty; no untracked runtime data inside release |
| 3 | Runtime dependency/bootstrap | supported PHP/PDO/MySQL + Python; Git; qpdf/pdfinfo/pdftoppm/Pillow/font where worker enabled; adequate disk/memory |
| 4 | Install NEW Fanoos secrets | independent DB/service/channel/storage/payment/updater secrets outside Git; no legacy secret reuse |
| 5 | Provision staging DB/storage | empty/private Fanoos DB and non-public object root; least privilege |
| 6 | Apply migrations/seeds | canonical migration checksums; no manual ledger edits |
| 7 | Produce approved legacy/sanitized snapshot | read-only immutable source export, SHA-256, redacted inventory; real production snapshot only when authorized |
| 8 | Validate/dry-run/apply migration | validation clean; dry-run no mutation; apply explicit; content bytes verified before object metadata |
| 9 | Reconcile | machine report PASS + manual domain samples |
| 10 | Full deterministic suite | same commands/contracts as CI against disposable/staging-safe targets; no test against production DB |
| 11 | Web smoke | HTTPS liveness/readiness, login/account, workspace, schedule/grades/resources/search, RTL/mobile/basic accessibility |
| 12 | Telegram live smoke | `getMe`, link/workspace/read, callback, long Persian, notification receipt, safe order projection |
| 13 | Bale live smoke | provider identity, link/workspace/read, long Persian, capability-aware fallback |
| 14 | Protected-media fixture | safe synthetic PDF source → worker → personalized derivative → checksum → authorized Telegram send; Bale fail-closed if protection unavailable |
| 15 | Backup | SQL + objects + manifest outside release |
| 16 | Restore rehearsal | independent verify then restore to isolated target; schema/tenant/health/reconciliation pass |
| 17 | Updater dry-run/control-plane rehearsal | operator authorized; normal user/representative denied; candidate resolved from canonical main; no arbitrary ref/input |
| 18 | Atomic activation | immutable release + current pointer only after prior gates |
| 19 | Health/readiness | platform DB/storage + configured bot/worker fixed hooks |
| 20 | Restart recovery | restart web/bot/worker/updater timer around safe queued/receipt state; no duplicate business action |
| 21 | Rollback rehearsal | application pointer rollback to known compatible release; no automatic SQL reverse |
| 22 | Evidence capture | exact SHA, timestamps, versions, counts/checksums, safe statuses, redacted logs, failures/remaining risks |

## Canonical source checks

```bash
git fetch origin --prune
git remote get-url origin
git rev-parse HEAD
git status --porcelain
git merge-base --is-ancestor "$STAGE8_RELEASE_CANDIDATE_SHA" origin/main
```

The first URL must resolve to the Fanoos repository and the second output must equal the exact supplied SHA. If `origin/main` moved after the release candidate, do not silently deploy the newer commit; continue testing the supplied SHA or return to release review.

Build the exact source artifact:

```bash
php scripts/db/check.php
php scripts/ci/check-secrets.php
php scripts/ci/check-text.php
php scripts/ci/check-stage8.php
php scripts/ops/build-release.php "$STAGE8_RELEASE_CANDIDATE_SHA"
```

## Database bootstrap

After staging DB credentials are injected and the database is confirmed non-production:

```bash
php scripts/db/migrate.php
php scripts/db/seed.php
php scripts/db/check.php
```

For a disposable test DB whose name ends `_test`, run the full PHP suite using the existing double guard. GitHub CI remains the authoritative deterministic release gate; staging tests confirm runtime compatibility rather than replacing CI.

## Legacy snapshot rehearsal

For the first rehearsal, a sanitized bundle may be used to prove mechanics. For the real staging migration, Codex must read only the actual legacy canonical storage, freeze/export to a private path, compute the source snapshot SHA-256 **before transformation**, then generate the version-1 normalized bundle. No real snapshot or transformed bundle is copied into Git.

```bash
php scripts/import/legacy-bundle.php --bundle="$PRIVATE_BUNDLE" --validate
php scripts/import/legacy-bundle.php --bundle="$PRIVATE_BUNDLE" --dry-run
FANOOS_MIGRATION_APPLY_CONFIRMED=1 \
  php scripts/import/legacy-bundle.php --bundle="$PRIVATE_BUNDLE" --apply
php scripts/import/legacy-reconcile.php --batch="$BATCH_ID"
```

For content files, private copy/checksum verification precedes any `content_object` row with `status=verified`. Structured content should use the canonical Content import path when its contract fits. Bot account links are relinked by default and are not inserted by the generic bundle.

## Web/browser smoke

Use a non-privileged test student plus a scoped representative/operator test identity. Check at minimum desktop Chrome/Firefox or equivalent, mobile-width browser, and one real iOS Safari/Android Chrome path if available. Verify Persian/RTL, focus/keyboard basics, workspace changes, empty/error/loading states, no raw API dump, truthful payment/entitlement wording and no cross-workspace stale content.

## Telegram/Bale smoke

Use dedicated staging bot tokens. Telegram: account link, workspace selection, schedule/grade/resource reads, notification send/receipt, replayed callback, protected synthetic document, owner Update Server deny/allow paths. Bale: equivalent canonical reads and long Persian; confirm unsupported forward-protected delivery fails closed. Restart each process and confirm local offset/receipt state does not create duplicate canonical business facts.

## Backup / restore / updater

Run the tracked backup script using staging-private config, independently verify the resulting backup, and run the restore-test contract against an isolated target. Then configure the updater service/timer from tracked templates and execute one supervised request through its normal durable state machine. Inject a safe pre-activation failure and a post-activation health failure on staging to prove stop/rollback behavior.

## Rehearsal acceptance

Staging is accepted only if all deterministic CI gates are already green on the exact SHA, real runtime dependencies are present, migration reconciliation passes, SQL/object backup and isolated restore pass, browser/channel/protected-media smokes pass, updater candidate/authorization/backup/test/migration/health gates work, restart does not replay business actions, and application rollback is observed without pretending to reverse SQL.

Any tenant leakage, financial inconsistency, object checksum mismatch, provider-protection downgrade that sends protected bytes, backup/restore failure, arbitrary updater input, or unresolved Critical/High defect sets staging `BLOCKED`.

`RUNTIME_VALIDATION_REQUIRED=true`
