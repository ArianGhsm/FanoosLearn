# Residency 1398 source-page completion — 2026-10-21

## Final status — 2026-10-21 12:16 UTC

PRs #490, #491 and #492 are merged; all five required CI jobs passed on each
merge commit. The official updater deployed
`90424b17196196f7107ca0dabb8f6e8771685dfa` successfully. `/health` is OK and
the server-first sync check reports GitHub `main`, updater checkout and active
release in sync. Deployment request `01a15e5b-4ae9-7776-92a0-66c785fbbe38`
used verified backup `20261021T120919Z-0baff8fc` (114 files).

The final live census is 247 questions: 20 valid English questions exempt from
book sources and 227 book-based questions. All 227 have a chapter, PDF page
and PDF hash; no source-free or below-threshold rows remain. There are 228
source rows because Q221 has a validated two-page source bundle. Seventeen
rows are visibly marked outside official scope, each with a note: 14 use the
authorized Hupp 7e 1406 proxy, and Q45, Q186 and Q189 are the other documented
scope cases.

The frozen assessment remains version 10 with the same checksum, 10 reviews
and zero attempts. Source-only operations did not republish or rekey any
question. Q97 remains draft/voided with its null-choice answer.

## Scope and live baseline

This continuation covers only the 1398 residency sitting. The verified final
state is 247 questions: 20 English questions with valid final/amended keys
(reference-exempt) and 227 book-based questions, each with a same-row chapter,
PDF page and PDF SHA. One book-based question (Q221) retains two citations,
so the database has 228 source rows for 227 questions.

The official 1398 catalog has 19 edition-validity rows. Eighteen exact PDFs
are ready in the private library; the only absent book is **Hupp Oral Surgery
6e**. Hupp 7e is an approved PDF with SHA-256
`35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83` and is
official for residency 1406. The owner instructed the agent to use newer
references for this 1398 completion. Accordingly, only the 14 1398 surgery
items that need Hupp use Hupp 7e as an explicitly labelled 1406 updated
reference proxy. They must be stored as `outside_scope_exam_question`, with a
note that Hupp 7e is not in the 1398 official reference list. This exception
does not change the 1398 catalog or answer key.

## Applied source-only batches

All reports below are private and SHA-bound to the exact study, decisions,
validated pages, and current private reference PDFs. Every guarded source-only
writer passed rollback preview, applied against the verified backup above and
was independently read back. Receipts report zero question, choice and answer
changes.

| Batch | Rows applied | Citation set | Independent audit SHA-256 |
|---|---:|---|---|
| 1398 oral surgery, official Malamed 7e | 2 | Q123 ch3 PDF110; Q124 ch11 PDF211 | `b2643a0bef3df19fe5108f74cb78701008b398ccde86761fee21231ac8a47633` |
| 1398 oral surgery, updated Hupp 7e proxy (reference year 1406) | 14 | Q110 ch11 PDF203; Q111 ch4 PDF60; Q112 ch3 PDF56; Q113 ch15 PDF298; Q114 ch19 PDF417; Q115 ch17 PDF373; Q116 ch23 PDF502; Q117 ch25 PDF547; Q118 ch24 PDF526; Q119 ch21 PDF452; Q120 ch26 PDF574; Q121 ch31 PDF672; Q122 ch2 PDF43; Q129 ch13 PDF237 | `fa57b42810d44f95672c6120ce3a60516613220c83ee587eafc270ed05589ea2` |
| 1398 pediatric dentistry | 16 | McDonald-Avery 10e; all proposed chapter/page pairs pass the current exact-PDF validator | `07ed170b580bf6e6d85a6675adf0fa38b20a3deec390080ef1b21d1950612bb7` |
| 1398 periodontics | 14 | Carranza 12e; all proposed chapter/page pairs pass the current exact-PDF validator | `e771af216b3b5638da31504d4af945304b1eb2b3d94ed7198456bfd138be67aa` |
| 1398 operative dentistry | 1 | Q188, Summit 4e ch11 PDF610 | `bfc5099f898a2f6687e691d4243388a8016a93a8fd516d56cdc71753b20baa90` |
| 1398 oral radiology | 1 | Q225, White & Pharoah 7e ch24 PDF457 | `0f3b75f96fa2c9679b07653304f8753245b0b99929a5160891a4d03a04a17c24` |
| 1398 prosthodontics voided Q97 | 1 | Shillingburg 4e ch22 PDF430 | `eaea25f22f5d6be09b53c24b10d3fd8a0846c45b18ecba1cb663d97dc5af6a8f` |

