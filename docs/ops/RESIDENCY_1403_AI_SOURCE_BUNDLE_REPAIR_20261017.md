# Residency 1403 — audited AI source bundles for Q34 and Q119

This runbook covers the two accepted 1403 questions whose design location
requires two complementary pages in one official reference. Their evidence is
kept in the protected server research workspace; no question text, options, or
book excerpts belong in Git.

## Audited original-page pairs

| Question | Official edition | Chapter and physical PDF pages | PDF SHA-256 |
|---|---|---|---|
| Q34 | Torabinejad Endodontics 6e | Chapter 8: 148, 153 | `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0` |
| Q119 | Carranza Periodontology 13e | Chapter 5: 133; chapter 13: 537 | `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf` |

The primary pages have prior independent year-specific audits and decisions.
The complementary page evidence is SHA-pinned in the private research-only
report
`/srv/fanoos/shared/research/classification/reports/residency1403-ai-source-bundle-original-pdf-evidence-20261017-v4.json`.
The pinned supplementary research report is
`/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-remaining-findings-20261012/classification/reports/1403-four-original-research-plus-secondary-page-evidence-20261012.json`.

## Pre-merge read-only preflight (2026-10-17)

An isolated temporary code copy ran the new read-only plan builder and
rollback-preview against the live records, without applying writes. The plan
SHA-256 is `462565dcfcd9be6b430d1c8c82729d5bfb95abfe997ce644a59ad513facfcf48`;
the preview SHA-256 is
`9b08559a058f5a6083a9f4f717acc314294d2053ebcb1e39094c67e8397ae395`.
The preview accepted two bundles, reported `applied=false` and zero changes to
questions, choices, keys, reviews, attempts, or assessments, and read back the
intended primary/secondary pages: Q34 148/153 in chapter 8; Q119 133/537 in
chapters 5/13. An independent live query after rollback still reported
222/230 same-row chapter/PDF-page/SHA, one incomplete source row each for Q34
and Q119, and health `ok` on release `e9359323535446c779b6ba0ab66d41e38904ef9e`.

This is a pre-merge preflight only. Regenerate the private plan and preview
from the deployed `main` release after CI, merge, and updater deployment; never
apply the preflight plan.

## Guarded operation

Use `prepare_residency_1403_ai_source_bundle_plan.php` to create a protected
read-only plan. It accepts only Q34 and Q119, confirms the pinned original PDF
pages and full official 1403 scope, checks the existing AI source and canonical
question/choice/key, and writes no database rows.

`repair_residency_1403_ai_source_bundles.php` supports rollback preview and
apply. It requires the pinned research/evidence, exact PDF map and page-local
quotes, a unique unchanged unreviewed AI primary source, unchanged canonical
question/choices/final key, one official chapter node per page, a fresh full
verified backup, and a unique protected receipt. Apply updates only the
existing primary source row and inserts one secondary AI source row per
question. It must not edit questions, options, official answers, reviews,
attempts, or frozen assessments. Each apply must be followed by a live readback
of both source rows per question and an independent year census.

Do not use `repair_verified_human_source_page_bundle.php` for these questions;
that operator is restricted to human-origin citations and one chapter.

## Publication gate and checkpoint

These source corrections do not authorize exam publication. The 1403 residency
assessment remains at the previously published v5/250 while any clinical item
is incomplete. Before this bundle work, the live same-row chapter/PDF/SHA
census was 222/230 (English 20/20 exempt with valid keys); the remaining
scientific holds are Q61, Q62, Q63, Q68, Q69, and Q127. Q34 and Q119 remain
incomplete until both source rows per question are independently read back.

Record the final plan SHA, preview output, fresh verified backup ID and manifest
SHA, apply receipt SHA, readback SHA, exact live totals, release SHA and `/health`
result below after the protected operator is deployed and run. Do not report
completion before those records exist.
