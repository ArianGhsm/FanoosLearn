# Residency 1402 — Neville 4e additional original-page evidence, 2026-10-12

**Evidence-only checkpoint, NOT a publication receipt.** This document records pending original-book research for the 1402 dental residency. Do not treat these questions as newly complete unless an independently verified live DB read-back of the same source row confirms both pdf_page and pdf_sha256, followed by a controlled assessment publication.

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

Previous 1402 successfully applied records and the v5→v6 243-question immutable-content audit are documented in `docs/ops/RESIDENCY_1402_ORIGINAL_PDF_PAGE_REPAIRS_20261011.md` (PR #316). No new production change is claimed by this evidence-only checkpoint.
