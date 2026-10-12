# Fanoos residency 1402 — 15 newly verified original PDF pages, 12 October 2026

**Scope:** dental residency, 1402, round 1. This is a live, independently read-back production source-only checkpoint; the 15 source edits described here were performed with controlled official original-reference evidence, not a claim that all legacy chapter/page assignments are independently sound.

## Baseline, verified result, authoritative original files

At start, 112 of the 250 bank questions had **same-row** reference chapter node, positive original PDF page and 64-character PDF SHA-256. At completion of these operations the live bank read-back was **127/250**, or **15 new strict source-row completions**; 123 raw incomplete comprised 20 final-key English book-exempt and 103 non-English book candidates (including 6 historically voided). The frozen student assessment remained version 9 at initial source-readback; publication of a new frozen version requires the separate guard-and-audit sequence below.

| Official 1402 source | Verified original server PDF SHA-256 |
|---|---|
| Hupp Oral and Maxillofacial Surgery 7e | `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83` |
| Malamed Local Anesthesia 7e | `cc668f86a731bed4c016feb1a3e20be033637c7d0a30da86358dbc9b2a4c08e7` |
| Zarb Prosthodontic Treatment for Edentulous Patients 13e | `bcf92916cd0a01915695d1cb5aad8c51e7f2dac5909477c4df4454d229156a3d` |

Every listed edition was checked against its **year-specific official chapter scope**, exact approved source PDF and SHA-pinned chapter-page map. The protected original reference file was read page by page without extracting or uploading a full text copy. All accepted sources had explicit short book-text evidence and passed their PDF page's printed label with at least two neighbouring matching page labels, using `PrintedBookPageEvidence`. Source IDs, preexisting edition and chapter, legacy printed page, historical anchor hash and AI/unreviewed state were matched. The operator changed **ONLY** `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`, with a rollback-only transaction preview preceding each applied batch; questions, answers and choices changed zero.

## Eleven official oral surgery / local anaesthesia questions

| Q | Reference | Chapter | PDF page | Printed page | Verified topic |
|---:|---|---:|---:|---:|---|
| 83 | Hupp 7e | 11 | 211 | 197 | Maxillary tuberosity fracture; section crown and allow six to eight weeks |
| 85 | Hupp 7e | 26 | 577 | 563 | Open-bite apertognathia and segmental maxillary correction |
| 86 | Hupp 7e | 21 | 449 | 435 | Sjögren focus score and lower-lip minor salivary gland biopsy |
| 87 | Hupp 7e | 28 | 637 | 623 | Wardill palatal cleft repair |
| 89 | Hupp 7e | 19 | 418 | 404 | Bisphosphonates exert antiangiogenic rather than proangiogenic effects |
| 90 | Hupp 7e | 20 | 430 | 416 | 2% ephedrine facilitating drainage in sinusitis |
| 93 | Hupp 7e | 17 | 362 | 348 | Lateral pharyngeal space and pharyngeal constrictor muscles |
| 94 | Hupp 7e | 14 | 267 | 253 | Implant angulation beyond 20 degrees and lateral loading |
| 95 | Hupp 7e | 31 | 680 | 666 | Arthrocentesis in anterior disc displacement without reduction |
| 97 | Malamed 7e | 14 | 267 | 250 | Auriculotemporal nerve blocked by Gow–Gates |
| 100 | Malamed 7e | 13 | 228 | 211 | PSA injection too lateral, leading to unexpected tongue numbness |

Three separate protected plans/previews and **three atomic apply receipts** under
`/srv/fanoos/shared/research/classification/reports/`:
- `residency-1402-hupp-malamed-ready1-applied-20261012.json`: Q83,85,86,87; receipt SHA-256 `2841edaa60702224467a66201251802de9b426621d8aab0d2f4d61f9fd2983d3`.
- `residency-1402-hupp-malamed-final2-applied-20261012.json`: Q89,90,93; receipt SHA-256 `a1e60cbdc52d10699b91fd25be6f7bb4522540b7fc35e6c9941973af8606d931`.
- `residency-1402-hupp-malamed-safe3-applied-20261012.json`: Q94,95,97,100; receipt SHA-256 `a6eb26c90955c9aea948fcd4c8c40f7bf22e6db1cec009ab7517906168787675`.

Each operation used full production backup `/var/backups/fanoos/20261012T161053Z-a03b8f8d` independently verified by the canonical `verify-backup.php` as **114 files**; the backup was checked again before applies.

