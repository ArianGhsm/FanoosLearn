# Pathology original Neville4e: four additional printed pages

2026-10-10, exclusive original-edition dentistry residency oral pathology 1399–1402. These four cases passed exact printed page labels of three adjacent physical Neville4e PDF pages, the correct year-specific chapter scope, original text specific to each recorded final clinical answer, and complete live 159-question/159-source snapshot checks.

**PDF workflow correction:** this historical pass used a temporary page-text derivative, which cleanup removed; its path and hash are recorded only in the private cleanup receipt. The approved Neville4e PDF SHA-256 is `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`; current operator code checks the exact requested pages directly and must not recreate the derivative.

| Question | Chapter | Neville fourth edition printed page | Original PDF page | Fact |
| --- | ---: | ---: | ---: | --- |
| 1399 Q77 | 15 | 653 | 660 | Granular cell odontogenic tumor is classified under tumors of odontogenic ectomesenchyme |
| 1400 Q67 | 10 | 350 | 357 | Oral melanoacanthoma does not require further treatment after confirmed diagnosis |
| 1400 Q74 | 14 | 595 | 602 | Fibrous dysplasia without functional/cosmetic disturbance may not require surgical treatment |
| 1402 Q65 | 6 | 195 | 202 | Chronic hyperplastic candidiasis is the least common clinical form |

The immutable protected four-case evidence `/srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/nev4-sixth4-reviewed-20261010.json` has SHA256 `44aae015fec9bc301fc60d1a9c47795b01966f4699207dfd8dce3402fed5fb82`. Whole-course exact source snapshot SHA256 `1784a930935d2dd23e348f2120486c6375507d02c7007222f60cb8fed5717f77`, official-answer/choices and stems SHA256 `776f561da178195ea0b62eb54b3ffb4d63a9eef7ec1cf36230a03620fd37c421`.

The guarded `scripts/references/correct_existing_neville4_sixth4_pages.php` is **read-only by default** and can update only the four AI-origin unreviewed existing `bank_question_sources.page` NULL fields in one atomic compare-and-swap transaction, and only after matching exact whole-course snapshots, official keyed choices/stems, year scope, book evidence, valid full fresh backup, publisher lock and unique protected receipt. It cannot update any answer key, human-reviewed source, exam or question.

**This PR itself does not perform production corrections.** Only after green CI, official owner-authorized deployment, new 54-file verified full backup, deployed read-only preview, applied=true unique transaction receipt, all 159 original source/answer + six published entire-exam SHA256 unchanged can the new pages be counted in the completed record. Prior confirmed bank 104 corrected metadata fields and 33 NULL printed-page fields; after successful verified four-case repair it would be 108/29.

Other held cases remain unresolved: at least six recorded official answers contradict original-book clinical facts; five source chapters lie outside official year scope; protected human 1404 Q56 is NULL; physical page label for 1401 Q73 is misread as chapter13 in text extraction and was held pending independent physical-page verification. Never fill on lexical overlap alone.
