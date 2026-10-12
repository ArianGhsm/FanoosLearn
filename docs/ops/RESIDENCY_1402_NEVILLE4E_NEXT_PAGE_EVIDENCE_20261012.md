# Residency 1402 — Neville 4e additional original-page evidence, 2026-10-12

**Verified source-row correction checkpoint.** This record began as original-book research and was subsequently updated with guarded, independently read-back production source-page repairs for the 1402 dental residency. Source rows and the student assessment are separate publication steps.

At the start of this pass the production 1402 sitting remained **250 questions; 79** with same-row chapter + verified PDF-page/hash fields, 145 existing chaptered-but-unbound, 6 source-free radiology and 20 final-key English officially exempt. Published student assessment was version **6**. Current approved Neville Oral and Maxillofacial Pathology **4e** has 878 PDF pages, pinned SHA-256 `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`. Each proposed page below was **directly read from this exact server PDF** (no nearest-edition substitution), and the recorded chapter occurs in the official 1402 Neville4e scope list. A printed-page/header screen was also performed, but full neighboring-page confirmation and the transactional dry-run remain required before acceptance.

| Q | Chapter | Original PDF page | Printed page | Short identifying fact checked on page |
|---|---:|---:|---:|---|
| 63 | 3 | 141 | 134 | Condensing osteitis common in children and young adults |
| 67 | 10 | 367 | 360 | Speckled leukoplakia / erythroplakia and severe dysplasia |
| 69 | 11 | 439 | 432 | Anticholinergics decrease saliva production |
| 71 | 12 | 494 | 487 | Peripheral ossifying fibroma with mineralized product and fibrinopurulent surface |
| 72 | 12 | 524 | 517 | Well-differentiated liposarcoma is the most common oral variant |
| 74 | 14 | 601 | 594 | Fibrous dysplasia generally lacks osteoblastic rimming |
| 75 | 14 | 627 | 620 | Myxoid chondrosarcoma classically a soft-tissue tumor |
| 76 | 15 | 656 | 649 | Calcifying odontogenic cyst lining with ameloblastoma-like cells |
| 78 | 16 | 735 | 728 | Erythema migrans epithelial Munro abscesses |
| 79 | 16 | 725 | 718 | Mucous membrane pemphigoid with desquamative gingivitis |
| 80 | 17 | 792 | 785 | Addison disease, diffuse gingival pigmentation |

Private SHA-pinned guarded source-only update *candidate* plans (mode 0600; never import as an exam):
`/srv/fanoos/shared/research/classification/reports/residency-1402-neville4e-next-1-page-plan-20261012.json`
(Q63/67/69/71/72/74) and
`/srv/fanoos/shared/research/classification/reports/residency-1402-neville4e-next-2-page-plan-20261012.json`
(Q75/76/78/79/80). Plans contain current pre-change source IDs, hashes of the unchanged historical anchor, original edition and chapter, PDF SHA, printed labels and short original-page evidence. They must be revalidated if the DB changes. The versioned `repair_verified_source_pages.php` is limited to exact source-only pdf_page/pdf_sha256 updates, with rollback preview, official scope and direct original-page corroboration, and a **fresh verified full production backup** for apply.

**Not accepted in this batch:** Q61 and Q62 are in chapters excluded from structured official 1402 Neville scope. Q68 (alveolar mucosa versus gingival SCC resemblance to pyogenic granuloma) and Q77 (absence of a radiopaque component for ameloblastic fibroma) need more discriminating original text/alternatives before repair. Q70 has no old printed-page label; a direct, definitive Neville4e passage supporting its myoepithelial-cell exception and its exact PDF page remains to be established. Those cases are pending, not imputed.

**Concurrency warning:** multiple other exam years were running independent source-page work. A 1399 repair reported a 1205 lock wait timeout while source-page previews were holding row locks and repeatedly hashing large PDFs. Do **not** start concurrent source repair transactions or force through a timed-out preview. Wait for the specific prior run's verified result, then prefer short source-only batches, keep full backups/receipts, independently check unchanged question/choice/key fields and published assessment. Only count an actual applied/read-back result.

Previous 1402 successfully applied records and the v5→v6 243-question immutable-content audit are documented in `docs/ops/RESIDENCY_1402_ORIGINAL_PDF_PAGE_REPAIRS_20261011.md` (PR #316). The dated production outcomes below supersede the initial candidate-only research status for those explicitly applied rows.

## Verified live source-only outcomes (12 October 2026 UTC)

After an oversized six-row preview failed to return a confirmed result during
simultaneous server workloads, read-only production verification showed all 11
target pages remained NULL. Research was preserved and the operator was
resumed with four smaller guarded batches; **each batch independently
succeeded in rollback-only live preview and then applied with a fresh
verified production full backup**. Current deployed operator:
`scripts/references/repair_verified_source_pages.php` in the approved
server release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`.
The exact-approved Neville 4e PDF SHA is pinned above. The operator
corroborated every original PDF-page quote and printed pagination including
neighboring pages, checked year-specific official chapter scope, verified
unreviewed source identities/unchanged old anchors, and only updated
`bank_question_sources.pdf_page` and `pdf_sha256`.

| Guarded applied batch | Questions | PDF pages | Private SHA-256 receipt |
|---|---|---|---|
| Q63 alone | 63 | 141 | `4abc78fe512fe7983a4db4c65502b03d695c20ca3037dbb4946c3d514884ad2e` |
| nextbatch-1 | 67, 69, 71 | 367, 439, 494 | `8724b494d57ee79ec98dadce475f96ec97e077b9fa4ae21693b3d126ce0653c5` |
| nextbatch-2 | 72, 74, 75 | 524, 601, 627 | `c76713919553c285a54e1620ed90acf1d79561287959252886e06df809695b9b` |
| nextbatch-3 | 76, 78, 79, 80 | 656, 735, 725, 792 | `d140266b8901bbbbb0f1f7bba8bf7897cb526570be48a3e448a07f6bd15fb05a` |

For each applied batch, private receipt path:
`/srv/fanoos/shared/research/classification/reports/residency-1402-neville4e-`
followed by `q63-page-applied-20261012.json` or
`nextbatch-{1,2,3}-applied-20261012.json`. Receipt JSON confirms
questions/choices/official answers changed **zero**, and lists exactly the
source IDs/pages/file hash that were read back against live MySQL.

The common pre-apply full backup is
`/var/backups/fanoos/20261012T035944Z-71f4b256`, independently verified
by `scripts/ops/verify-backup.php` as **114 files**, including database.sql;
created and applied inside the current four-hour guard. Protected plan files
are all mode 0600 under `/srv/fanoos/shared/research/classification/reports`
and source metadata did not change between preflight and apply.

**Independent live after-apply count: 90/250** with same-row chapter +
positive PDF page + PDF SHA-256, up from **79/250** at this pass's start.
The remaining raw strict field-incomplete set is **160/250**, consisting
of 140 non-English book-citation candidates and 20 officially exempt English.
This field count does not independently certify validity of pre-existing
pages: the separate 29 out-of-structured-official-scope records documented
in the previous 1402 audit remain an unresolved review queue (overlapping
the backlog, not added to it).

The student-facing frozen assessment was still version 6 when the source
changes were read back; a separate reviewed `audit_published_assessment.php`
preflight confirmed its **243/243** non-explanation question definitions
were stable. Do not claim version 7 has published until a separate full
backup, official publisher and post-version immutable-field audit succeed.
