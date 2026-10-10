# Oral-pathology 1404–1405: approved original-Neville-5e page-only repair operator

Date: 2026-10-10. Scoped operation: exactly **five existing AI-produced source records**, no insert/delete. All original editions and original answer text were examined in the protected site research workspace. The printed page numbers shown below were checked against both adjacent original PDF pages and matched the recorded official answer fact:

| Year question | Existing source chapter | Previous erroneous page field | Correct printed page | Actual PDF page |
|---|---:|---:|---:|---:|
| 1404 Q46 | 10 | 10 | 367 | 377 |
| 1405 Q47 | 10 | 20 | 434 | 444 |
| 1405 Q51 | 12 | 12 | 533 | 543 |
| 1405 Q54 | 14 | 14 | 639 | 649 |
| 1405 Q60 | 16 | 3 | 770 | 780 |

Operator: `scripts/references/correct_existing_pathology_pages.php`. **No automatic site application or assessment republish.** Run preview first as authorized operator; it always rolls back any live read lock and reports five expected source IDs. Its apply mode is restricted to the updater service account and must have an independently completed, less-than-four-hour-old verified full backup and a previously nonexistent private receipt path. Actual application is not permitted until a single-writer coordinator confirms no competing bank publication and retains an approval/preflight ledger.

The operator SHA-pins two private snapshots (existing sources + source answer/choice/stem) and the complete original edition full text. The original source PDF object's verified SHA256 is `4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac`. Exact source, question, choices, final answer, book chapter, original book phrase, allowed official 1404/1405 chapter, and old page must match. The sole SQL mutation updates the **page** field on those five *existing AI, unreviewed* `bank_question_sources` rows with compare-and-swap predicates inside an all-or-nothing transaction. Existing anchor/provenance/reference/chapter and question content are immutable. A source that is human-reviewed, already changed, from an unexpected edition, or lacking a correct PDF fact **aborts the entire batch**.

Review gate: Validate tests, CI, and protected live preview, merge on green, deploy only via official updater, verify new release SHA and health, independently complete/verify fresh full project backup, check exclusive single-writer publication ownership, run the five-row apply once, independently read all five source pages from live DB and compare protected original question/options/answer snapshots and old assessment versions. Never claim success based on script exit alone.

The other 154 pathology records, including 119 older nearest-edition 4e anchor records and one human source in 1404, are **not** affected. The separate 1398 complete official notice now validates Neville 4e chapter coverage, but original page/content checks and an explicit legacy-anchor provenance policy remain required before further per-question repair.

Private proof/answer hashes and reports: `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/`. No private book or question content belongs in Git.
