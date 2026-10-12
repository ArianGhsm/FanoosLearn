# Residency 1405 — exact approved reference chapter and PDF-page continuation (2026-10-12)

## Read-before-write production baseline and safeguards

The current FANOOS live site was checked at release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e` and public `/health status=ok`; the deployed release includes guarded `repair_existing_source_pdf_page.php --printed-offset` from merged PR #306. During today's production readback, the 1405 residency round-1 bank had **93** reference rows bearing `node_id`, `pdf_page` and `pdf_sha256` on the same source row, out of **250** questions, with **20 English questions exempt** and **137 remaining textbook-based incomplete**. This is a field-triple count, not proof that all existing historical chapter choices are correct. In particular, old out-of-1405-syllabus chapter holds Q44, Q129, Q130, Q217, Q218 still require scientific review.

No question stems, options, official answers, human review, frozen assessment definitions, exam attempts or history were changed in this continuation. The only authorized production operation used was the guarded addition of missing `bank_question_sources.pdf_page` and `pdf_sha256` to a **preexisting unreviewed AI source**, with its edition, chapter, old legacy printed page and evidence unchanged.

An official full backup at `/var/backups/fanoos/20261012T035946Z-27488df0` was created by `fanoosupd` and independently verified by `scripts/ops/verify-backup.php`: **114 files verified**. Backup policy excludes separately approved private reference-PDF objects; the current reference PDFs themselves are individually verified by SHA-256.

## Evidence and original question/option/key preservation

For each six tested candidates, the actual approved server reference PDF was SHA checked; the given chapter lies within the explicit 1405 official subject-specific syllabus; the current chapter map assigned the candidate PDF index to that chapter; the *original legacy printed page* was corroborated by the exact PDF page and at least **two adjacent printed page labels**; original source evidence matched the candidate PDF text in the initial scientific screen; and one fresh single-question **read-only study** was assembled from the current live stem, four choices and latest final official answer. Each one passed `apply_classification.py --pdf-pages` **1 accepted/0 rejected**, followed by a distinct `audit_private_study_batches.py` provenance check **1 accepted/0 pending**.

The protected case root for each Q is `/srv/fanoos/shared/research/classification/recovery-1405-<stem>-20261012/`. It contains `bank-sittings/1405/<stem>-study.json`, `classification/decisions/1405-<subject>.json`, `classification/sittings/1405-<stem>-validated.json`, and `classification/reports/1405-<stem>-audit.json`. These files are research-only; **never import a one-question study into an entire sitting**.

| Q | Subject | Official reference | Chapter | Original printed page | Exact current PDF page | Audit SHA-256 | Production status |
|---:|---|---|---:|---:|---:|---|---|
| 142 | oral-surgery | `hupp-oral-surgery@7e` | 26 | 563 | **577** | `e92d1013ed5be8d27018c3192e73c6c7b2acf3c0c7a178dac40fab2654c2f1a9` | applied |
| 144 | oral-surgery | `hupp-oral-surgery@7e` | 25 | 539 | **553** | `56a1d8e9bb8cac5ea38d4424a2e6414c7ce2d129af72691a8adc9c687c3c0822` | applied |
| 92 | prosthodontics | `zarb-edentulous@13e` | 1 | 17 | **31** | `2b61ca771bd72e61e3be7362747a9bd5a04e962f86bbffb7cca6da914b616385` | applied |
| 96 | prosthodontics | `zarb-edentulous@13e` | 8 | 174 | **188** | `b138e5913ccf1eb17be2cf7f44b21446b6bf586186c92c06f939790ec103f0d5` | applied |
| 66 | oral-medicine | `burket-oral-medicine@13e` | 6 | 181 | **201** | `4d2b5d79650418baf07dd74127896e67ae975098a134dcdf971cd48d9e3253d8` | primary + independent audits and rollback preview passed; **no confirmed apply** |
| 143 | oral-surgery | `hupp-oral-surgery@7e` | 28 | 623 | **637** | `ba49d258c222484ea197e74ed52732ed395e23abdd70878904da9e1dcbd88927` | **HOLD**: stricter guarded live original-anchor proof failed; no apply |

The current approved reference-PDF SHA-256 values for these exact editions are:

- Hupp seventh edition: `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`
- Zarb thirteenth edition: `bcf92916cd0a01915695d1cb5aad8c51e7f2dac5909477c4df4454d229156a3d`
- Burket thirteenth edition: `0ca841beb1820e8e0a33c96b5ce37cc36c94d6adce53fbf172b27312e0fd5252`

## Production receipts and live verification

All four applied rows passed an **independent rollback-only production preview** (`applied=false`), then completed individually with `applied=true` and `source_rows_updated=1`, while `question_rows_updated=0`, `choices_updated=0`, `answers_updated=0`, `source_chapter_updated=0`, and `human_reviews_updated=0`. The exact verified complete backup `20261012T035946Z-27488df0` was required for each source-only apply. Private immutable unique receipts exist under their respective cases at `classification/reports/1405-<stem>-live-repair-20261012.json` for **surgery142**, **surgery144**, **prostho92**, and **prostho96**. DO NOT claim an existing receipt for medicine66 or surgery143.

After these four applies, a fresh independent direct production MySQL query restricted to the exact `residency:1405:round1` sitting returned **97 same-source-row records with chapter + PDF page + current approved PDF SHA**. Accordingly, **133 textbook-based questions remain incomplete** after subtracting only 20 English-exempt, assuming the current sitting retains its verified 250 total. Presence of those three fields is not independently sufficient to prove correct official-year chapter validity on legacy records.

The oral-medicine Q66 guarded rollback-only preview initially encountered `SQLSTATE 1205 Lock wait timeout exceeded` while other background operations overlapped; after they cleared, a separate preview returned `applied=false` with one eligible source and all protected-domain mutations zero. Subsequent attempt to apply was blocked by the operation/tool's access safety checks before any confirmed successful apply; **hold Q66 pending an authorized actual operator result and postreadback**. Never disable a source guard or bypass verification to obtain a completion counter.

Q143 failed the independent guarded PHP live original-anchor/page-text check, despite the earlier primary research-stage evidence match; its `--printed-offset` preview exited 1. **This is a hard evidence hold, not an excuse to rewrite an original source anchor or force a PDF page.**

## Additional research and unresolved backlog

Read-only samples of the exact approved server PDFs found consistent printed-to-PDF offsets for:

- Hupp 7e **+14** (three independent sampled printed labels).
- Malamed 7e **+17**, but zero out of four remaining oral-surgery legacy source anchors passed exact corresponding-page text evidence in the scientific screen.
- Burket 13e **+20**; only Q66 of twelve source-incomplete records passed exact old original-anchor, chapter and adjacent page-label evidence.
- McCracken RPD 13e **+13** and Zarb 13e **+14** on three sampled labels. Unlike Zarb, the existing McCracken chapter-run map is **not currently PDF-SHA-pinned**; independently reconcile and version the exact current source mapping before source write.
- Craig 14e **+11**, Torabinejad 6e **+7**, Neville 5e **+10** were previously studied; only exact evidence-supported individual rows were applied in earlier checkpoints.
- McDonald/Avery 11e pediatric printed-to-PDF difference is **not constant** across sampled pages (+11, +8, +3); chapter and PDF page require actual per-question evidence, never a global offset.
- Little/Falace 10e printed-to-PDF offset also varies (+15, +32, +38 in sampled spots); requires per-question exact page verification.
- For Carranza 14e, many legacy `printed_page` values are chapter numbers or otherwise tiny placeholders rather than verified book-printed page numbers; do not infer page from them. Its chapter-run map also lacks a current PDF-SHA pin.
- Oral-pathology Q44, periodontics Q129/Q130, and materials Q217/Q218 remain separately flagged outside official 1405 source chapter scope.

**Next actions**: (1) Verify Q66 existing source state and run an independently authorized fresh dry-run/one-source apply, only if original and official source evidence remain unchanged, then independently verify live row and receipt. (2) Research other pinned official references without guessing offsets. (3) For all new operations maintain a fresh complete verified backup and original question/key/choice and human-review invariants. (4) Check published assessment content/version, exact release health, green CI for documentation PR and merge. (5) Never assert whole-sitting classification finished until all 1405 textbook questions have both official chapter and exact verified PDF page and all final publication checks pass.
