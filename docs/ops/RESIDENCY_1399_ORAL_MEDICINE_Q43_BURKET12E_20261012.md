# Residency 1399 Oral Medicine Q43 — Original Burket 12e Official Source (2026-10-12)

**Original reference research complete; private source-only classification audit passed; production insert and student-facing assessment publication not yet performed.**

## Live question and official syllabus
- Dentistry residency 1399 round 1, oral medicine **question 43**: “Which is NOT a neuropathic manifestation of Sjögren syndrome?” Final, immutable official answer **option 4: facial nerve paralysis**. Other options: glossodynia, oral burning, burning pain in extremities.
- Live source audit: this is the **only source-free oral-medicine question** among Q41–Q60. The other 19 have historical AI sources, mostly old `burket-oral-medicine@12e` or `little-falace-dental-management@9e` chapter metadata with citation anchors explicitly drawn from **unapproved newer** Burket13e/Little10e, so **do not silently attach an original-PDF page/hash to those legacy anchors**.
- 1399 official medicine validity: **Burket12e only chapters 4–7** (chapter 7 only until its printed page 161), and **chapter 10 except printed pages 224–232**; Little/Falace9e only chapters 2,3,4,12,14,16,17,24 dental-management themes. Citation Q43 is in original Burket12e **chapter 10, printed page 251**, well **outside excluded printed pages 224–232**.
- Approved original exact source PDF: `burket-oral-medicine@12e`, SHA256 `ebb1c0a98c24b21778f19e038127402a205b672ae6a31356eee722c27d199ab7`, canonical bound map chapter 10 original physical **PDF page 268**, original printed page **251**.
- The original page states patients with Sjögren may have *oral burning*, *glossodynia* and peripheral neuropathic *excruciating burning pain of extremities* and cranial trigeminal neuropathy; facial nerve paralysis is not listed as a neuropathic syndrome feature. Both `PrintedBookPageEvidence::pageContainsEvidence` for two original answer-specific fragments and `corroborates` for printed 251 using adjacent pages returned `QUOTE_OK PRINT_OK` on production. One-page direct `verified_reference_pdf.py --edition burket-oral-medicine@12e --require-map --page 268` read independently confirmed the same answer. Source metadata uses chapter **10** and physical **PDF page 268**.

## Durable private, immutable one-question study and provenance
Protected stage under
`/srv/fanoos/shared/research/classification/coordinator/W01-1399-oralmedicine-q43-20261012`.

The canonical `export_server_candidates.php --year=1399 --subject=oral-medicine` read the exact production source-free Q43 only, without importing or modifying any question/option/answer. Protected research-only study, decision and validated records:

| File | SHA256 |
|---|---|
| `bank-sittings/1399/oral-medicine-q43-study.json` | `218fb629353c3033ddaaffbe5057b6a64005bb6a41a54bea5fd0ca1e7bd954bc` |
| `classification/decisions/1399-oral-medicine.json` | `8d50b35a4cb5379c525cf78040c2d366dec8ea97dfc083b435bded11e70e3557` |
| `classification/sittings/1399-oral-medicine-q43-validated.json` | `34812f4fceac13d4a31731ba0de911d7634e06510adcd2208bffa7960ff13c3e` |
| `classification/reports/q43-audit-20261012.json` | `22fdae06b08e5487fa2226713c5041c1d21dd0e28ff8917d913f0319d3e58384` |

`apply_classification.py --pdf-pages` yielded **1 accepted, 0 rejected** from original approved year PDF. Independent `audit_private_study_batches.py --batch=1399:oral-medicine:oral-medicine-q43` yielded **one question, one accepted, zero pending**, exact original PDF and no bank-question/choice/answer changes.

Versioned SourceOnlyPublisher CLI gate is extended **only** for exact package name `W01-1399-oralmedicine-q43-20261012`, `year=1399`, `subject=oral-medicine`, `stem=oral-medicine-q43`, `expected=1`. No other stages or scopes are relaxed. Contract regression test checks these restrictions and full backup/provenance digest contracts.

**The code/document PR does not insert a source.** After green CI and authorized `main` deployment, operator must confirm latest live zero-source Q43 and exact year-scope source/hash, rerun rollback-only `import_verified_sources.php` from the **deployed release** with exact `--package-root`, **fresh verified full backup** for apply, unique protected receipt, independent source-node physical page/hash and question/answer immutable readback, then perform separately authorized full-sitting frozen assessment audit and append-only republish if content identical. Do not claim completion before actual DB receipt and live readback.

Other 1399 blockers: previous 32 guarded source row plans and 4 radiology insert candidates not yet deployed/applied, latest production strict count was 95/249 with 134 clinical pending; Q206 radiology official-key conflict, pediatric Q176/Q184 and oral pathology Q75 out-of-year-syllabus legacy chapters; historical anchors citing nearest editions cannot be relabeled as original-book evidence without formally reviewed provenance correction.
