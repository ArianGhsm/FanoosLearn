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

## Newer verified W06 checkpoint: 13 approved research citations / 17 scientific holds

**This section supersedes the earlier 11/19 research counts in this dated document.** The untouched original study still has all 30 prosthodontics questions (1398), and no bank question, option, key, human decision, historical attempt or source row has been changed by W06.

After inspecting the exact approved Shillingburg 4e text, two more cases passed the unchanged original primary validator and the independent frozen-question provenance auditor, bringing private research validation to **13 accepted, zero rejected, 17 pending**:

- Q86 — Shillingburg Fixed 4e, chapter 21, **PDF page 412**. Early moisture exposure weakens glass-ionomer cement; this supports the unchanged official choice 4.
- Q90 — Shillingburg Fixed 4e, chapter 23, **PDF page 444**. Excess infiltration glass can increase chroma; this supports the unchanged official choice 1.

The approved research question numbers are **80, 81, 86, 90, 91, 94, 99, 100, 101, 102, 103, 104, 109**. Every item has an original official-edition chapter/page witness and a `pdf N` page label, not an unverified printed-page guess.

Exact protected private receipts:
- `W06/1398-prosthodontics-decisions-candidate-v9.json`: SHA256 `0cf57363ad576b3d03decade7e931ce33314a6b982d79824cdf699c48c015d88`
- `W06/1398-prosthodontics-v9-pdf-validated.json`: SHA256 `87aa2ce0ce8fd0f9b16b3ff006dfaac4527486b9251e37abb9ddee2a3c0f5eb5`
- `W06/research-stage-v9/classification/reports/1398-prosthodontics-v9-independent-audit.json`: SHA256 `ca1ed12cc3c4e52c209519c67d836aedd2e3139ec30685a95271dadc8e268479`
- `W06/1398-prosthodontics-all30-disposition-v9-20261010.json`: SHA256 `4a83b95aef43167afe169af45ce65b3dd591611c16f3d4fef8f24baee283cb31`

The **17 scientifically held questions** are Q82, Q83, Q84, Q85, Q87, Q88, Q89, Q92, Q93, Q95, Q96, Q97, Q98, Q105, Q106, Q107, Q108. Evidence-based blockers include direct contradictions between original chapter text and the stored official key; voided Q97 must remain voided; the supporting original Zarb chapter 19 for Q108 is excluded from the 1398 official scope; and Q95 still needs proof for the exact three-tooth span.

Particularly important original-book counterevidence, with **no answer-key mutation**:
- Q83: Zarb 13e ch8 PDF190 describes the *posterior palatal seal* compensating for processing shrinkage, not the keyed rugae.
- Q85: Zarb 13e ch8 PDF188 associates mandibular grimacing with the buccal shelf, not the keyed hamular notch.
- Q88: Shillingburg 4e ch15 PDF253 reports **low** bis-acryl volumetric shrinkage and limited polishability, whereas the stored key selects high shrinkage.
- Q89: Shillingburg 4e ch21 PDF403–404 describes a buccal shift caused by the maxillary palatal cusp's buccal incline, whereas the stored selected option is a contact associated with a lingual shift.
- Q98: Shillingburg 4e ch7 PDF103 places the key mesial to the distal pontic, contrary to the keyed distal-of-mesial-pontic option.
- Q107: McCracken 12e ch8 PDF105–106 normally locates Class II mandibular indirect retention at the mesio-occlusal first-premolar rest opposite the free-end base, not the keyed lateral-incisor cingulum.
- Q105: McCracken 12e ch5 PDF46 identifies Kennedy classes II and IV, but the stored choice 4 is not a valid class pair.

The corrected complete **29-chapter Shillingburg 4e page map** was merged separately in PR [#259](https://github.com/ArianGhsm/FanoosLearn/pull/259), commit `01363c26a6a85270c68f732100dc95fcc1b08367`, after five green CI jobs. A read-only impact audit of 72 existing Shillingburg 4e citations found three historical chapter mismatches: 1400 Q103 PDF137 9→8, 1401 Q102 PDF125 9→8, 1405 Q108 PDF391 21→20. These require separate protected source-only transaction(s), not silent edits.

**Publication remains pending:** The 13 private citations have *not* received a coordinator-only immutable publication stage, freshly verified full backup, source-only production receipt, and frozen assessment postdiff. Therefore neither 13 newly published source rows nor a complete 30/30 exam may be claimed. The 17 holds must be resolved via authentic official reference/answer review, preserving all original bank and historical data.


## Held-case exact-book audit (latest)

The private W06 report `1398-prosthodontics-all17-original-book-holds-v11.json` (SHA256 `fcfba3bde4e6ca53ab5f6be57e05c5c817d83839b883c436e879b0f9316ea22d`) confirms **all 17 held questions have a literal same-edition quotation at the specified original PDF page and matching chapter**, including Q95 where the page covers only an analogous *two*-tooth situation. Mechanical quote matches **do not validate any answer key**: the publication status remains **13 research approved, 17 scientific holds, zero W06 production inserts**. Original stems, choices, keys, voids, human decisions and attempts are unchanged.

New exact-book findings: Q82 Zarb13e ch10 PDF231 specifies simple articulator for zero-degree monoplane, while the keyed ramp needs semiadjustable; Q92 Zarb13e ch10 PDF218 recommends anterior selection before **final**, not necessarily preliminary, impressions; Q93 Shillingburg4e ch17 PDF331 permits digital rescan and still requires provisional; Q96 Shillingburg4e ch2 PDF32 reports lateral molar disocclusion averages working 0.5mm vs nonworking 1.0mm, not the keyed either-side claim. Q84 Zarb13e ch3 PDF51 describes **less** vertical chewing movement with aging. Q88 Shillingburg4e ch15 PDF253 shows bis-acryl **low shrinkage** and **limited polishability**, unlike the keyed high shrinkage. Q97 remains officially **voided** and Q95 exact three-tooth abutments remain unsupported.

Crucially, Q108 **also** appears in permitted McCracken RPD12e ch24 PDF323, not just Zarb's excluded ch19; McCracken expressly distinguishes interim obturation to separate nasal/oral cavities from immediate surgical obturation to hold the dressing. The excluded-book obstacle is no longer the only issue, but the existing Q108 answer choice3 still conflicts with that official-source evidence. No answer change is authorized.
