# Dentistry residency — endodontics exact-edition classification (2026-10-10)

**Scope:** `residency`, subject `endodontics` only, sitting years 1398–1405. This document is a public, sanitized checkpoint. Private question text, official answers, verbatim evidence, source PDFs and classification decisions stay on the server. The controlling policies are `docs/PROJECT_PRINCIPLES.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md` and `docs/ops/RESIDENCY_PARALLEL_WORKSTREAMS.md`.

## Confirmed read-only production inventory

On 2026-10-10, the authorized operator queried live `bank_questions` scoped by the dentistry residency sitting and the canonical `endodontics` subject, using a `NOT EXISTS(bank_question_sources)` test (no database writes). A separate join against `bank_question_sources` checked missing `node_id` and `page` metadata.

| Exam year | Questions | With source | Without source | Exact official edition |
|---|---:|---:|---:|---|
| 1398 | 19 | 0 | 19 | `torabinejad-endodontics@5e` |
| 1399 | 20 | 20 | 0 | `torabinejad-endodontics@5e` |
| 1400 | 20 | 19 | 1 | `torabinejad-endodontics@6e` |
| 1401 | 20 | 20 | 0 | `torabinejad-endodontics@6e` |
| 1402 | 20 | 20 | 0 | `torabinejad-endodontics@6e` |
| 1403 | 20 | 20 | 0 | `torabinejad-endodontics@6e` |
| 1404 | 20 | 20 | 0 | `torabinejad-endodontics@6e` |
| 1405 | 20 | 20 | 0 | `torabinejad-endodontics@6e` |
| **Total** | **159** | **139** | **20** | |

All **139 existing endodontic source rows** have a non-null chapter node; **two human-origin rows (1404 Q27, Q34) have NULL page labels**. These human decisions must remain untouched unless independent, authorized review supplies exact book/page evidence. The other 137 sourced rows have a page label. **Presence of a node/page is not the same as independently validating the cited passage or edition; no blanket accuracy claim is made for legacy mappings.**

## Exact-book private research already available

Previous W03 research handoff (not a new publication permission):
`/srv/fanoos/shared/research/classification/parallel/W03/HANDOFF_CHAT9.md`,
`reports/audit-report.json`, original book page-marked texts, official-edition decisions and accepted study-only outputs.

- **1398**, Torabinejad 5e: 9 primary-validator-accepted **research** decisions — question → chapter → PDF page:
  Q1 → ch3 p53; Q2 → ch1 p30; Q3 → ch4 p73; Q4 → ch4 p76; Q5 → ch5 p89; Q6 → ch5 p102; Q7 → ch7 p128; Q8 → ch9 p161; Q12 → ch6 p113.
  The other **10** source-free 1398 questions are Q9, Q10, Q11, Q13, Q14, Q15, Q16, Q17, Q18, Q19, all still *pending exact-evidence/content review*. Their apparent chapter leads in book search output are **not** accepted decisions. Torabinejad 5e has 494 fully extracted PDF pages and canonical chapter runs ch1–25 over PDF p3–494; PDF front matter p1–2 is omitted from the canonical map. The coordinator must independently decide whether/how to resolve this map completeness defect before production publication.
- **1399** Q29, Torabinejad 5e, ch18 PDF p335: the independent coordinator previously validated and **published** this source in production after a rollback-only preview and verified 54-file full backup. Actual import receipt: `/srv/fanoos/shared/research/classification/reports/1399-endodontics-W03-source-publish-20261010.json`; 1399 assessment v6→v7; no original stem/choice/answer changes. **Never import this row again.**
- **1400** Q29, Torabinejad 6e, ch9 PDF p170 (book-printed p163): passed W03's primary research validator, but remains **source-free in the live database**. The independently reported printed/PDF page-label disagreement requires its own original-book corroboration and publication-writer approval; do not alter a page label to force import.

The chapter/page leads above are *PDF page indices* and deliberately distinct from a book's printed page number. Original evidence, source SHA256 receipts and question/answer identity hashes remain private, not reproduced here. W03's preliminary report bundled periodontics; this checkpoint and all continued research here are **endodontics-only**.

## Publication and parallel-worker safeguards

