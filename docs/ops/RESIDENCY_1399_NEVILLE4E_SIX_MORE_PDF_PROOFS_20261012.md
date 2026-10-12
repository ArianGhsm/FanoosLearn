# Residency 1399 — six original Neville 4e chapter+physical PDF page proofs (2026-10-12)

**Scientific original-book research; not applied to production source rows or the frozen exam.** Exact approved `neville-oral-pathology@4e` PDF SHA256 `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`. Live official 1399 oral pathology syllabus: all original chapters **except 4, 8, 18 and 19**.

Original 878-page PDF was directly authenticated and inspected in one transient, memory-only Poppler run (no text cache/index/file). The original physically located answer-bearing pages were subsequently read through the canonical `verified_reference_pdf.py --edition neville-oral-pathology@4e --require-map --page N`. **Every row below returned both `QUOTE_OK` and `LABEL_OK`** from the independent `PrintedBookPageEvidence::pageContainsEvidence` and `corroborates(..., true)` adjacent original-page labels, with exact PDF SHA and chapter map:

| Q | Original ch | Physical PDF p | Original printed p | Immutable official choice | Original-book evidence |
|---:|---:|---:|---:|---:|---|
| **61** | **10** | **392** | **385** | 2, stage II | Original TNM Table, **Stage II — T2 N0 M0**; 3 cm tumor without nodes/metastasis |
| **71** | **12** | **523** | **516** | 3, fibrosarcoma | Spindle-shaped cells in characteristic **herringbone** fascicular pattern |
| **72** | **12** | **482** | **475** | 3, giant cell fibroma | Numerous large, **stellate fibroblasts** in superficial connective tissue |
| **75** | **14** | **604** | **597** | 4, periodic observation | **Periapical cemento-osseous dysplasia**, asymptomatic and vital anterior mandibular teeth |
| **76** | **15** | **649** | **642** | 3, parakeratinized epithelium | Nevoid basal cell carcinoma syndrome with multiple odontogenic keratocysts; histopathologic source says cysts are invariably OKCs; review exact parakeratin lining if adding direct quote |
| **77** | **15** | **660** | **653** | 1, granular cell odontogenic tumor | Original chapter taxonomy puts **granular cell odontogenic tumor** under *tumors of odontogenic ectomesenchyme* |

**Priority correction Q75:** historical AI source `neville-oral-pathology@4e#ch19` violates the 1399 source syllabus since original Neville chapter19 is excluded. Official original evidence lies in **ch14, physical PDF604/printed597**. Never merely fill a PDF page on the invalid ch19 node. Correct BOTH chapter AND PDF page only in a reviewed source-replacement workflow preserving all immutable question text, options, official answer, human decisions and attempts.

The previously independently verified four original Neville4e pages for Q66 ch3 PDF141 print134, Q68 ch1 PDF20 print13, Q69 ch11 PDF467 print460 and Q79 ch17 PDF795 print788 are in `docs/ops/RESIDENCY_1399_NEVILLE4E_FOUR_ORIGINAL_PDF_PROOFS_20261012.md`. Together these make **10 original-book page research proofs**, but their stored historical anchors cite *Neville5e nearest edition*. Do **not** attach a 4e SHA to unreconciled 5e anchor text, or count any of these research cases as source-complete.

## Distinct holds and safety

- Q63: original Neville4e ch6 around physical PDF220 calls **cutaneous leishmaniasis** by far the most common clinical form; stored official key chooses **visceral** leishmaniasis (choice 3). **Apparent scientific/official-key conflict**: verify complete original page and official key before any source publication; never change the official answer automatically.
- Q62 spindle-cell carcinoma p398, Q65 resorption p66, Q67 cleft/taurodontism, Q70 oncocytoma, Q73 Hodgkin stage, Q74 osteosarcoma, Q78 bullous pemphigoid and Q80 CMV remain at original-page candidate stage pending sufficient correct-choice book passage corroboration. Do not count as approved from a search score alone.
- Source-only operations must use exact original 1399 edition+chapter+physical page and PDF SHA together, leave protected original answer/human/attempts unchanged, use strict CAS/provenance audit with fresh verified full backup, authorized production deploy, independent production source readback and separately audited append-only frozen assessment publication. **No production DB mutation performed by this research or GitHub docs merge.**

Live strict 1399 bank completion at last read remained **95/249** source rows (154 incomplete including 20 exempt English, 134 clinical pending). Previously **37 source-only candidates** (32 guarded existing-row page previews, four new radiology source rows, one new oral-medicine Q43 source) were approved as **read-only preflights**, not committed. Do not inflate the actual count.
