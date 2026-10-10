# Oral pathology: 42 original Neville 4e printed-page corrections — controlled package

Date: 2026-10-10. Scope: Iranian dental residency pathology 1398–1403 only. **This is not a whole-subject certification.**

The approved original Neville fourth-edition PDF is preserved as 878 page-marked pages (private text SHA-256 `b240862242cfd48c9b90cdfa5cf8ba43a8e4ffe6900ea5d4e02b09419c40c58a`). The exact-year chapter scope was checked against the official catalog, including the newly completed 1398 notice. Forty-three keyed-option-specific original-book passages were manually screened; **42** had exact source text, unambiguous physical chapter and matching printed page corroborated by neighboring original PDF pages. The extra 1398 Q61 physical print-label check failed and is excluded.

Protected immutable 42-case approval candidate manifest: `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/neville4-reviewed-answer-page-candidates-20261010.json`, SHA-256 `06489c524c61e8fe221208117ce9cdc46e57b3d0a18e5f76215815ea1fc60f82`. It lists each source ID, exam year, original official chapter, source fact, PDF page, printed book page and recorded official answer. It excludes 1398 Q60/Q64 suspected answer-key conflicts, all five currently known excluded-chapter sources and the 1404 Q56 human-reviewed record. **A textual page match is not a substitute for a review of medical interpretation or an authenticated official-key correction.**

Versioned update operator `scripts/references/correct_existing_neville4_pathology_pages.php` verifies, before any write:

- Immutable SHA-pinned original 4e full text, 159-source bank snapshot and 159-answer/option/stem snapshot, plus the exact protected 42-case manifest.
- Every original-book proof phrase, canonical PDF page→chapter mapping and independent neighboring printed page labels.
- The single current existing primary AI source ID, exact original edition, year-specific official allowed chapter, current NULL page, no human review or override, published exact stem and media, unchanged choices and recorded official final/amended answer.
- Read-only preview with rollback by default; actual apply restricted to the updater service account, fresh independently verified full database/object backup, protected unique receipt, nonblocking named publisher lock, atomic 42-row compare-and-swap source-page-only UPDATE.
- A postapply independent full 159-row source diff and question/answer snapshot check, and digest comparison of the entire frozen exams of all years 1398–1403.

The only write permitted by this executable is `UPDATE bank_question_sources SET page=:page` on exactly the 42 existing AI-origin reviewed candidate records. **No** source insert/delete, anchor-text/provenance replacement, reference/chapter or human decision change, question/option/key modification, assessment republish, or attempt rewrite is authorized. For older records, legacy nearest-edition notes remain recorded unchanged; the privately audited original 4e evidence is preserved separately.

Apply sequence: green CI, merge, official updater deployment and actual release SHA readback; fresh original live read-only preview; independently verified recent full backup; exclusive coordinator review; one apply; receipt SHA; full 159-row source/answer and whole-assessment frozen-version integrity check. Stop on any unexpected mismatch. Other unresolved citations retain their existing state and are not guessed.

The user-requested course can only be closed after **all** remaining NULL pages, wrong/out-of-scope chapters, clinically conflicting official answer records and human review exceptions are independently addressed. Do not mark the entire pathology course final just because one batch is applied.
