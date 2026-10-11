# Dentistry residency 1399 — eight original PDF pages committed to production (2026-10-11)

## Verified completion and limitations

**This is the source-metadata write/readback receipt, not a claim of frozen student-exam publication.** The authenticated IranServer `fanoos_prod` bank was checked directly. Production at the time of both applies was healthy on release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`, which includes the audited and CI-green versioned operator from PR #305, merge `781b3111c18cf50cd1a13e665bfde10cd7f81501`.

One sitting: `residency / 1399 / round 1`, 249 questions. Strict same-row `node_id + pdf_page + pdf_sha256` count **87 before** and **95 after**: **eight net additional recorded-complete sources**, zero inferred pages. This is the field-presence counter, not independent verification of the other 87 legacy records.

### Endodontics — exact Torabinejad 5e

| Q | Preserved chapter | Original PDF page | Printed book page |
|---:|---:|---:|---:|
| 25 | 4 | 63 | **51** (corrects legacy printed 7) |
| 30 | 18 | 347 | 335 |
| 32 | 1 | 21 | 9 |

Original approved PDF SHA-256: `71f21c26a4f1e5d038ff6aa408669a5cc040e90c94eea0bf2e00478997710146`.

- Private sealed preflight plan: `/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/endo-plan-20261011-pass1.json`, SHA-256 `9ed61378a2aa47212ed1dee50aa0b2b0c1c429dac1d6af763e1522aef618e4a6`.
- The live operator returned `applied: false, count: 3` for preview, then `applied: true, count: 3` for guarded apply; return code 0.
- Exact private apply receipt: `/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/endo-apply-20261011-pass1.json`, independently hashed SHA-256 `9fe43376ac52f7594995aba38aeb4af5067dc1a90da674a1f8bbebabf19861b7`.
- Independently re-read live `bank_question_sources` confirmed Q25 PDF63, Q30 PDF347, Q32 PDF21 and the full expected original PDF SHA-256 for each.

### Periodontics — exact Carranza 13e

| Q | Preserved chapter | Original PDF page | Printed book page |
|---:|---:|---:|---:|
| 115 | 12 | 506 | 182 |
| 118 | 33 | 882 | 408 |
| 122 | 72 | 1611 | 721 |
| 123 | 52 | 1196 | 559 |
| 125 | 3 | 96 | 46 |

Original approved PDF SHA-256: `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`.

- Private sealed preflight plan: `/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/perio-plan-20261011-pass1.json`, SHA-256 `9736cc80de6e9c8ce34ffbc19e33993999e95c8a656a5413aa322cd6b7c4526a`.
- Live operator returned `applied: false, count: 5` for preview then `applied: true, count: 5` for guarded apply; return code 0.
- Exact private apply receipt: `/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/perio-apply-20261011-pass1.json`, independently hashed SHA-256 `472c6ef2828ba614fedc7440aeee658562955bcb989f10df05186eec502713e9`.
- Independently re-read live source rows confirmed exact PDF pages 506/882/1611/1196/96 and original approved SHA-256 prefix `f0e411898ae01068`.

### Protected backup and invariants

- Existing full backup `/var/backups/fanoos/20261011T143835Z-3e7ca9e5` passed read-only `scripts/ops/verify-backup.php`: **114 files verified**, before both applies. Both source-only operators verified its fresh signed manifest and emitted its ID in private receipt. Do not claim independent off-host backup from this server-local full backup.
- The one-time operator is committed and PR CI validated; it locks the narrow source metadata workflow, compares live question/option/all official key/source fingerprints against a sealed plan, reads exact book PDF page/neighbor page labels and evidence, and performs a single atomic source-only compare-and-swap. Its only permitted data updates are **`bank_question_sources.page`, `printed_page`, `pdf_page`, `pdf_sha256`**. It returned **zero question, option, answer, human, attempt or assessment updates** for both batches.
- No new official chapter was silently substituted: the original precise official edition/chapter/syllabus must already match and the active source row remains identical except the four allowed metadata columns.

## Next gates — not yet completed here

- **Frozen student assessment:** database `residency 1399` published assessment was at **version 8**, with **248 frozen published questions** versus 249 raw bank questions. One existing read-only preflight (`audit_published_assessment.php --year=1399 --subject=endodontics --stem=endodontics`) returned `matched_study_questions=1`, `full_sitting_questions=248`, `question_content_identical=true`. That limited preflight is **not** a full-sitting safe-republish clearance. The bank sources are updated, but the existing frozen exam has **not** been republished by this operation. Preserve version 8 and all student attempts until a full 248-question comparison and authorized append-only publication can be proven, with old/new noncitation question content identical.
- Site-wide and per-subject progress displays must be re-read after any future import; new baseline was 95/249 strictly recorded, 154 raw incomplete, including 20 English no-book citation exemption, **134 clinical incomplete**.
- Additional original-book research is recorded in `docs/ops/RESIDENCY_1399_MATERIALS_PROSTHO_PDF_PAGES_20261011.md` (merged docs PR #310). Guard exact official partial-page and sectional syllabus. A separate batch-two code PR (#312) is undergoing CI/review at this checkpoint; none of those new proposed rows is yet counted as complete.

This record contains no private question stems, answer options, full book pages, personal identities or authentication material. Private plans, evidence and receipt bytes stay in protected production research storage and verified recovery.
