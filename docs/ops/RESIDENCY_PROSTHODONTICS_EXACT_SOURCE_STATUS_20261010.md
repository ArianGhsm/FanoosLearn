# Prosthodontics residency 1398–1405 — exact-source checkpoint, 2026-10-10

**Scientific status: 1398 partially verified, NOT published or closed.** Question stems, choices, official answer keys, human source decisions and historical attempts are immutable.

## Official scope and code

The owner-provided 1398 full source notice (see `RESIDENCY_1398_COMPLETE_NOTICE_20261010.md`) establishes McCracken RPD 12e (all chapters), Shillingburg Fixed 4e (except chapters 5, 11, 12), and Zarb Edentulous 13e (except 18–22). No nearest-edition substitution is authorized. GitHub PR #235 added an exact-edition source-only 1398 prosthodontics publisher gate and merged as `126d437df6e516dbc650c6deb58f40eb90851f8a`; five PR CI checks passed, and later live site releases descend from that commit. This code gate **does not** imply source rows were inserted.

## Production bank counts

The read-only production W06 inventory found **240 prosthodontics questions** in residency years 1398–1405: **30/30 in 1398 without source** and **210/210 in 1399–1405 with existing source rows**. Existing source presence is not independent evidence validity.

Historical 1399–1405 metadata audit found **14 missing source pages**, **66 rows with chapter confidence below 0.85**, and **three 1402 scope violations**: Q102 Zarb 13e chapter 19 (voided official answer), Q103 Zarb 13e chapter 22 (voided), Q121 Shillingburg 4e chapter 11 (final). Six human-origin sources in 1404 are protected. Do not alter official answer status or human review to silence these flags.

## 1398 source-book evidence actually validated (11/30)

The exact original approved McCracken RPD 12e PDF (389 pages), Zarb Edentulous 13e PDF (466 pages), and Shillingburg Fixed 4e PDF (585 pages) were SHA-checked against private source objects. Earlier W06 work temporarily reconstructed page-marked text; cleanup removed those derivatives. Current and future verification reads selected pages directly from the exact approved PDFs. Shillingburg remains **research-only** while its global chapter map is incomplete. The historical `apply_classification.py` pass accepted **11** decisions, **0** rejected; when explicit `pdf N` page labels were used, the independent `audit_private_study_batches.py` pass also reported **11 accepted / 19 pending** with every question, choice and answer identical. Those historical outputs do not authorize recreating text files.

| 1398 question | Official edition | Chapter | Original PDF page |
| --- | --- | ---: | ---: |
| 80 | Zarb Edentulous 13e | 4 | 58 |
| 81 | Zarb Edentulous 13e | 10 | 221 |
| 91 | Zarb Edentulous 13e | 12 | 292 |
| 94 | Shillingburg Fixed 4e | 6 | 84 |
| 99 | Shillingburg Fixed 4e | 9 | 144 |
| 100 | Shillingburg Fixed 4e | 6 | 88 |
| 101 | Shillingburg Fixed 4e | 19 | 369 |
| 102 | McCracken RPD 12e | 17 | 248 |
| 103 | McCracken RPD 12e | 6 | 65 |
| 104 | McCracken RPD 12e | 16 | 240 |
| 109 | McCracken RPD 12e | 7 | 99 |

**These are validated private research results, not confirmed production inserts.**

Protected validation and integrity:
- Research root: `/srv/fanoos/shared/research/classification/parallel/W06`.
- Original study SHA256: `7da095a7659e303437643cea5f1c03cb427a38e5d81bb2ec7d0afa8ec5502aee`.
- Decisions SHA256: `99815b5b27078f2e84e37d8277987beeeba0f86297e799cddcf0f41aa804580a`.
- PDF-page validated study SHA256: `30609b8c8d4d6ed13725e288ed9f6e344fd8d00211272cea747558040f1f5d5a`.
- Independent report SHA256: `b9f79b793c523ccb77ee8bd726c167968bf0d06ec31aa8a08112d6315ec149cf`.
- Every-question disposition SHA256: `dba153fb26560982c931b23b5d4aece44aca5b6ca430fb2c8ac3fb33d327fdbd`.
- Independent checkpoint SHA256: `168363eb16b91be6e56cee61077696b91fca738d290bfa84cdac0926516d70e2`.

## The 19 unapproved 1398 questions

Q82, Q83, Q84, Q85, Q86, Q87, Q88, Q89, Q90, Q92, Q93, Q95, Q96, Q97, Q98, Q105, Q106, Q107 and Q108 remain **scientific holds**. The private `1398-prosthodontics-all30-disposition-20261010.json` identifies each exact candidate edition/chapter/PDF-page lead and reason.

Reasons include: cited source not sufficiently supporting the official option; figure-dependent anatomy; malformed/ambiguous official choices; Q97's voided key; and Q108's subject matter located in Zarb chapter 19, excluded by the official 1398 syllabus. No answer was invented or edited. These 19 have **not** passed the primary + secondary approval gates and must not be imported.

The Shillingburg 4e global map has gaps at PDF pages 1 and 55. Independent original-page inspection also establishes chapter 8 at PDF page 110 and chapter 9 at PDF page 142, while the old map merges both under chapter 9. Chapter boundary repair requires its own tested and impact-audited PR, not a synthetic page/chapter guess.

## Release controls still required

For the 11 validated private rows, the central single-writer coordinator must independently refresh the live unsourced study, pin an immutable stage, verify no existing source rows or competing writer, run source-only transaction preview, finish a **new complete verified full backup**, apply only the approved batch with a unique protected receipt, and read back all question-source rows and immutability invariants. Full frozen assessment pre/post diff may change only source-derived explanations, with a new version published when appropriate; site health and exact deploy SHA must be checked. The W06 research owner does **not** directly modify production DB.

The case cannot truthfully be marked 30/30 or closed while these 19 evidence holds and historical page/scope anomalies remain.
