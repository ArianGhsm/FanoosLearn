> **PDF workflow correction — 2026-10-10:** Earlier runs temporarily generated page-marked text from approved PDFs. Cleanup removed the live derivatives and the discovered W08 archive copy; see the private receipts in `PDF_REFERENCE_SOURCE_POLICY_20261010.md`. Text hashes and paths below are history only. Current and future checks read one exact approved PDF page at a time and discard it after checking.

> **Latest live revision 2026-10-10 18:25 UTC** — **160 residency periodontics questions; 144 source rows; 16 source-free; exactly 15 published sources with independently validated original-edition printed page and official answer** (4 in Carranza12e 1398, 11 in Carranza13e 1399). The other **129** source rows must NOT be marked exact-page final. This section supersedes prior 10/160 and 6/1399 progress statistics elsewhere in this historical audit.

## Second five exact Carranza 13e page-only production corrections (1399)

The following **additional** five cases were verified directly against their original 1,991-page approved Carranza 13e and each printed-page label independently corroborated from neighboring original pages.

| Question 1399 | Original chapter unchanged | Original PDF page | Corrected printed page | Original answer-defining evidence |
|---|---:|---:|---:|---|
| Q115 | 12 | 506 | 182 | Smoking: decreased bleeding on probing; increased attachment loss |
| Q118 | 33 | 882 | 408 | CBCT better visualizes fenestrations than 2D/digital intraoral images |
| Q122 | 72 | 1611 | 721 | Merin class C, medically deferred periodontal surgery, 1–3-month recall |
| Q123 | 52 | 1196 | 559 | Metronidazole inhibits warfarin metabolism and prolongs prothrombin time |
| Q125 | 3 | 96 | 46 | Transseptal and alveolar-crest fibers form as the tooth emerges |

