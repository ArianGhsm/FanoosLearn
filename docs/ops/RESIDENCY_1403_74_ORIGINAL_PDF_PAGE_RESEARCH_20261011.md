# Residency 1403 — original PDF chapter + exact page; independent research checkpoint (2026-10-11)

## Immutable bank and classification guardrails

The strictly read-only canonical export for `residency / 1403 / round 1` is `/srv/fanoos/shared/research/bank-sittings/1403/canonical-export-20261011/residency-1403-1.json`, SHA-256 `5a903b491496ed3b7f912dde0fdc37607ff102f33b36a9c5e723baab19a1fcc7`. It contains 250 unchanged stems/options/official answers; 20 final-key English questions are exempt from book-citation scope. A fresh LIVE read-only DB query found **71** questions with `node_id`, `pdf_page`, `pdf_sha256` together on a source row; **159** clinical questions are still formally incomplete in the bank (includes old citations with only *printed* pages). This is a **bank status**, NOT a tally of scientific research decisions.

The research-only study documents use `fanoos.classification.study-only/1`, deliberately **not importable as a bank sitting**. Decisions were validated against original, server-held, exact-edition PDF SHA-256, physical PDF page, and official 1403 syllabus scope, then independently checked by `scripts/references/audit_private_study_batches.py`. The audit's `accepted` status checks document/source identity and page evidence, but does not by itself authorize changing pre-existing human-reviewed or AI source rows, editing old anchor text or publishing. Two audit roots and receipts are kept on the protected server; no publisher or deployment was run on this 1403 campaign in this checkpoint.

## 74 new source/chapter/PDF-page research decisions in this checkpoint

| Exact 1403 reference/subject | Audited accepted | Held / reason | Independent private report |
|---|---:|---|---|
| White–Pharoah Radiology 8e, oral-radiology | **9 / 9** | 0 | `1403-radiology-nine-independent-page-audit.json`; SHA `fded7361c03d9d8735911d1e0f2956070e91722e9d9b7b2fe4253cee21d4c337` |
| Hupp Oral Surgery 7e / Malamed LA 7e, oral-surgery | **19 / 20** | Q131 **already had physical PDF-page + source SHA in the bank**, not rewritten/reclassified in this research batch | `1403-oral-surgery-independent-original-pdf-pages-20261011.json`; SHA `be11d2b4bae434e665046fb9f94b3c63efed4392a8e366fe849fa086499a3c4b` |
| Craig Dental Materials 14e, dental-materials | **8 / 10** | Q218 and Q219: old Craig chapter labels **not within official 1403 scope**; do not attach an out-of-scope page | `1403-dental-materials-independent-original-pdf-pages-20261011.json`; SHA `8597a5bd2151274e93cb19aaf923e0e4a1653584a144cb7f8edeaccb16064df3` |
| Neville Oral Pathology **4e**, oral-pathology | **20 / 20** | 0 | `1403-oral-pathology-independent-original-pdf-pages-20261011.json`; SHA `faff8eaceff262d62f330822180da84851ac0b88f54718617930537bb4285c44` |
| Carranza Periodontology **13e**, periodontics | **18 / 20** | Q119 MRONJ and Q127 artificial-valve oral irrigation: no sufficiently specific, answer-compatible, **official-scope** original-page evidence located | `1403-periodontics-independent-original-pdf-pages-20261011.json`; SHA `d0912fd125befcf319b25d4b9ac78d952364681ef805600968c0b6a35a66ad5d` |
| **New independently audited research-only total** | **74** | **4 held / 1 already recorded** | All source receipts privately protected |

The **prior separate** Torabinejad Endodontics 6e research accepted **19/20** Q21–40 (Q34 held) and was independently verified under the earlier 1403 checkpoint. Do not double-count that earlier batch among these 74.

### Accepted original PDF page pairs

**Radiology**, White–Pharoah8e: Q192 ch1/p70, Q193 ch3/p136, Q196 ch6/p302, Q197 ch15/p754, Q203 ch12/p606, Q205 ch22/p1084, Q207 ch25/p1344, Q208 ch26/p1446, Q210 ch28/p1568. Q208 official Multiple Myeloma option is supported on original p1446: relative dental opacity against osteopenic background.

**Oral surgery**: Q132 ch4/p60; 133 ch23/p503; 134 ch13/p237; 135 ch21/p440; 136 ch27/p608; 137 ch16/p334; 138 ch25/p537; 139 ch11/p209; 140 ch11/p204; 141 ch20/p429; 142 ch28/p630; 143 ch2/p40; 144 ch7/p104; 145 ch1/p25; Q146 **Malamed7e** ch4/p93; Q147 **Malamed7e** ch3/p66; Q148 Hupp7e ch11/p212; 149 Hupp7e ch31/p669; 150 Hupp7e ch29/p642.