1. The 20 production-unsourced endodontics questions remain unsourced until a coordinator-authorized atomic source-only import is independently verified. Research acceptance is **not** database completion.
2. Never reapply 1399 Q29 or a question already acquiring a source from any other chat. Requery live unsourced status, edition validity and pre-existing human sources immediately before every staged import.
3. Independently verify exact approved PDF SHA, complete page-marked book text, chapter-page mapping including printed/PDF translation, question and recorded-key consistency, the original unchanged `apply_classification.py` validator and a second evidence/page audit. Any answer conflict or figure dependence stays `pending`.
4. Worker-owned, SHA-pinned private packages stay separate from the coordinator. **No worker mutates** shared catalog/page maps, production DB, previously published assessments, another worker's artifacts, or deployed release. The 1398 page-map fix is coordinator-owned and must follow a reviewed GitHub PR.
5. For an actual production change: fresh site-matching study export and content hash comparison, `questions_changed=choices_changed=answers_changed=0` transactional preview, verified complete production backup, unique importer receipt, live DB readback, full frozen-assessment diff, authorized publish, and only if code/docs have changed: green CI, merge, updater deployment and live health/sync verification. No step is deemed done from historical W03 claims alone.
6. Continue searching undecided 1398 Q9–Q19 (excluding accepted Q12), but **do not infer chapters merely from subject headings** or rewrite official answers. Independently audit pre-existing 1399–1405 page evidence where possible; give human rows explicit protection.

## Current verification and handoff

- **Verified this chat:** production readonly counts (159 total, 139 sourced, 20 unsourced) and existing source metadata (1404 Q27 and Q34 missing pages). Official per-year edition validity and all-chapters scope confirmed from the current versioned catalog. Source-file inventory and historical W03 handoff verified in the protected server workspace.
- **Historical, not re-executed this chat:** primary-validated 1398 nine decisions and 1400 Q29; 1399 Q29 import/publish receipt and full frozen assessment.
- **Not performed in this chat:** new original-book evidence acceptance, source-only production import, new assessment version, CI validation, merge, updater deployment. A documentation PR has no effect on bank sources.
- **Next safe step:** create a separate immutable **endodontics-only** protected research package for the remaining ten 1398 questions, with original-book evidence and answer-key reconciliation. Ask the integration coordinator to audit the Torabinejad5e page-map gap and 1400 printed/PDF discrepancy before any production publication. Record all new exact hashes and fresh database counts in a new checkpoint, without modifying historical W03 files.

The primary source of truth for production data remains the live FANOOS database and its audited private receipts; this document is a dated read-only audit, not a statement that outstanding chapters were completed.


## 2026-10-10 exact-edition second-stage evidence audit

The coordinator created **private staged copies** from the immutable W03 1398 research, retaining the original nine-decision package unchanged under `classification/coordinator/W03-audit-stage/classification/archive-1398-original-W03-20261010/` (original SHA256 decisions `8efd4f16aabe1cdd94a61e7e045a14fa5e12f9a70e7fa8681a702437622652a5`, validated `21ddc97a75a2225b8b89f68abf82bbce0e2d8f267955ca3fb4c939aadc451e1c`). Nothing was edited in the worker's original W03 research.

The unchanged primary validator and the stricter independent page-neighbor audit agree on exactly **eight** source links for 1398: Q2–Q8 and Q12, all Torabinejad 5e. Q1 previously had a 5e ch3 PDF p53, printed p41 lead, but the independent **two-neighbor printed-page check fails**; Q1 is *held*, even though the original primary validator passed it. The remaining ten 1398 questions (Q9–Q11, Q13–Q19) are also held pending exact book/key/figure review.

Private coordinator stage: `/srv/fanoos/shared/research/classification/coordinator/W03-audit-stage/`.
- 1398 stage decisions (eight only): SHA256 `550372c0ce33a2fec231a38aa68af40da60e7abb15394675704d8a69070ea936`.
- 1398 stage primary-validated file: SHA256 `5c97f090fa92c1658fbb2b256ae3546af4fb9a793ba27dbe18e3a39c00b4dd5c`.
- Independent original-book audit `classification/reports/W03-1398-8sources-second-audited-20261010.json`, SHA256 `9a54569e697042876ab32f6c470c184e351a9982762e433af78be1ad2e713bea`. It reports 19 original questions, eight mapped, 11 pending, unchanged question/choice/answer content.
- 1400 Q29 original book printed p163 / PDF p170, 6e ch9, independently passed the original-book two-neighbor audit `classification/reports/W03-1400-second-audited-20261010.json`, SHA256 `8bbbb1a7610b9ab1295e1be84ca5d62aa15e88d64ee7e3cce55f192904174950`. The unmodified staged validated and original decision SHAs match the W03 handoff.

The 1398 official catalog identifies `torabinejad-endodontics@5e` and all chapters for **endodontics**. The proposed source-only 1398 exception in this PR is intentionally *subject-restricted*: other 1398 disciplines remain rejected. It still requires matching original study, exact officially valid edition/chapter scope, primary and independent evidence review, a fresh live source-free check, verified full backup, source-only transactional preview, unique receipt and independently audited assessment publication. The older 1398 W03 nine-decision package is **not** publication-ready.

**State at this checkpoint:** these are research acceptance and a proposed code eligibility change only. Neither eight 1398 links nor 1400 Q29 were imported or published at the time this report was written. The fact that the newer code supports independently proven printed-book labels does not authorize bypassing the older deployed source writer, nor changing a label to make a preview pass.