- Original approved PDF SHA-256 `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`. Its former page-text derivative is removed; cleanup provenance is in the protected receipt.
- Original protected full live-question/choices/answer/source snapshot SHA-256 `5a3eb3c985e6a0f5d4a4d27da45c5a0c3f6f04fe2b24df7628f2ae5ad3e3e781`. Narrow source-page-only operator/tests/docs: [PR #268](https://github.com/ArianGhsm/FanoosLearn/pull/268) merged commit `6d7c9b7b4478ac320c4c4e6ccbe0a882d607a073`. Both branch CI and canonical main `1347f3c371b5826833def67e564922fefe0c121f` CI green.
- First deployment request was refused by the canonical SHA CI guard after another workstream advanced `main`; no writes. Retried only after green CI: official deployment request `01a1270b-df57-723e-9d67-aa9f7606e758` **SUCCEEDED**, live site health matched `1347f3c...`, full before-change backup `/var/backups/fanoos/20261010T182345Z-1ff944bb` passed verification of **54 files**.
- Production page-only atomic apply receipt in protected `classification/reports/periodontics-1399-next5-exact13e-20261010/second-five-source-pages-apply-20261010.json`, SHA-256 `ca73f81de9d20491c4ff5d2e7d4a8c294a076dd81cb85245e557f38a8b795d64`. **Independent all-20-source-row before/after check** proved exactly five `bank_question_sources.page` transitions from NULL to these printed pages, with 15 other sources, edition, node, historical anchor, AI/human review, confidence values and question stem hashes identical. Private postdiff SHA-256 `113fe6d48590b85ede26907decffb4ff9ee687975c292d75dde7f30ed0e4dde8`. Frozen whole-year 1399 published assessment remained **version 8** and full definition SHA-256 `d36cc9097b8d096004128a967c5e15e279973927f2ea47b020c5876205158b85`. No choices, answers, human decisions or attempts were changed.

### Current machine-verifiable 160-item disposition (v4)

Protected `/srv/fanoos/shared/research/classification/reports/periodontics-completion-ledger-v4-20261010.json`, SHA-256 `7823cdc96e12001d563d85173f7e668f89e098a7bc701524c27d29fb2d3d84b4`.

| Status | Count |
|---|---:|
| Original 12e / 1398 print-page-validated and published | 4 |
| Original 13e / 1399 print-page-validated and published | 11 |
| 1398 source-free with original-book/official-key scientific holds | 16 |
| 1399–1403 older 13e source rows with no verified page and within chapter scope | 84 |
| 1399–1403 13e source rows with NULL page and chapter outside year-specific official scope | 5 |
| 1404–1405 old 14e sources with placeholder-like page labels; full original 14e absent | 40 |
| **Total** | **160** |

**Scientific closure remains blocked:** 16 source-free 12e cases include clinically incompatible or malformed official options; 89 existing 13e sources still need answer-specific primary-book verification (including five out-of-official-scope chapter links); 40 14e existing page labels are unvalidated because the registered source is merely 105 supplementary PDF pages rather than the complete official edition. Do not change question content or official answers, treat existing legacy source metadata as approved, or infer textbook pages without the correct edition. Prior sections record historically true earlier milestones rather than current production counts.

---

> **Latest verified checkpoint — 2026-10-10 15:42 UTC.** The prior 12:18 UTC counts remain accurate for total/source presence, but the classification below supersedes its Q119 conflict. **160 questions; 144 source rows; 16 unsourced; 10 validated original-edition printed pages published.** The other 134 source rows are not exact-page verified.

## Final production Q119 correction and full 160-question ledger v3

**1399 Q119 (Bass / targeted oral hygiene):** Official Carranza **13e**, **chapter 47**, original PDF **page 1073**, book-printed **page 507**. This was a real historical source metadata error: chapter **48 → 47**, page **NULL → 507**, with answer position 1 (Bass) left intact. Exact clinical wording and neighboring printed original page labels were independently corroborated. **Not an estimated match.**

- Original immutable Q119 source/official key snapshot SHA-256 `e46bb494b6cd52f9e94bab4e88c5c44292146f5bc740dc5c0cb842314cd91e7c`; registered original PDF SHA-256 `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`. A former page-marked extraction was generated during the historical operation and later removed. The current correction code rechecks the exact PDF page directly; do not recreate a text file.
- Guarded implementation [PR #242](https://github.com/ArianGhsm/FanoosLearn/pull/242), option-array serialization-safe correction [PR #258](https://github.com/ArianGhsm/FanoosLearn/pull/258), squash SHA `11fe481ffcae19268c6b4af3464860c3e844df0a`. All five PR CI jobs and canonical main CI successful (run `38064074938`). Official updater request `01a12675-bde2-7af3-a27f-9c4d958db30e` **SUCCEEDED**, and live health release matched `11fe481ffcae19268c6b4af3464860c3e844df0a`. Verified complete pre-change backup `/var/backups/fanoos/20261010T154006Z-a2f1d052`, 54 files.
- Live preview accepted **exactly one** unreviewed AI-origin source, with zero mutations. Atomic apply receipt under protected `classification/reports/periodontics-13e-chapter47-correction-20261010/q119-chapter47-page507-apply-20261010.json`, SHA-256 `8790adbb21c04181250916c21c0414d98512e802a658106f825fc7b887bc53af`. Independent **20/20 year-1399 source-row** before/after comparison found **19 wholly unchanged and only the Q119 node and page modified**; existing anchor, edition, source ID, human origin/review and question metadata were untouched. Protected post-apply report SHA-256 `505d14035eb381ff1ff582f0642551d2505920ee8fed015b8e6b2d423173bc20`. The full published 1399 assessment remains version **8** with identical definition SHA-256 `d36cc9097b8d096004128a967c5e15e279973927f2ea47b020c5876205158b85`. No answer or student-attempt history changed.
- Protected **v3 complete 160-question live ledger:** `classification/reports/periodontics-completion-ledger-v3-20261010.json`, SHA-256 `6ae42bdd40c3c1b6429a60d049d25e6b82ae8ff23ca8cb2e2b3152bd4dafd5e0`. Partition: **4** original 1398 Carranza12e verified page publications; **6** original 1399 Carranza13e verified page publications (including Q119); **16** 1398 source-free clinical/answer-key holds; **89** older 13e rows with NULL page and no exact-evidence approval; **5** additional 13e rows with chapter outside the announced year-specific scope and NULL page; **40** existing 14e rows with scientifically unverified placeholder-like page labels, with **4** of those also out of annual chapter scope. Nine out-of-syllabus historical source records remain. Total **160**, source rows **144**, unsourced **16**.

**Scientific closure is explicitly blocked**, despite successful release of all defensible audited changes: the 16 original-12e answer/evidence holds require question-by-question human scientific adjudication; 94 older source rows from exact 13e need evidence and page correction; and the full approved Carranza14e original book needed for 1404–1405 has not been supplied (the registered object is a 105-page supplement). Do not substitute another edition, synthesize page labels, overwrite human decisions, or falsely claim full completion.

Metadata-only protected research archive `/var/backups/fanoos/research/20261010-periodontics-final-q119-source-audit.tar.gz`, SHA-256 `4610ae2ef33243c94d05e0cddd15b0115fbc6d19ab9ca6c06fe1e23cd2a05442`, independently verified. **On-host only; not offsite.** Earlier research archives were removed by parallel cleanup. Reconstruction is reproducible from the registered PDF, versioned code and pinned evidence hashes.

---

> **Superseding live status — 2026-10-10, 12:18 UTC:** Exact-page repairs and 1398 second source-only batch were published and independently audited since the preceding snapshot below. **160 residency periodontics questions; 144 sourced; 16 without a source.** Source presence is NOT equivalent to accurate exact-edition original-page validation, so the subject is **not scientifically closed**.

## Latest verified live periodontics publication

### Carranza 13e: five 1399 historical page fields corrected, source chapter retained

| 1399 item | Existing 13e chapter | Verified original PDF page | Verified printed page |
|---|---:|---:|---:|
| Q113 | 45 | 1050 | 496 |
| Q114 | 51 | 1182 | 549 |
| Q120 | 17 | 616 | 244 |
| Q126 | 20 | 656 | 270 |
| Q129 | 23 | 708 | 306 |

Original approved 13e PDF SHA-256 `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`. Reviewed operator [PR #231](https://github.com/ArianGhsm/FanoosLearn/pull/231), merged SHA `2e1890da5ca36ba45e7c5297c5293661094eb9ac`, all five PR CI jobs green; official updater subsequently deployed canonical main `b69b9bfb0f316961276304982ec1ffb520393fb5`, health OK. Verified full backup `/var/backups/fanoos/20261010T120645Z-2ac1eec3` (54 files).

Atomic production receipt `/srv/fanoos/shared/research/classification/reports/periodontics-13e-page-repair-20261010/1399-five-page-apply-receipt-20261010.json`, SHA-256 `db0e133786706d7e09ed9dec529605312e192313421486faa4fb8f4e93b272f7`. **Independent comparison of all 20 source rows** confirmed ONLY these five `bank_question_sources.page` fields changed from NULL. Full 1399 frozen assessment remained published version **8**, with unchanged original SHA-256 `d36cc9097b8d096004128a967c5e15e279973927f2ea47b020c5876205158b85`. Other sources, stems, choices, official keys, previous assessments, attempt history, and historical anchors unchanged. Independent post-audit SHA-256 `6fef339de62e522545cafaf43e53d0cfc9959e72aa5fa20e38df5cd937756d38`; protected recovery archive SHA-256 `a43a63ce40c17729f361ece49bd1f939d8e426b061678183f506c3c53ad5a296`.

### Carranza 12e: 1398 Q134 and Q137 source-only insertions and exam publication

After original Q141/Q144 publication (previous section), a fresh **18-source-free** question study identified two additional exact Carranza12e, chapter **15**, original PDF **594**, book-printed page **229**: Q134 (recorded correct probing depth 2 mm after accounting for 3-mm visible recession and 5-mm actual attachment level) and Q137 (loss of stippling in early gingivitis). Primary validator accepted **2**, rejected **0**, pending **16**. The exact original source and printed page neighbors corroborated both.

An independent frozen-input second batch was introduced by [PR #236](https://github.com/ArianGhsm/FanoosLearn/pull/236), merged `986bae8610f963c8be60cd2cc92788e69a5a706b`, required CI green (run `38050961356`). Official updater request `01a125bb-5cf9-7122-a065-a068092074bb` **SUCCEEDED**, live health SHA matched `986bae8...`; full verified backup `/var/backups/fanoos/20261010T121615Z-541d6a2f` contained 54 files. Original first-batch Q141/Q144 immutable inputs and receipts were not overwritten. Protected independent followup provenance audit SHA-256 `7ad7fd4ac36ebf6aeb83d300b549d45fdba7dc5363da14c89ea815abeb93fd75`.

Source-only insertion preview and atomic apply both succeeded: exactly Q134 and Q137 got the new source link, **zero question/choice/answer edits**. Receipt `/srv/fanoos/shared/research/classification/reports/1398-periodontics-followup-source-publish-20261010.json`, SHA-256 `e0478158c2e4fa12966ac07961b6794e2c054bbb0dc17bf01492d2fd023f04fd`. Read-only post-commit query showed exactly **four** 1398 periodontics source rows Q134 ch15 p229, Q137 ch15 p229, Q141 ch15 p230, Q144 ch45 p487. **Sixteen** other 1398 periodontics questions remain unsourced.

Full 1398 published frozen residency assessment was republished from version **8 to 9**, 245 questions. Independent immutable post-audit established **all non-explanation content identical**; **23** source-derived explanations changed, attributable to concurrent already-verified published sources in **19 orthodontics, two oral pathology, and two new periodontics Q134/Q137**. No unseen stem/choice/key differences. Protected independent post-audit SHA-256 `f573cfd0f2d03b66dcf4b0f2758f1bf03708203069d4ab9e97bb3651c531c2a3`. Protected recovery archive SHA-256 `80883db517cab84d71e37ab3075bb8016130c23ff380aa8c01cfe133b21e284b`.

### Exact-book scientific holds still preventing whole-subject closure

- **1398:** 16 unclassified items. The source-book evidence conflicts with some recorded final answers or options: Q135 keyed answer text is malformed (book's gingival enlargement Grade II); Q138 original book says cellular intrinsic-fiber cementum fills resorption lacunae but keyed answer is acellular extrinsic; Q145 Fones brushing is circular, not vibratory as keyed; Q148 genetics is not an environmental factor. Other cases lack sufficiently direct answer-defining evidence. Preserved private per-item no-key-change ledger: `classification/reports/periodontics-1398-16-scientific-holds-20261010.json`, SHA-256 `676e966057bbe168578dd30b93e9bccb1de3672fa3cb9fe1520c7cf67df4fe9d`. **Never rewrite official answers to make classification appear complete.**
- **1399–1403:** 100 pre-existing Carranza13e source rows, now **5** validated live printed-page fields and **95** still NULL. The Q119 1399 historical chapter 48 contradicts direct original-13e ch47 PDF1073 p507. Five other current 13e chapter links fall outside announced year-specific scope. Existing nearest-14e historical anchors remain intact pending separately approved source/chapter/page-only correction.
- **1404–1405:** all **40** existing Carranza14e page fields are placeholder-like and unverified; four current assigned chapters are outside year-specific syllabus. The approved private source object is only a **105-page supplementary extract**, not the complete original edition, and Google Drive has the same partial object. No correct full-original-book page/answer audit is possible yet. No nearest-edition substitution is allowed.

**Read-back count:** 160 residency-periodontics questions; 144 have a source record, 16 source-free. Only the specifically listed 1398 source links and five 1399 printed pages are original-book evidence-verified and published in these audits. The remaining 135 older source rows must never be described as exact-page approved. On-host research backups are *not* offsite copies. Regenerated **complete 160-item machine-readable live ledger v2** at protected `/srv/fanoos/shared/research/classification/reports/periodontics-completion-ledger-v2-20261010.json`, SHA-256 `b16155c61307f366b879b833e2eebc5dd82f5a9822ebcf924388524c34d939f1`. Its partition is exact: 4 approved published 12e, 5 approved published 13e page corrections, 16 unsourced 1398, 89 other legacy 13e with NULL page, 5 out-of-scope legacy 13e, 1 confirmed 13e chapter conflict, and 40 unverified legacy 14e; nine current out-of-annual-scope rows are listed individually. Prior completion ledger is historical, not the current source of truth.

---

> **Updated verified production checkpoint, 2026-10-10 UTC10:28.** The original 2026-10-10 research-only snapshot below is historical for 1398 source availability: exact Carranza12e became ready, and two validated question source rows were subsequently published. The following new section is the current result; do not reuse the earlier unsourced count or "12e pending" state as live.

## Verified periodontics production completion checkpoint (1398 partial; subject remains open)

Latest production query and original-book audits establish **160 periodontics residency questions (1398–1405), 142 existing source rows, 18 raw unsourced questions**, and no human-reviewed source overrides. This must not be called 160 exact page-verified records.

### 1398 official Carranza 12e — two sources published

- Approved original 12e private PDF: 1,766 pages, 89 original printed chapter headings verified against corrected page ranges. PDF object SHA-256 `1332e1f92ec1dea1ee99c7382993ce3f191407099d7650ebb97c79b989553263`. A former page-text derivative was removed; the private boundary audit SHA-256 is `2953344c8bc3dfdf42703ce798543c404e6ae52006991127b9dde1030f69160a`. Correction/manifest/tests: [PR #215](https://github.com/ArianGhsm/FanoosLearn/pull/215), merged SHA `652d496a21182568e549a157c5378a9f1d21db21`, green CI.
- Exact edition + year/subject-specific source-only publication gates and regression tests: [PR #217](https://github.com/ArianGhsm/FanoosLearn/pull/217), merged SHA `4253bf4c41a19896aa27b5cea7336ff0f8397b35`, CI workflow run `38044496861` green. Official updater request `01a12556-d879-757c-9582-1d2679445673` **SUCCEEDED**; both live `/health` and checkout matched `4253bf4c41a19896aa27b5cea7336ff0f8397b35`.
- Pre-release study, re-exported from live source-free DB, was byte/JSON identical to prior W03 study for all 20 periodontics questions. Primary original-book `apply_classification.py`: **2 accepted, 0 rejected, 18 undecided**. Independent audit `classification/reports/1398-periodontics-two-audit-20261010.json` confirms unchanged stems/choices/official answer and exact source page/quote/node.
- Official full backup `/var/backups/fanoos/20261010T102642Z-77b8d9ac` passed `scripts/ops/verify-backup.php` (54 files). Exact source-only PHP preview then atomic apply inserted **only 2** source rows: Q141 Carranza12e ch15, original PDF p595, printed page 230; Q144 Carranza12e ch45, PDF p981, printed page 487. Origin `ai`, official scope passed, both original printed page corroborations passed. Production receipt `/srv/fanoos/shared/research/classification/reports/1398-periodontics-two-source-publish-20261010.json`, SHA-256 `7823991b0e746b7db49ce911106fdd8d73826ce267ac09ede43f7ca0c0650867`, reports `applied=true`, `source_rows=2`, `questions_changed=choices_changed=answers_changed=0`. DB post-readback showed precisely Q141 and Q144 were added and **18** other perio 1398 questions remain source-free.
- Full 1398 frozen assessment preflight compared all **245** questions with version 4, zero stem/answer/choice changes; after 2 insertions, official `scripts/import/import-bank.php publish` added published version **5**, preserving previous version. Independent `audit_published_assessment.php --previous=4 --current=5` succeeded: `question_content_identical=true`, `questions=245`, `updated_explanations=2`. Publisher carried forward 2 unrelated voided answers as voided/left out; no key changes performed. Live site health remained OK.
- Recoverable research archive `/var/backups/fanoos/research/20261010-periodontics-exact-reference-checkpoint-v2.tar.gz`, SHA-256 `7b36bbb657c1003d3b194d85b08d547eac4cc69c202e65af15c9909d9478b3f3`; on-host `.sha256` verification succeeded. This is **not** offsite storage and predates two production source inserts. Durable private handoff `/srv/fanoos/shared/research/ops/residency-periodontics-20261010.md` has post-apply details and recovery paths.

### Outstanding blockers — do not claim this subject fully closed

| Year(s) | Count requiring further exact-page adjudication | Reason |
|---|---:|---|
| 1398 | 18 without source | Original 12e is now ready but questions require item-by-item exact-answer evidence; some recorded official answers are clinically inconsistent or malformed. Do not change human/official answers as part of classification. |
| 1399–1403 | 100 source rows, **all without page** | Historical nearest 14e evidence used despite exact official Carranza13e. Full original 13e private text now available; requires exact-page original quote and review, and a new guarded metadata-only *correction* operator for already sourced rows (existing source-only publisher only inserts). Current 1399 Q119 ch48 conflicts with original ch47 PDF p1073, printed p507. Five currently assigned chapters fall outside official year scope: 1400 Q130 ch34, 1402 Q138 ch14/Q139 ch11/Q148 ch65, 1403 Q115 ch87. |
| 1404–1405 | 40 source rows, all suspect page values | 22 pages equal chapter numbers, 18 pages are placeholders 1/2/3. Approved private Carranza14e PDF is **only 105 supplementary pages**, while full original 14e page map expects over 1,800 pages. The full exact 14e is not present in connected Drive or approved server library; neither chapter nor printed page can be final-verified. Never substitute 13e for 14e. |

Keep source rows and question keys untouched unless exact-year evidence, validation, fresh verified backup, atomic audited metadata-only change, frozen assessment diff, and post-readback all succeed. **A sourced-row count is not an exact-reference completion count.**

---

# Dentistry residency periodontics — exact-reference audit checkpoint (2026-10-10)

**Status: private research only. No live question, choice, answer, source, historical assessment, or attempt record was changed.** This checkpoint is limited to the `periodontics` subject; other parallel workstreams own all other subjects.

## Source/year authority and current audit

The authoritative source is `data/bank/catalog.json` `validity`, read for the exact dentistry residency year and subject.

| Residency years | Exact official edition | DB question count | Source-row state | Verification status |
|---|---|---:|---|---|
| 1398 | `carranza-periodontology@12e` | 20 | 20 without source rows | **Pending** full exact-edition text and verified chapter/page map |
| 1399–1403 | `carranza-periodontology@13e` | 100 | 100 source rows with chapter, but **none with page** | **Not final**; prior nearest-edition evidence needs direct 13e review |
| 1404–1405 | `carranza-periodontology@14e` | 40 | 40 source rows with chapter/page | **Not independently final**; complete exact book PDF not currently verified |

These are read-only production measurements on October 10, 2026. Source existence must not be counted as exact-book evidence validation. Do not overwrite reviewed human source decisions.

## Original reference availability

- **13th edition:** original approved private PDF (116,004,765 bytes), 1,991 pages, 88 mapped chapters. Source SHA-256 `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`. The previous reader generated a temporary page-marked derivative; cleanup removed it. Current tools verify the approved PDF and inspect only requested pages.
- **14th edition:** approved private object SHA-256 `eb2ebe1a682635f9be38b2da3016af6c521d6a5f70052a8acf90841bf3d4ae18` is a **105-page supplemental e-page extract**, not a complete book; the catalog chapter map spans 1,875 pages and has boundary gaps. The current direct-PDF bridge rejects classification until a complete, correctly mapped exact edition is available. This **does not authorize reassigning 14e pages by guesswork** or modifying boundaries just to satisfy validation.
- **12th edition:** a complete exact PDF chapter/page map is still unavailable. No nearest-edition replacement is authorized.

## First independently original-page-checked records, 1399

- **Q111**: exact 13e chapter **62**, PDF page **1395** (printed **641.e3**), matches existing chapter 62; the original text relates removal of widow's peaks to gradualizing marginal bone. Existing production page is NULL.
- **Q119**: exact 13e chapter **47**, PDF page **1073** (printed **507**), **conflicts with current recorded chapter 48**. Original text explicitly equates targeted oral hygiene to the Bass technique, the official keyed answer. Existing production page is NULL. **Do not change source metadata until an audited, approved correction path exists.**

Five more 1399 questions independently checked against their **current official answer** and original 13e chapter/page/text:
- Q113 — ch45, PDF p1050, printed p496: acute periodontal abscess amoxicillin loading/dosing.
- Q114 — ch51, PDF p1182, printed p549: ultrasonic instrumentation and dysphagia.
- Q120 — ch17, PDF p616, printed p244: early lesion predominantly lymphocytes.
- Q126 — ch20, PDF p656, printed p270: biopsy in necrotizing gingivitis differential, including tuberculosis.
- Q129 — ch23, PDF p708, printed p306: most severe degenerative changes in lateral pocket epithelium.

Thus **seven exact-edition page-evidence research checks** are recorded (two above plus five here); they are neither imported nor independently approved for existing-row correction. Five-item private evidence report: `classification/reports/periodontics-1399-exact13-five-more-20261010.json`, SHA-256 `aa49845d6503ce2ab42748db1a920f14276c9e101b410920ba2ce47b6eb09e3f`. The isolated one-member on-host evidence archive `/var/backups/fanoos/research/20261010-periodontics-five-more-evidence.tar.gz`, SHA-256 `750c4f336f9f3c8d5d886c904d61840eb3add50fcf6c45544e8b70b8eb040923`, passed archive-member and external `sha256sum -c` verification. As with the main archive, this is not an offsite copy.

The historical report checked page text, edition mapping and official answers using the earlier temporary derivative. Its protected JSON and concise evidence digest are retained at `classification/reports/periodontics-1399-exact-book-evidence-20261010.json`, SHA-256 `01909be8c1b263c15c8a8edda499fbaf08ec58d2d895a662adc0a30921f444c2`; revalidate any cited page directly from the approved PDF before new work. No original-book excerpts or private questions are committed to Git.

A 20-question original-book *search*, not 20 accepted mappings, has been staged:
- `classification/reports/1399-periodontics-carranza13-queries-20261010.json`, SHA-256 `527db92aef661242c37f22c856d40f13708f8ad8c925eab0fd20317a2f06a61b`
- A temporary search-output text file from the historical pass was removed during cleanup. Future searches should be rerun from the exact PDF page reader and leave only a concise validated receipt.

These are under private root `/srv/fanoos/shared/research/`, alongside `ops/residency-periodontics-20261010.md` (SHA-256 `975025c151090d52f3fd864ae5aaa3a4baac70989b3415b06b3d6c191ed2d1b1`).

A historical on-host audit archive `/var/backups/fanoos/research/20261010-periodontics-exact13-audit.tar.gz`, SHA-256 `53763d82c232a0c99a3d67c53a93011f060c03cafa99a0fee8be2250e6955246`, was recorded with a passing adjacent `.sha256` receipt. Its member inventory must be rechecked through authorized server access before use; no page-text member may be restored. This is **on-host**, not independent offsite recovery.

## Reproducible next steps and guards

1. Recheck fresh `main`, production DB, reference object inventory, active research decisions and concurrent work before any new write.
2. Evaluate every 1399–1403 question from its current live official answer and exact **13e PDF**, inside that year's official scope. Require a page-at-a-time PDF evidence check, exact chapter, printed/PDF page alignment, confidence and human-review threshold. Keep unsupported candidates pending; do not auto-accept top search hits.
3. Read only needed pages directly from the approved PDF; never retain an extracted page, full-book text or index. Preserve existing AI/human decisions and make immutable, independently reviewed before/after evidence packages.
4. **Existing rows already have source links:** the current `scripts/references/import_verified_sources.php` is an **INSERT-only source publisher** and is inappropriate for changing them. First implement/test/review a dedicated idempotent **source-metadata-only correction operator**, with immutable source snapshots, protected human decisions, no updates to question/choice/answer/assessment/attempt fields, a freshly verified full backup, preview, independent audit, exact-page validation and post-apply DB readback.
5. For 14e, source and verify the **full original exact PDF** and chapter boundaries; for 12e, obtain/verify exact reference and map. Missing editions remain pending without blocking 13e.
6. Develop any code change in an isolated branch → green CI → reviewed PR → merge → official updater deployment → health/sync validation. Count source changes only after a distinct audited import receipt and live post-readback; **none were made in this checkpoint**.

See `docs/PROJECT_PRINCIPLES.md`, `docs/product/09_CHAPTER_CLASSIFICATION.md`, `docs/ops/SERVER.md`. Retention dry run observed one oldest full backup eligible for policy-managed pruning after the on-host archive addition; do not delete backups ad hoc.
