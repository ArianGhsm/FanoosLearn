# Residency English reference exemption — 2026-10-10

Owner-approved rule: dentistry-residency English questions are final by their official answer key alone. Do not assign any book, edition, chapter, PDF page, passage, synthetic `bank_question_sources` row or artificial source. Keep all recorded answer statuses, question texts, options, previous exam versions and attempts intact.

Read-only production audit of the current answer per question found exactly 159 English questions: 20 each in 1398,1399,1400,1402,1403,1404,1405 and 19 in 1401. All have non-null answer choices: 158 final, one amended, zero voided, zero missing. There are zero residency/English `bank_reference_validity` rows.

Of 1,995 residency questions, 1,602 have bank source rows and 393 do not. English accounts for 159 of those 393; the other 234 are the non-English evidence-dependent classification backlog. This exception changes **the interpretation of backlog**, not the source-row or answer tables. No live data writes occurred.

To reproduce: use the server's authenticated read-only DB, join bank_questions to bank_subjects, bank_exam_sittings and bank_exam_types, filter residency; group NOT EXISTS bank_question_sources by subject; resolve the latest bank_official_answers for English by recorded_at and ID, verifying status and choice; confirm there are no official English validity entries. Publish both raw and exemption-aware counts. Invalid/absent future English keys require separate key review, never invented book citations.

See permanent policy in docs/PROJECT_PRINCIPLES.md §3A and docs/product/09_CHAPTER_CLASSIFICATION.md. Research workers W01–W08 must not touch English; W09 must exclude it from book citation processing but preserve historical answer records. This checkpoint is a dated observation, not a future live-data invariant.