## Four official complete denture questions

| Q | Reference | Chapter | PDF page | Printed page | Verified topic |
|---:|---|---:|---:|---:|---|
| 101 | Zarb 13e | 15 | 332 | 318 | Sibilant /s/ articulation across languages |
| 106 | Zarb 13e | 8 | 188 | 174 | Border-molding and resisted closure against a hand on the chin |
| 108 | Zarb 13e | 7 | 140 | 126 | Trisodium phosphate as the alginate setting retarder |
| 118 | Zarb 13e | 12 | 281 | 267 | Pressure-indicating paste applied as a thin even layer |

Private plan `residency-1402-zarb13e-four-20261012.json`, passed rollback-only guarded preview (4 accepted). Atomic source-only apply receipt
`residency-1402-zarb13e-four-applied-20261012.json`;
SHA-256 `36afc3db2ec8e6b3830fc01cb46c68be825213c27ecc8b6cff21b5beadf8a8eb`.
The same complete, independently verified 114-file backup guarded this transaction.

## Scientific holds and remaining risk

**Not automatically classified:** Hupp Q92's text is answer-relevant on PDF page 411, but the validator could not corroborate the purported printed page 397 against neighboring page labels. Hupp Q88's recorded answer (Iris scissors for undermining) conflicts with the book's explicit distinction between fine-work Iris and undermining Metzenbaum scissors; it needs key/source human adjudication, not an invented page. Other Hupp questions lacking direct topic corroboration were not forced.

**Pediatric McDonald–Avery 11e:** 5 research candidates Q154, Q155, Q161, Q162 and Q166 had individually checked book passages and observed original printed labels, but `data/bank/reference-chapter-pages.json` **has `source_pdf_sha256: null` for this edition**. Consequently the canonical source verifier's `--require-map --expected-sha256` rejects this reference, so **zero pediatric rows were changed**. The legacy chapter-run map also has questionable starts (some point to the preceding chapter's references rather than explicit chapter opening). It must be regenerated/validated against the exact approved book SHA `a344465f89ff6711099a8bee1878e83236c9778340447ac7491bbc27a89fb629`, CI-reviewed, deployed and then its verified pages rechecked. Do **not** simply pin a digest to a chapter map with unverified boundaries. `mccracken-rpd@13e` also has unbound `source_pdf_sha256` and remains held.

Several other 1402 legacy sources have their chapters outside the existing structured official-year scope; the previous 29-row year-scope hold audit overlaps this 103-question incomplete cohort and **is not an additional 29 cases**. Other references like Carranza have existing chapter nodes but blank printed page and cannot be completed by copying an unproven book offset.

## Frozen-assessment publication gate

The previous complete frozen student assessment was **version 9**, scoring 243 questions. The seven officially voided question numbers 60,96,99,102,103,144,181 remain protected. After completing the 15 source-only repairs, a **new full production backup after all source updates** is required and must pass independent `verify-backup.php`. Then repeat `audit_published_assessment.php` preflight against v9; use the canonical owner-authorized `import-bank.php publish` and compare all 243 questions for immutable non-explanation content in v9→v10. Verify that the **15 explanation differences match exactly the 15 updated source question IDs**, with no other changes and all prior frozen versions and attempts untouched; read back version 10 published and check live `/health`. Do not represent v10 as published until those checks have succeeded.

## Additional three Hupp 7e original pages applied after the first 15 (UTC 16:47)

A follow-up original-book review found **three more** previously chaptered
but page-unbound Hupp oral-surgery questions in the official 1402 chapter
scope, each with a direct topic-specific answer and printed-header
evidence corroborated by both neighboring PDF pages:

| Q | Chapter | Exact PDF page | Printed page | Original-book fact corroborated |
|---:|---:|---:|---:|---|
| 81 | 1 | 27 | 13 | Severe hepatic dysfunction and inability to metabolize nitrogen in swallowed blood; encephalopathy risk and possible inpatient surgery |
| 84 | 25 | 543 | 529 | Vertical facial buttresses are nasomaxillary, zygomatic and pterygomaxillary; the orbit is not one of them |
| 91 | 23 | 499 | 485 | Marsupialization leaves pathological tissue in place without complete histologic examination |

