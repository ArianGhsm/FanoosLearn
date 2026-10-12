# Residency 1405 Q207 — TWO exact official source chapters and PDF pages (2026-10-13)

Q207 is one of five previously source-free oral-radiology questions (the other four Q191–Q194 already have separately audited one-source chapter/PDF-page decisions, original published question/answer preservation, and guarded source-only rollback preview). The live Q207 final official answer is **option 4**, naming **glandular odontogenic cyst and aneurysmal bone cyst** as two lesions needing post-treatment recurrence imaging.

The exact official 1405 reference is **White & Pharoah, 8th edition**, `white-pharoah-radiology@8e`, with current approved private PDF SHA-256 `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`. Both relevant chapters were independently checked inside the *explicit official 1405 syllabus* and within the approved SHA-bound chapter map.

| Correct answer lesion | Official chapter | Actual source-PDF page | Verbatim source support |
|---|---:|---:|---|
| Glandular odontogenic cyst | **23** | **1161** | “Treated cases should be followed with periodic radiographic examinations to assess for recurrence.” |
| Aneurysmal bone cyst | **24** | **1294** | “The recurrence rate ranges from 19% to about 50% after curettage, and approximately 11% after resection. Careful follow-up is needed.” |

Both exact phrases were looked up in the actual current edition PDF page text; neither page number is a guessed offset, title index or printed-book page. Actual official-year validity allows both ch23 and ch24. The original published question stem, four choices and latest final answer were compared read-only to the official key; **no bank_question_sources row exists** yet, and no answer or original options were changed.

Protected immutable scientific research report:
`/srv/fanoos/shared/research/classification/recovery-1405-radiology207-20261013/classification/reports/1405-radiology207-two-chapter-evidence.json`

Report SHA-256 `2b1f59080f1a41b4935ee1824502de84aa9705f0c5377c2ec0b9859528b1a8bc`. Original live question/choices/key snapshot SHA-256 `c876a35a823a3405e7f1f19dcaa138bbb003311aaabc7a149b209bdd84c1793c`.

## Important blocker; DO NOT publish only one half as if complete

`SourceOnlyPublisher::run` currently enforces **exactly one source per accepted question** (`count($sources) !== 1` fails closed), whereas Q207's correct official option cites two distinct conditions described in **different official chapters**. Recording only one chapter with one page as a complete citation would hide essential correct-answer support, conflicting with the requirement that **every chosen chapter must have its actual PDF page**. Therefore Q207 is a **scientifically verified TWO-CHAPTER research hold**, not a completed bank source or publication. Any eventual source-only workflow should support a reviewed two-source mapping, independently audit both exact pages, preserve the original question/key/choices and frozen assessment attempts, and obtain unique write receipts plus independent live readback.

This is not a request to change the official answer or rewrite a published exam. The radiology question is *not* marked fully sourced pending a safe two-citation publisher and evidence audit.
