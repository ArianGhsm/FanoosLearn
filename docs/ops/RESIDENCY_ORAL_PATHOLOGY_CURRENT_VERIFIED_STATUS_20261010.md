# Oral pathology — current independently verified production state

**2026-10-10 · FANOOS dental residency 1398–1405 · Oral pathology only**

**Not eligible for final scientific closure of all 159 questions.** This is the current source-of-truth status; the older GitHub progress counts and interim review notes are historical, not a statement of current bank contents.

## Completed and verified

- **159 questions and 159 attached reference/chapter sources** in production.
- **104 source-page-only repairs actually applied and independently verified** with immutable private receipts, official original-book evidence, green CI, approved canonical updater deployment, fresh 54-file full backup, exclusive publisher lock and all-159-bank/official-answer and frozen published-exam before/after comparisons.
- Among the 104, **87 missing original Neville fourth-edition printed pages were filled**, in batches **42 + 12 + 12 + 17 + 4**. Another **17 incorrect nonempty fifth-edition printed-page placeholders were corrected**, in batches **5 + 12**.
- **33 source pages still NULL** (1398: **14**, 1399: **7**, 1400: **4**, 1401: **2**, 1402: **4**, 1403: **1**, 1404: **1**, 1405: **0**). Direct readback, not projection from PRs.
- Last four-page batch was [PR #253](https://github.com/ArianGhsm/FanoosLearn/pull/253), versioned `scripts/references/correct_existing_neville4_fifth4_pages.php`, officially deployed live code `3fd5b6050971387d15cdc12d2b3257d030e398ae`. Before-update verified complete backup: `/var/backups/fanoos/20261010T135514Z-31e44a89`, **54 files**. Independent last-batch whole-subject/frozen-assessment postdiff SHA256 `1898c335c4dcc05a2bd5ff82c4a33d4d79f2d087901105315e848ebda6b273cf`; only those four preapproved page values changed.
- **None** of the original 159 question stems, stem images, choices, recorded official answers, source origins/anchors/chapter/editions, human-reviewed decisions, published frozen exam definitions or past attempts changed through these source-page repairs. Site `/health` HTTP **200** at last independent live check.

## Outstanding scientific review, separated by risk

The remaining **33 NULL pages** consist of **24 ordinary fourth-edition answer-level book checks**, **five fourth-edition questions with potential book/recorded-key contradictions**, **three fourth-edition existing chapter assignments that fall outside that exam year's official chapter syllabus**, and **one original fifth-edition human-reviewed source whose page is missing**.

There are **six total recorded-answer vs exact-original-book research flags**: **1398 Q60, Q64, Q66, Q67; 1399 Q63; 1404 Q48**. The last 1404 case has a nonempty page, so five are in the fourth-edition NULL set. *Do not change the recorded official key*, overwrite human decisions or manufacture citation pages to make these counts disappear. Resolve using authenticated same-year official answer documents/errata and authorized human adjudication.

There are **five out-of-year-syllabus chapter citations**: **1399 Q75, 1402 Q61/Q62, 1404 Q44, 1405 Q44**; the first three are also fourth-edition NULL pages. **1404 Q56** is a protected human source with a NULL printed page. Separately **19 original fifth-edition currently nonempty pages** still need truly independent book-page and answer-specific verification.

## Durable evidence and original source books

Primary protected subject workspace:

`/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/`

- Canonical `README.md` SHA256 `fed8a165d1ce33c38a7c4f7fe565d2f7fb6fca1d58129214d5ae255cddb40714`.
- Full source-by-source 159-row disposition `CURRENT_159_ORAL_PATHOLOGY_EXACT_STATUS_V2_20261010.json` SHA256 `b4ae6a61a1fc5e7acdb42781ff96c9a9800229dc4e99f9cedb9f1fcb381ac306`.
- Human/key and excluded-year decision ledger `HUMAN_KEY_AND_SCOPE_REVIEW_HOLDS_V3_20261010.json` SHA256 `cbb7f4e223bd8ea48d1e76a85a7c3c346cab4c7ca2f9e44c47b8fa6d8a11d241`.
- Original **Neville 4e** approved private PDF (878 pages) SHA256 `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`, protected page-labeled text SHA256 `b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a`.
- Original **Neville 5e** approved private PDF (983 pages) SHA256 `4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac`, protected text SHA256 `348dafa50b9f53648dc5f7cad97547459ba70a227680ab921c5a04b1b63b6c40`.
- The derived book texts have repeatedly been removed by unrelated parallel cleanup. They were restored only by the official hash-verifying extractor from those **unchanged original approved private PDF objects**. Those original PDFs, applied transaction receipts, study evidence, canonical status file and hashed historical checkpoints are essential; do not delete them. Earlier reports are safely archived with hashes under `history/20261010/`.

## Exact finalization gate

Continue normal remaining question citations from their exact original edition, printed page and answer fact, independently verify populated fifth-edition pages, and obtain actual authorized disposition of the six key conflicts, five syllabus exceptions and one human hold. Every subsequent source change must be version-controlled, CI-verified, officially deployed and backed up; use a page/chapter/source-only atomic transaction and prove no changes to all 159 questions/answers, prior full exam definitions or human decisions. **Completion is a verified fact, not a status switch.**

Relevant completed implementations: [PR #239](https://github.com/ArianGhsm/FanoosLearn/pull/239), [PR #243](https://github.com/ArianGhsm/FanoosLearn/pull/243), [PR #244](https://github.com/ArianGhsm/FanoosLearn/pull/244), [PR #250](https://github.com/ArianGhsm/FanoosLearn/pull/250), [PR #253](https://github.com/ArianGhsm/FanoosLearn/pull/253).