Their source row identities, prior printed pages, anchors, actual Hupp 7e
PDF hash `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`
and year-specific official scope were validated by the same
`repair_verified_source_pages.php`. A **3-row rollback-only preview**
accepted all three and reported zero question/choice/answer changes.
The identical private plan was applied in one guarded source-only
transaction at `2026-10-12T16:47:28Z`, backed by independently
verified 114-file full backup
`/var/backups/fanoos/20261012T161053Z-a03b8f8d`.

Private plan:
`/srv/fanoos/shared/research/classification/reports/residency-1402-hupp-last3-ready-20261012.json`
(SHA-256 plan `ae868b13be2a8981394a7104700ba00568165db6a40a53c886a4789f9996a3e8`).
Atomic applied receipt:
`/srv/fanoos/shared/research/classification/reports/residency-1402-hupp-last3-ready-applied-20261012.json`,
receipt SHA-256 `1ae8302229c41c288cc641a919fce965953e786bf46667c7fbb7c8c62253b024`.

**Cumulative live strict source-row completeness after all 18 corrections:
130/250 (52%).** Exactly **120** raw field-incomplete rows remain; 20
final-answer English questions are book-exempt, leaving **100 non-English**
incomplete, of which 6 voided legacy questions are excluded from
the 243 scored frozen-question set. Thus **94 scored non-English
source/page citations** remain to be independently established.
These are counts of strict same-row binding, NOT a retrospective
independent semantic re-audit of all older source rows. The
29 year-specific structured official-scope review flags still overlap
the sets; they are not additive.

The previously started backup `/var/backups/fanoos/20261012T164444Z-8022b068`
completed **before** these final three writes and therefore is **not**
a sufficient post-update backup for student frozen-v10 publication.
A new backup following Q81/Q84/Q91, a second independently verified
manifest, full preflight, publisher, full 243-question v9-to-v10
frozen-content comparison, exact 18 explanation-ID diff check, and
site health are mandatory. Do not claim version 10 until verified.

## Final frozen student publication: version 10 completed and audited

**2026-10-12, production verified.** After the final three source-only
operations and the independently verified source count of **130/250**,
a **new full backup after all 18 source corrections**
`/var/backups/fanoos/20261012T165117Z-fdcd2bd9`
completed, with an independent canonical
`scripts/ops/verify-backup.php` check of **114 valid files**.
This backup is chronologically later than the source-only
Hupp Q81/Q84/Q91 apply (`2026-10-12T16:47:28Z`).

The reviewed full-sitting `audit_published_assessment.php` preflight
compared the current bank to the frozen version **9**, with
`question_content_identical=true`, **243** scorable questions and
20/20 protected radiology-study questions matched.

Canonical owner-authorized
`scripts/import/import-bank.php publish --type=residency --year=1402 --round=1`
successfully created **published assessment version 10**, version ID
`01a13107-5ed7-747c-9560-2a429dddd3bf`, without creating a new exam.
The seven protected officially voided Q60, Q96, Q99, Q102, Q103, Q144,
Q181 remain excluded from scoring. Historical version 9 is preserved.

**Postpublication immutable regression audit**
`audit_published_assessment.php --previous=9 --current=10`
returned `question_content_identical=true`, `questions=243`,
`updated_explanations=18`. A second independent comparison of all
243 explanation fields at question ID level found exactly the **18**
source-corrected questions
**Q81/Q83/Q84/Q85/Q86/Q87/Q89/Q90/Q91/Q93/Q94/Q95/Q97/Q100/Q101/Q106/Q108/Q118**
and `unexpected=[]`, `missing=[]`.

Separate production SQL read-back confirmed
`exam_assessments.current_version_no=10` and `status=published`;
strict same-row `node_id/pdf_page/pdf_sha256` bank coverage remains
**130/250**. Public `/health` returned `status: ok` at deployed
site release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`.
The source-only changes did **not** change question stems, options,
official answers, human review decisions, prior student attempts
or any earlier frozen assessment versions.

**Remaining backlog (not complete):**
120 raw strict field-incomplete question records, minus 20
reference-exempt final-key English = **100 non-English historical citation
cases**. Six of these are voided, thus **94 scored clinical questions**
still need book chapter/page. The 29 old official-structured-scope
mismatch alerts overlap this count and the older currently bound
rows; each remains subject to exact official notice review.
McDonald–Avery pediatric 11e and McCracken RPD 13e maps currently lack
an exact approved-PDF SHA binding and must be independently reconstructed
and CI/deploy-verified before writing any page; do not silently infer
page offsets. This exam is **not yet scientifically citation-complete**
despite the safe published version 10.
