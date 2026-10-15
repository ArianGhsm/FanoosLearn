# Residency 1405: guarded chapter and original-page correction

Status: **code proposal only**. This document does not claim a source write,
new strict count, production deployment, or approval of a private case. The
last independently documented production count is 181/250 same-row chapter,
physical PDF page and PDF SHA, with 20 English exemptions and 49 clinical
field-incomplete. The 20 Carranza cases have research audits but 0/20 live
page-bound source rows. See `RESIDENCY_1405_ALL_SUBJECTS_PAGE_RESEARCH_20261015.md`.

`scripts/references/repair_verified_source_chapter_pages.php` is a narrow
source-only operator for **one existing, unreviewed AI source in residency
1405** where the historical chapter and/or printed page is wrong and the
correct chapter, book page and physical PDF page have an independently
accepted one-question audit. It
retains the source UUID, exact edition, legacy `page`, original anchor,
question, choices, latest answer, review provenance and attempts. Only
`node_id`, `printed_page`, `pdf_page` and `pdf_sha256` may change. There is no
edition switch, new source insertion, answer repair or voided-answer bypass.

The plan is a private JSON file directly under
`/srv/fanoos/shared/research/classification/reports/`, mode 0600. It is
never committed. Its shape is:

```json
{
  "format": "fanoos.verified-source-chapter-page-correction/1",
  "exam_type": "residency",
  "year": 1405,
  "subject": "periodontics",
  "audit_path": "<private report path>",
  "audit_sha256": "<64 lowercase hex>",
  "entries": [{
    "number": 129,
    "source_id": "<source UUID>",
    "edition": "carranza-periodontology@14e",
    "expected_chapter": "79",
    "chapter": "78",
    "pdf_page": 1618,
    "pdf_sha256": "<current approved PDF SHA-256>",
    "printed_page": "<corroborated book page>",
    "expected_stored_printed_page": "<current stored value or null>",
    "expected_legacy_page": "<current legacy page or null>",
    "expected_anchor_sha256": "<SHA-256 of unchanged stored anchor_text>",
    "expected_stem_sha256": "<SHA-256 of unchanged stored stem>",
    "expected_choices_sha256": "<SHA-256 of PHP json_encode of the ordered PDO choice rows>",
    "expected_answer_sha256": "<SHA-256 of PHP json_encode of latest PDO status/choice_position row>",
    "evidence": "<short verbatim original-PDF-page passage>"
  }]
}
```

Generate the three question/choice/answer hashes from the live read-only
queries in the operator, with identical PDO column order and PHP JSON
encoding. An audit from `audit_private_study_batches.py` must accept exactly
this question, edition, corrected chapter, PDF page and SHA; its bytes are
pinned by `audit_sha256`. The audit may reside in that case's private
`recovery-1405-*/classification/reports/` directory. The protected original study must keep the live
stem, four choices and final/amended key unchanged. The operator also checks
the live source UUID and anchor digest, official year-specific chapter scope,
deployed SHA-bound chapter map, actual book printed-page label plus neighboring
labels, and the verbatim quote on the approved original PDF page.
If the historical chapter is already correct, `expected_chapter` and
`chapter` are equal, while the stored printed page must differ from the
newly corroborated `printed_page`.

After a CI-green merge and **permitted official updater deployment**, an
operator can run a rollback-only preview:

```sh
php scripts/references/repair_verified_source_chapter_pages.php \
  --workspace=<workspace UUID> --plan=<private plan path>
```

Only after a fresh, independently verified full backup and successful
preview, apply the exact same plan with `--apply --backup=<verified backup>
--receipt=<unused private JSON path>`. The updater service account is the
only account allowed to apply. The receipt records the old and new chapter
node IDs, printed label, physical page, PDF SHA, plan SHA and audit SHA.
Independently read back the exact source row, question/choices/answer and
frozen attempt state; compare pre/post immutable digests and verify `/health`
and the strict 1405 count before reporting a production completion.

The full Carranza map is merged in Git but was **not** on the active release
at the last checkpoint. The earlier official deployment request was declined
by the connected safety gate. This operator does not change that decision or
authorize an alternate deployment route. If the official updater still
cannot deploy, retain the 20 protected research audits without calling them
live completions.
