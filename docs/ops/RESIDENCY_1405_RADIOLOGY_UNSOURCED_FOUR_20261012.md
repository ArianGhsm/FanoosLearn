# Residency 1405 — four unlinked radiology items: source-only research audit and protected publication gate

## Independent live state (read only)

Canonical residency 1405 round 1 contains 250 bank questions, 20 English-exempt, and 5 oral-radiology questions with **no bank_question_sources row**: 191, 192, 193, 194 and 207. Latest confirmed live before this isolated research batch: 99 source rows with chapter + `pdf_page` + PDF SHA populated (including previously applied Burket Q66); **131 textbook-based incompletes**.

Exact announced 1405 radiology reference is `white-pharoah-radiology@8e` and only its officially allowed chapters are eligible. The approved PDF on the live server has SHA-256 `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`, 1958 pages, a full SHA-pinned chapter map. Its chapter runs and the primary figure or table were matched to the *actual PDF page*, not the printed book page.

| Q | Official reference | Official chapter | True PDF page | Source support | Latest untouched final answer status |
|---:|---|---:|---:|---|---|
| 191 | White/Pharoah 8e | 1 | **60** | Fig. 1.18: x-ray spectrum after beam filtration | final |
| 192 | White/Pharoah 8e | 2 | **94** | Casarett cell radiosensitivity categorization, relevant to cell death mechanisms | final |
| 193 | White/Pharoah 8e | 3 | **120** | Table of effective doses for the referenced radiographic examinations | final |
| 194 | White/Pharoah 8e | 4 | **186** | Fig. 4.14: digital brightness, contrast and gamma adjustments | final |

The original published Persian stems, all four options, latest official answer and statuses for each were extracted directly from live database in **read-only** mode. Four source-free rows were counted before the study and no original question was edited. The protected case folder:

`/srv/fanoos/shared/research/classification/recovery-1405-radiology191-194-20261012/`

Contains original `bank-sittings/1405/radiologyfour-study.json`, exact chapter/PDF-page decisions `classification/decisions/1405-oral-radiology.json`, validated study `classification/sittings/1405-radiologyfour-validated.json` and independently checked provenance report `classification/reports/1405-radiologyfour-audit.json`.

`apply_classification.py --pdf-pages` confirmed **4 accepted, 0 rejected, 0 pending**. `audit_private_study_batches.py` independently confirmed **4 accepted, 0 pending**, `question_content_identical=true`, current approved reference and immutable original study/decisions hashes. Exact independently checked audit SHA-256:

`2abf5865abdb948b347d33abb4bcaa0302b23af82724fe3413c534b4fcbfc2a3`

These four new links must use **`import_verified_sources.php` / `SourceOnlyPublisher`** (no existing-source update, no full-sitting bank import). PR #370 adds exact-year protected private `--case-root` with mandatory `--audit-sha`, retaining normal coordinator-root behavior, approved edition/page/chapter rules, source-absence check, lock and published-question/choices/latest-key exact-match, rollback-only default, recent full verified backup and unique private live receipt.

## Publication procedure

1. Require PR #370 CI fully green and merged to canonical main; use approved canonical updater to deploy and independently verify health/release, rather than invoking an un-deployed script on the live DB.
2. Read back four source-free questions, official answers, options and original study SHA, and review figure/table evidence. Q191's answer choices refer to graphical labels; verify the published figure itself before final scientific closure.
3. After approved release and fresh full verified backup, invoke **without `--apply` first** (atomic rollback-only):
   `php scripts/references/import_verified_sources.php --workspace=<approved-workspace-uuid> --year=1405 --subject=oral-radiology --stem=radiologyfour --expected=4 --audit=/srv/fanoos/shared/research/classification/recovery-1405-radiology191-194-20261012/classification/reports/1405-radiologyfour-audit.json --audit-sha=2abf5865abdb948b347d33abb4bcaa0302b23af82724fe3413c534b4fcbfc2a3 --case-root=/srv/fanoos/shared/research/classification/recovery-1405-radiology191-194-20261012`.
4. Only if rollback preview reports exactly four safe source-only inserts and no question/answer changes, repeat with `--apply --backup=<verified-full-backup> --receipt=<new-private-file-under-case-reports>`. Never apply with stale backup, changed question/answer, unreviewed PDF mapping or existing source. Independently verify new source rows, exactly matching PDF-page indexes and SHA, frozen published-assessment integrity, site health and read-only aggregate count. Archive receipt and case.
5. **Q207 remains completely unsourced and scientifically unresolved**; no page invented. Check specific odontogenic lesion recurrence/follow-up against chapter scoped official reference, including correct choice, before issuing a source decision.

**Important:** At document creation this is *research and independently audited evidence only*. No source-only production insert has yet been confirmed for Q191–Q194 and they must not be counted in the 99 confirmed field-triple rows. Preserve all previous human reviews, published assessment versions and exam attempts.
