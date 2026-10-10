# Second original Neville 4e pathology page batch: 12 fact-checked references

2026-10-10, subject only *oral pathology*, dentistry residency 1398–1403. The 159 live records already contain original-year source/chapter assignments. After the independently verified five and 42 earlier page repairs and the second 12-case Neville5e placeholder correction, the old Neville4e source records still have NULL printed pages. This change targets exactly **12** of those currently NULL pages, and no others.

The twelve question-number/source-ID/printed-page pairs are in the protected immutable original book research manifest at `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/nev4-next12-answer-page-reviewed-20261010.json`, SHA256 `addaa20964d777b1029c57340d9e7f8f46f35485592d085b5bdfd14663efa3c0`. Historical review used a temporary page-text derivative, which cleanup removed. The current correction script checks each exact approved Neville4e page directly, then confirms its printed label from neighboring PDF pages and checks the chapter against the hash-bound map. Approved Neville4e PDF SHA-256: `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`.

| Question | Chapter | Correct printed page |
| --- | ---: | ---: |
| 1398 Q72 | 14 | 583 |
| 1399 Q67 | 2 | 87 |
| 1400 Q63 | 3 | 130 |
| 1400 Q69 | 11 | 458 |
| 1400 Q72 | 12 | 521 |
| 1401 Q61 | 1 | 28 |
| 1401 Q66 | 7 | 232 |
| 1401 Q78 | 16 | 716 |
| 1402 Q63 | 3 | 134 |
| 1402 Q73 | 13 | 550 |
| 1403 Q55 | 14 | 621 |
| 1403 Q56 | 15 | 645 |

The existing-source-only operator `scripts/references/correct_existing_neville4_next12_pages.php` is SHA-pinned to the original 878-page Neville4e book and two all-159-row live question/source snapshots taken **after** the prior 12 Neville5e corrections. Live no-write preview passed 12/12. It verifies source IDs, 159 question stems/choices/images/official answers and all source metadata, official original edition and year scope, printed pages and proof passages, no human-reviewed source, current page NULL, fresh independently verified complete backup, protected new receipt, advisory publisher lock and one atomic compare-and-swap `bank_question_sources.page` UPDATE. It cannot update stems, options, answer keys, anchors, edition, chapter, origin or historical exams.

**Do not mark these twelve corrections applied** merely because CI or a PR succeeded. Wait for green CI, official owner-authorized release, fresh complete backup, deployed CLI 12/12 preview, protected committed receipt and independent all-159 questions/sources and the six full frozen 1398–1403 assessments readback.

Unresolved source pages, syllabus-excluded chapters and recorded-key/book conflicts cannot be bulk-populated from only lexical topic overlap. The one 1404 human-reviewed source must never be overwritten.