Applied receipt SHA-256 values, in table order: Malamed `a9da61834337637ae08627801e5d193633ef5aadc0e6325cae9071ecd0b2955f`; Hupp proxy `82ee4c8c7cc2c8516e55157a8e4edfd5831bd43843de4049f9e3fa3ee3670df2`; pediatric `35593e9f05887dd4a15fc316c916f52729a6c8afd3e7919d318a82db7c9e425b`; periodontics `027c49742fbcdf04ee71287694ce8be81bba51cf54078a89139112692c82d59c`; operative Q188 `6ded54d03832dc0541bd7d454facb4811c04abedf950195762fba8b7b9c184a6`; radiology Q225 `3cc65bae8f1b887f9218b08a7e6640ea12d270c871ef3045a2c69e1b941974e6`; voided Q97 `48ffbe5ee272dd78d2dad8d102a97fe41a3bbf7784ab302ac2a3b05bdf84d8f0`.

The 14 pediatric and 14 periodontal citations use the exact official 1398
editions. For each batch, the live writer rechecks question status, stem,
four choices, latest official key and absence of a source before insertion.
The Q97 plan is narrower: it allows only the exact existing draft question
with a null-choice `voided` answer, and changes only its source row.

## Answer alignment and invariants

Private independent reports record the conflicts found while locating source
pages. Examples include Q188's snowplow/key mismatch, Q225's radiology/key
mismatch, and the Q97 null-choice voided state. Pediatric and periodontal
reports also record mismatches or evidence limits question by question.
Every stored official key, question stem, choice, question status, frozen
assessment version, review, attempt and score remains unchanged. A source page
does not authorize rekeying an exam item.

## Code, deployment and immutable assessment

PRs #490, #491 and #492 are merged. Main is
`90424b17196196f7107ca0dabb8f6e8771685dfa`; all five required CI jobs passed
on each merge commit. The official updater deployed that SHA using request
`01a15e5b-4ae9-7776-92a0-66c785fbbe38` and verified backup
`20261021T120919Z-0baff8fc` (114 files). The deployment used the official
`platform-primary` target, passed migration/tests/smoke checks, and left the
host healthy and in sync.

The pre-existing backup-retention work was preserved as a local WIP commit
`ea5cb28` on `codex/retention-two-20261019`; it was not included in this release.

The five Van Noort confidence updates used
`scripts/references/repair_verified_ai_source_pages.php`. The four existing
citation replacements used
`scripts/references/repair_residency_ai_source_citation_replacements.php`.
The Q48/Q176/Q182 key conflicts remain documented in the private alignment
report; the official keys were preserved. Q188 and Q225 conflicts are recorded
in their private research audits. The Q97 source was inserted without changing
its `draft` status or null-choice `voided` answer.

Six post-apply frozen-assessment preflights passed. Assessment version 10,
checksum `ed956277b88933db4fb15ae367b6ec88385da161d8073b9c63af9b23b5bb806d`,
10 reviews and zero attempts remain unchanged. No new assessment version was
published.

## Completion result

The final verified coverage is 227/227 book-based questions and 20/20 valid
English keys exempt from book citations. There are zero uncited book questions,
zero source rows below 0.85 confidence, zero incomplete page/hash rows and zero
scope-unverified rows. The 228 source records include the two-page Q221 bundle
(White & Pharoah 7e, chapter 25, PDF pages 472 and 486).

The nine lower-confidence rows are corrected and read back at 0.970:

- Dental materials: Q241 Van Noort 4e §1.6, printed 33 / PDF 48; Q246 §2.7,
  printed 148 / PDF 163; Q247 §3.8, printed 228 / PDF 243; Q248 §3.5, printed
  202 / PDF 217; Q250 §3.3, printed 184 / PDF 199.
- Operative dentistry: Q176 Sturdevant 7e ch10, printed 318 / PDF 331; Q180
  ch10, printed 347 / PDF 360; Q182 ch5, printed 156 / PDF 169.
- Oral medicine: Q48 Burket 12e ch7, printed 153 / PDF 170.

Source/key conflicts are documented while all official keys remain unchanged:
Q48, Q176, Q182, Q188 and Q225. Q180's page supports its official key. The
three other sources outside the official 1398 scope are Q45, Q186 and Q189;
their source pages carry `outside_scope_exam_question` notes. The fourteen Hupp
7e proxy citations are also visibly marked outside the 1398 official reference
list, with reference year 1406 recorded in their applied receipt and note.

All source-only receipts and the final readback are in the private checkpoint
`/srv/fanoos/shared/research/classification/reports/residency-1398-completion-checkpoint-20261021-v6.json`
(SHA-256 `31ee911258cfe42f45d2b8b53e27e8c88d35afa64b799b656dc4a3e2f601a3cb`).
The assessment remains on frozen version 10; this source-classification
closeout did not publish another version or close unrelated issue #422.
