# Oral pathology: twelve original Neville 5e incorrect page placeholders

Research completed: 2026-10-10. Scope: oral-pathology residency 1404–1405 only. This is a separate, narrowly bounded twelve-row **page-only** existing-source correction, not the 42-row Neville fourth-edition batch.

Against the approved **983-page original Neville fifth-edition PDF**, the following twelve AI-origin sources with incorrect old numeric values equal to their chapter number were matched to the actual clinical fact supporting the recorded official answer. Exact original printed-page labels were checked against physical PDF pages and neighboring labels; each chapter is eligible under the respective official syllabus.

| Exam | Reference chapter | Old placeholder | Correct printed page | Original PDF page |
| --- | ---: | ---: | ---: | ---: |
| 1404 Q41 | 3 | 3 | 143 | 153 |
| 1404 Q45 | 9 | 9 | 329 | 339 |
| 1404 Q47 | 10 | 10 | 379 | 389 |
| 1404 Q49 | 11 | 11 | 489 | 499 |
| 1404 Q51 | 12 | 12 | 569 | 579 |
| 1404 Q53 | 13 | 13 | 607 | 617 |
| 1404 Q54 | 14 | 14 | 635 | 645 |
| 1404 Q55 | 14 | 14 | 664 | 674 |
| 1404 Q57 | 15 | 15 | 702 | 712 |
| 1404 Q58 | 15 | 15 | 733 | 743 |
| 1405 Q49 | 11 | 11 | 491 | 501 |
| 1405 Q56 | 15 | 15 | 709 | 719 |

Protected private original-book evidence manifest is stored at `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/neville5-precise-placeholder-reviewed-cases-20261010.json`; SHA256 `0bd1e9d6df79057c5b95b0fa225ca3d99a7ee014a42f2f10f9d7c9a28012590e`. Full original text SHA256 `348dafa50b9f53648dc5f7cad97547459ba70a227680ab921c5a04b1b63b6c40`.

**Holds:** 1405 Q42 also had a relevant original-page fact but the selected source page did not pass physical adjacent-printed-page corroboration. It was held. Year-1404 Q48 has an apparent contradiction between the recorded official answer and HPV pathogenesis; do not replace the answer or assign a source page without independent official-key adjudication. 1404 and 1405 Q44 both cite an explicitly excluded viral-infection chapter 7 and cannot be forced into another chapter merely to supply a page. Other existing Neville 5e source pages need further verification.

Operator `scripts/references/correct_existing_neville5_pathology_placeholders.php` uses pinned complete original-book text, complete 159-source/answer bank snapshots and the exact private manifest. Before any modification it checks the question/choice/answer/media, AI-only unreviewed source identity, recorded edition and official year scope, expected old-page value, both independent original-book phrases, physical chapter map and corroborated printed page. It uses one transaction, compare-and-swap UPDATE of **bank_question_sources.page only**, a fresh independently verified full FANOOS backup, exclusive protected receipt and a publisher lock. Default mode is read-only rollback. The first live read-only preview passed all twelve cases.

**Actual production status is separate from GitHub or the preview.** Do not mark the twelve updates applied until the versioned script has green CI, official release deployment, a new verified full backup, a protected `applied=true` receipt and independent 159-question/source/answer and all 1404/1405 frozen assessment readbacks. Earlier human decisions, key histories, assessment versions, explanations and student attempts must not change. This set does not fill any of the remaining 78 NULL page fields; it repairs wrong nonempty placeholders.
