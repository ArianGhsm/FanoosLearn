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


## Latest verified production checkpoint — 2026-10-10 after second-stage publication

**This section supersedes the historical research-only status and preliminary question list above.** Historical W03 submissions and earlier eight-question search stages are preserved for reproducibility, but **Q12 is excluded by manual answer review and Q19 replaces it in the approved eight-question publication**.

### Real database inserts and complete frozen exam publication

| Year | Proven source-only inserts | Independently verified backup | Frozen assessment | Audit |
|---|---|---|---|---|
| 1400 | Q29, Torabinejad6e ch9 PDF p170 / printed p163 — **applied** 2026-10-10 09:55:46 UTC | `20261010T095118Z-d2cab314`, complete 54-file manifest | v6→v7, 249 questions identical except one source explanation | original-book audit SHA256 `8bbbb1a7610b9ab1295e1be84ca5d62aa15e88d64ee7e3cce55f192904174950` |
| 1398 | Q2, Q3, Q4, Q5, Q6, Q7, Q8, Q19, Torabinejad5e — **applied** 2026-10-10 10:01:51 UTC | `20261010T095909Z-0e187637`, complete 54-file manifest | v3→v4, 245 questions identical except eight source explanations | stricter original-book audit SHA256 `337d964cff414077364fa3adc82b8ea1bf7d2946e289b04eb5fa5f7a471cbe98` |

Official source-only importer independently returned exactly one and eight new rows respectively, with `questions_changed=choices_changed=answers_changed=0`. Unique private production receipts (verified readbacks):

- `/srv/fanoos/shared/research/classification/reports/1400-endodontics-W03-source-publish-20261010.json`, SHA256 `4e0cd8f384ffee3cab0550f6d3c60eb6001e35a4aebdae9d038bacc8e900cf7b`.
- `/srv/fanoos/shared/research/classification/reports/1398-endodontics-W03-semantic-source-publish-20261010.json`, SHA256 `eccec5dd7273e6e08dd03543913f1855d27ed2318c07d65d85fdc2d8d76ef72f`.

After the two batches, independent live SQL readback showed **148 of 159 endodontics questions** carrying source rows, with **11 unsourced**, all in 1398; year 1400 and each 1399–1405 are 20/20 sourced. Two of 1404's human-origin source rows, Q27 ch12 and Q34 ch13, still lack a book page and must not be marked page-complete.

### Manual semantic exclusions and new evidence

- Earlier research accepted 1398 Q12 (5e ch6 p113); **human semantic review held it**: the official recorded choice calls radix entomolaris a mesial extra canal while the book defines an additional molar *root*. Original key unchanged.
- Q19 was independently accepted in the replacement eight-question package, 5e ch11 PDF p201 (supporting preceding original page p200), recorded splint-duration answer consistent with the book. Original W03 evidence, both coordinator-stage predecessors, and their checksums remain preserved, never overwritten.
- Q1 printed book p41/PDF p53 mismatch did not pass the independent two-neighbor page auditor; hold pending proof. Q9–Q11, Q13–Q18 similarly remain uninserted unless newly audited separately. Q14, Q15, Q17, Q18 and Q12 require answer-key integrity review; **do not edit official keys while assigning sources**.

### Additional independently approved but not imported: 1398 Q9

The original exact Torabinejad5e text explicitly supports the recorded mesial tube shift together with decreased vertical angulation. Single-question private research package `1398:endodontics:endodontics-q9`, original-book ch12 **PDF p216**. Both unchanged primary validator and independent book audit accepted this one question, `0` source/content rejects. Private stage:
- `bank-sittings/1398/endodontics-q9-study.json`, SHA256 `0731cf447d0ac46d9dcfaff015e49edae63f24ab466d0f38bc36f195125b72e7`
- `classification/decisions/1398-endodontics.json`, SHA256 `e64eaec1bfd4a3d0296780f986381a3ea51efa386a9bd19bf7950a360f83c9f3`
- `classification/sittings/1398-endodontics-q9-validated.json`, SHA256 `c382dc37cc0b20250821cae44daad31f9a6ae2bd810e2350cb815d7ada52c07a`
- `classification/reports/1398-endodontics-Q9-independent-audit-20261010.json`, SHA256 `2e76698ee93bfdb84450a9fe1dc7adcd8b35eae846fc13316b2916ea9b942a9a`

The official publisher initially rejected `stem=endodontics-q9` (no production side effect). Scoped support was added with regression tests and merged **PR #214**, merge SHA `0b32d7741f502b34043e0d360d6cb86c3a9de271`, all five PR/main CI jobs green. A separate official updater request `01a1254a-4562-77f9-94a9-13de8c05d345` FAILED preflight at 2026-10-10 10:10:49 UTC because the canonical main workflow was not yet green *at that instant*. The previously healthy deployed release remained `051c3b00d783dd7e26c68635e258c79078f19fc0`. GitHub subsequently confirmed the main workflow green, but **Q9 has not been transaction-previewed on the new deployed code, imported, or published**. Do not represent it as done or repeat a failed deployment request blindly.

