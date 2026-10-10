# Oral pathology — current verified production and original-book status

**2026-10-10 · Dentistry residency 1398–1405 · Pathology only**

**Scientific course status: not yet eligible for 159/159 closure.** This document supersedes older and now-stale progress counts in the GitHub history while retaining the immutable evidence and all protected server source files. Do not interpret a chapter field that is nonempty as independently correct.

## Confirmed live bank state

- **159 questions, 159 attached original-year reference/chapter source records.**
- **83 existing-source printed-page metadata corrections actually committed**, each with an exclusive receipt, a fresh independently verified full FANOOS backup, a production-source read-only preflight and whole-subject readback:
  - **66 missing original Neville 4e pages filled**, in three batches of 42, 12 and 12.
  - **17 wrong nonempty original Neville 5e placeholder pages corrected**, in batches of five and twelve.
- **54 source records still have NULL printed page**. Per year: 1398: 16; 1399: 10; 1400: 6; 1401: 6; 1402: 8; 1403: 7; 1404: 1; 1405: 0. These counts were rechecked against live MySQL, not projected from PR results.
- Exactly **one protected human-origin record**, 1404 Q56, has no page. No human-authored classification, question, option, recorded official answer, earlier published entire exam definition or student attempt was altered by these operations.
- At least **19 other nonempty Neville 5e page assignments** still require original-edition answer-specific scientific and printed-page verification.

### Four recorded-key vs primary-book conflicts requiring authority

The original books raise research-level concerns for 1398 Q60 (microglossia), Q64 (ancient schwannoma), Q66 (oral melanoacanthoma), and 1404 Q48 (the recorded answer describes verruca vulgaris as lacking confirmed HPV involvement). **No keys were changed.** Authenticate official answer-booklets, original errata and any subsequent official revision; only an authorized human/official-answer process can adjudicate them.

Five existing source chapters are excluded by the official syllabus applicable to the exam year: 1399 Q75; 1402 Q61, Q62; and 1404 Q44, 1405 Q44. Do not invent an allowed alternate chapter merely to clear a blocker. For 1399 Q75, original Neville4e ch14 discusses periapical cemento-osseous dysplasia; a full answer/management proof is still needed before any source/chapter correction.

## Independently verified integrity and full rollback information

All five guarded source-page-only transactions applied with `applied=true`, with postapply readback showing only the enumerated `bank_question_sources.page` fields changed, across **all 159 source rows and all 159 question stem/choice/image/official answer snapshots**. Relevant entire already-published exam-version SHA256 values were identical before and after each update.

The fifth completed batch applied **12 Neville4e missing-page corrections** after the official deployment of `273899d2c22bea4a570506c9549c9056e8265e4e`, with a verified 54-file full project backup `20261010T130635Z-6d6b9aa3`. Its independent postdiff SHA256 is `64213e79236dd7b968611efe183d4c68b07bc1cf42fb9ab7b012fa78907813cb`.

All protected private details and receipts live in:

`/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/`

Read `README.md` (SHA256 `14e32aa9777fcb929c8dfe1de1b9e20a61e03d3de73dcdee56eaaeebdaa3e657`) and the full 159-question disposition `CURRENT_159_ORAL_PATHOLOGY_EXACT_STATUS_20261010.json` (SHA256 `3d24772ee3556bb7fa3a81eef503612f433603c9f47d44c1588a7957a2d6feda`).

All older narrative checkpoints have been **archived, not destroyed**, under `history/20261010/` with hash-verified manifest. Original PDF and answer-source evidence, published snapshot digests, production receipts, backups and reproducibility scripts were never intentionally discarded.

### Original book-text resilience after parallel cleanup

Parallel cleanup removed prior original-book text copies from the research workspace. We recovered **both** from the *approved original production private PDF objects* using the official versioned `scripts/references/extract_server_reference.py` with hash-verified dry run and apply:

- **Neville 4e**, original private PDF SHA256 `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`, 878 physical pages, extracted text SHA256 `b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a`.
- **Neville 5e**, original private PDF SHA256 `4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac`, 983 physical pages, extracted text SHA256 `348dafa50b9f53648dc5f7cad97547459ba70a227680ab921c5a04b1b63b6c40`.

Both verified books now reside in `references/` in the dedicated pathology workspace. Neville4e also has its restored W04 copy. **Future cross-subject cleanup must not delete either text or any unique applied receipt** without equivalent independently verified permanent storage; those files are required to validate ongoing source page operations.

## Next completion gates

Of the 54 NULL pages, 53 are older original Neville4e and one is human-reviewed original Neville5e. Among the 53, 47 are ordinary outstanding fact-level review and the other six are held by excluded chapters or likely official-key/book contradictions. Separately, at least 19 other populated original Neville5e pages require exact-source verification.

Complete only with individual answer-specific original-book evidence, exact exam-year official allowed chapter, physically corroborated book-printed page, and authorized adjudication of contested official answers or human decisions. Each further mutation must pass reviewed CI, official deploy, fresh independently verified backup, exclusive publication lock, complete 159-row invariants, entire frozen-exam digests, production receipt and site health.

**Do not mark oral pathology fully finished until the last 54 NULL fields and all remaining evidence/official-key, scope and human exceptions are legitimately resolved.**

Prior implementation milestones: [PR #239](https://github.com/ArianGhsm/FanoosLearn/pull/239), [PR #243](https://github.com/ArianGhsm/FanoosLearn/pull/243), [PR #244](https://github.com/ArianGhsm/FanoosLearn/pull/244).