**Dental materials**, Craig14e: Q211 ch4/p48; 212 ch4/p46; 213 ch5/p90; 214 ch10/p190; 215 ch12/p253; 216 ch11/p228; 217 ch11/p226; 220 ch8/p141.

**Pathology**, Neville4e: Q41 ch3/p134; 42 ch5/p183; 43 ch6/p202; 44 ch7/p238; 45 ch9/p318; 46 ch10/p393; 47 ch10/p369; 48 ch11/p459; 49 ch11/p462; 50 ch12/p501; 51 ch12/p529; 52 ch13/p568; 53 ch13/p561; 54 ch14/p601; 55 ch14/p628; 56 ch15/p652; 57 ch15/p645; 58 ch15/p664; 59 ch16/p725; 60 ch16/p746. The old legacy pathology source cited the **nearest, wrong 5th edition**, so none of its printed-page labels are accepted as physical page proof. Original 4e is actually verified on server and independently studied. Example Q57 original p645 directly confirms cartilage in an odontogenic keratocyst wall.

**Periodontics**, Carranza13e: Q111 ch60/p1364; 112 ch62/p1390; 113 ch69/p1558; 114 ch72/p1611; 115 ch5/p130; 116 ch32/p846; 117 ch32/p859; 118 ch3/p64; 120 ch17/p616; 121 ch12/p510; 122 ch15/p587; 123 ch50/p1144; 124 ch52/p1196; 125 ch59/p1334; 126 ch72/p1603; 128 ch50/p1127; 129 ch18/p626; 130 ch70/p1570. Notably Q117's stored old ch45 was false; official original citation lies in ch32. Q124 original p1196 supports the difference between doxycycline and other tetracyclines with calcium/antacids. Old Carranza14e nearest quotes **never substitute** for official 13e.

### Original private artifact custody

Radiology root:
`/srv/fanoos/shared/research/tmp/1403-radiology-page-audit-20261011/audit-root`.

Other four subjects root:
`/srv/fanoos/shared/research/tmp/1403-source-page-expansion-20261011/audit-root`.

The study subsets are under `bank-sittings/1403/`, decisions under `classification/decisions/`, primary validated research-only outputs under `classification/sittings/`, and independent receipts under `classification/reports/`. The source search and subset-generation scripts live one level above that second audit root: `scan_legacy_anchor_original_fast.py`, `make_surgery_materials_studies.py`, `scan_pathology_periodontics_original_fast.py`, `scan_problematic_questions_original.py`, `make_pathology_periodontics_studies.py`. Search scripts use SHA-bound original server PDFs, keep text in-memory, record only small candidate snippets (no PDF corpus copied) and never modify the database.

Verified research-only recovery archive (not a production DB backup):
`/var/backups/fanoos/research/20261011-residency1403-74-independent-research-audits.tar.gz`, SHA-256 `f6b21cac536f5bb8e5b1180115a4b75a4a7161d9913edebb73289617da469ff5`. `tar -tzf` passed after creation. It contains both privately staged study/audit roots but no original copyrighted book PDFs.

### Production gates and remaining backlog

At checkpoint time, Git updater checkout `main` at `a4b447d` and site health OK at release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`; deployed release lags Git HEAD. Recheck **actual** Git, site and DB immediately before future work.

**No production bank source was inserted, repaired or released in this audit campaign.** The existing-source repair operator from PR #305 is **hard coded for residency 1399** and cannot be reused on 1403. Source-only import scripts for new source rows cannot update the 1403 rows with existing AI/legacy metadata. Production work needs a 1403-specific approved, constrained original-PDF source-metadata repair (source/official edition/chapter/PDF page/page SHA only), rigorous handling of mismatched legacy `anchor_text`, optimistic snapshots of immutable question/option/final answer/human review, exclusive lock, pinned original PDF SHA, a fresh independently verified **full** production backup and restore path, green CI + matching deployed code, preflight of the full frozen sitting, audited two-phase prepare/apply, DB readback, optional frozen assessment reviewed republication, and post-publish immutable-diff/health checks. **Do not hand-edit production SQL or overwrite any human-reviewed source.**

Other 1403 subjects (oral medicine, pediatric dentistry, prosthodontics, one operative-dentistry record) and six held clinical questions (Q34,119,127,218,219 plus others outside these new batches) still require original-scope chapter+page scientific evaluation. The official PDF chapter maps for McDonald11e, McCracken13e, Shillingburg4e, Phillips13e and Goldstein3e lacked `source_pdf_sha256` binding at earlier checkpoint; revalidate and repair maps only after direct original-book chapter-boundary checks. Never infer physical PDF pages from old printed page numbers or substitute nearest edition.

**Distinguish research accepted from live DB completed** until an independently verified production readback proves otherwise.