**Q9 next gates:** independently confirm exact currently live canonical main and green SHA, successful authorized updater deploy with backup and site sync, fresh 1398 unsourced study/source-original identity, frozen full-assessment preview, unique full verified backup, `--expected=1` source-only preview/apply with above immutable package and unique receipt, live source count, exam v4→v5 publish and full 245-question frozen diff.

### Other unresolved data and constraints

Remaining 1398 source-free numbers as of readback: **Q1, Q9, Q10, Q11, Q12, Q13, Q14, Q15, Q16, Q17, Q18**. Q10 and similar diagram-dependent questions require actual reference figures. Two 1404 **human-origin** sources (Q27, Q34) have correct existing chapter nodes but **NULL page** and no existing source quote to ground a page. Keep the human origin/review provenance unchanged; only after a page-specific book/answer proof and separately audited metadata-only operator may these be repaired. The existence of a candidate chapter or nearby original page does not prove the precise page.

**Status:** verified source-only live publication of nine net new endodontics assignments across 1398/1400, plus one newly independently validated Q9 research proposal **not published**, and 10 other pending unsourced 1398 questions. These remaining scientific and production evidence gates prevent labeling the entire subject complete. All decisions/receipts/book content are protected on server, not in public GitHub.


## Updated verified endodontics checkpoint — 2026-10-10 10:55 UTC

This section **supersedes all earlier in-progress counts and Q9 hold statements above**.

- The independently reviewed 1398 Q9 was **actually inserted and published**. Protected receipt `classification/reports/1398-endodontics-Q9-source-publish-20261010.json`, SHA256 `fd4339c68aa813dd7d3154f6ac4b170255099970f87fe72f23499949269f7b87`, backup `20261010T103625Z-2c8ae452` independently verified (54 files); 2026-10-10 10:42:07 UTC. Q9 exact official Torabinejad 5e ch12 PDF p216; source-only insert returned precisely one new row and zero changes to questions, options, keys. The student assessment moved v5→v6; full 245-question frozen diff `question_content_identical=true`, `updated_explanations=1`. After this change, read-only production sources show **149/159** endodontics questions linked (1398 9/19; 1399–1405 each 20/20).
- 1398 Q1 was separately validated using original exact 5e ch3 **PDF p53**, supported by the book's enumeration of ecological factors. The primary research validator had initially emitted printed p41; that private unmodified output was archived, and only the validated research **source page label** was explicitly corrected to `pdf 53`, not the question or key. The independent original-book auditor accepted one of one, no unanswered/altered questions; report at `classification/coordinator/W03-endo-q1-audit-stage/classification/reports/W03-1398-Q1-exact-pdf-audit-20261010.json`, SHA256 `573b159132bdd55e6ea96f4e1b2e3fa85333aae3d4e5451b283bce18293b9005`. Private study SHA256 `281235a9299b302d61bfbe3049fc865a5d1f32c579a656448930ea30fb3d5505`, decisions SHA256 `ebd16c781ffeee6dc9db4d727a005bfc5a44fd1e6ea35961880dc9d0eaea1e41`, validated source SHA256 `5eb6e277f4c44472f965f310e7e62ae2bb4cd2aeff8b06df36509b80c44cba51`. PR #223 merged as `9aba5eef0fc4a2ff786e0e21c1f7ba9f5d80b52c`; all five PR CI jobs green and official source-only rollback preview accepted one source. **Q1 was not yet applied or published at the time of this checkpoint** because deployment attempts `01a12571-8d08-7275-9ea3-ab691b37057e` and `01a12573-37ea-75c3-8853-ce8dfc1cf707` both failed the canonical-main CI requirement as other parallel work advanced main. Never bypass this gate.
- Nine remaining 1398 questions, **Q10–Q18**, have protected independent scientific answer-consistency dispositions at `/srv/fanoos/shared/research/ops/1398-endodontics-answer-consistency-holds-20261010.json` (SHA256 `3d258373118f8146fd03f7fd8db98acf6280cecb7d59202557f669e484283012`). Exact 5e figure 13.4 on PDF p233 was visually inspected in private for Q10; other cases reference book chapter text and keyed answers. Q12 also lacks the exact name in the supplied 5e text. **No source-only insert is approved for these nine until independent official-key adjudication**; do not replace a proven book answer with a mismatched answer citation or modify the official stored answer.
- The 1404 existing **human** source rows Q27 and Q34 remain chapter-sourced but have NULL page and no verified source quote. They must not be silently overwritten.
- Publish closure is thus **not yet achieved** despite the successful Q9 publication. Complete only upon independently verified live Q1 insert, assessment version bump and unchanged 245-item post-audit, plus scientific adjudication of all nine held 1398 items and proof of both 1404 human pages.
