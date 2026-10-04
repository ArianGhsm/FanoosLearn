# Dental residency question corpus: verification status

The local workbook at `.local/exam-questions/dental-residency/question-corpus-1400-1405.xlsx` contains 2,000 form rows, covering 1400–1405. Forms B in 1402 and 1404 are alternate orderings, not additional exam years. The workbook and raw PDFs are local data and are not Git release artifacts.

The current workbook is **not a verified question bank**. Its prior output audit established that spreadsheet rows matched the generated JSON, not that the words, choices, official answers, editions, or chapters matched their primary sources. In particular, the 1,296 chapter assignments are candidates rather than confirmed book chapters. The current catalog has no chapter nodes.

| Year | Form rows | Rows with question text | Four choices found by the current parser | Main blocker |
| --- | ---: | ---: | ---: | --- |
| 1400 | 250 | 250 | 250 | Page-level text, source and chapter review pending |
| 1401 | 250 | 248 | 217 | Two empty text rows; one official final key unreadable; option and page review pending |
| 1402 | 500 | 0 | 0 | Both booklet PDFs lack usable selectable question text; OCR is disallowed |
| 1403 | 250 | 225 | 224 | 25 empty text rows and further option review |
| 1404 | 500 | 500 | 500 | Four-choice splitting alone does not prove accurate words or source mapping |
| 1405 | 250 | 249 | 248 | PDF text encoding corrupts many words/numbers; Q110 empty and Q191 uses graphic options |

The per-row audit is generated locally with `python scripts/import/corpus_to_bank.py audit --workbook=<xlsx> --out=<json>`. Its current result is `.local/exam-questions/dental-residency/question-quality-audit.json`. Across all form rows it flags 528 absent question texts, 33 additional stems or option sets requiring review, one unreadable official final key, 685 absent question-level references, and 176 absent chapter candidates. Every 1405 row is flagged for page review because its PDF text layer is unreliable. These issue counts overlap; they must not be added as a count of distinct bad questions.

The 1405 PDFs already available locally, including the 1.9 MB DentalMindset copy, share the same faulty text layer. A separate 1.3 MB copy from Telegram channel `medicaluniversity`, post 23718, has the same extraction defect. It is saved only as source evidence under `.local/exam-questions/dental-residency/alternate-sources/`. No OCR was used.

`scripts/import/corpus_to_bank.py` now requires all 250 canonical-form questions to have four reviewed choices, a reviewed reference or explicit inapplicability, and a reviewed chapter or explicit inapplicability before it writes an import file. It accepts reviewed corrections to the reference and chapter. Alternate form B is blocked from a second import under the same exam sitting. Multiple accepted official answers retain the accepted option list in their provenance text and are marked `disputed`, which the publisher excludes from scored exams. A structured accepted-options field is a separate bank contract change; it has not been invented inside an existing single-choice field.

To complete this corpus without OCR, obtain genuinely selectable or independently transcribed question text for the missing 1402 and 1403 rows, verify each question against the printed booklet, verify every official key against its final notice, and verify each cited chapter against the exact reference edition. Do not publish a sitting until these checks are complete.
