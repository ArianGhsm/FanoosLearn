# Residency 1391 — exact source, chapter and PDF page preflight (2026-10-11)

## Scope and verified live state

- Production FANOOS site: `https://fanooslearn.ir/health` responded `status: ok` at release `524ec2b25a8ce6325ab95b26b66e25bcb50f7140` during this preflight.
- The newly imported `residency / 1391 / round 1` sitting has **220 questions**, numbered 1–220. There are **zero** `bank_question_sources` records for these questions. Consequently **zero** source+chapter+verified-PDF-page completions are recorded.
- Official answer state exported from the live bank: **217 final**, **3 voided** (questions **86, 111, 160**). Retain the voided questions and their keys/status exactly; do not silently score, source-complete or modify them.
- `data/bank/catalog.json` has **zero** `validity` rows for `exam_type=residency, year=1391`. The currently configured official dental-residency validity starts with **1397**. This is a missing authoritative reference-year mapping, **not** permission to use 1397 editions retrospectively.
- Internet/Drive discovery in this preflight did not establish an authentic 1391 **dental** residency reference notice identifying book editions and scope. Medical-residency 1391 source notices are unrelated. Do not treat secondary or adjacent-year sources as official.
- Apply the atomic rule from `docs/PROJECT_PRINCIPLES.md`: one accepted source row must have **exact official edition + chapter node + verified `pdf_page` + `pdf_sha256`**, supported by direct short evidence from the actual server PDF. A chapter without its same-source verified PDF page counts as **incomplete**.

## Subjects in the sitting

| Subject | Questions |
|---|---:|
| community-dentistry | 10 |
| operative-dentistry | 20 |
| oral-pathology | 17 |
| oral-medicine | 17 |
| periodontics | 20 |
| prosthodontics | 30 |
| oral-surgery | 26 |
| oral-radiology | 10 |
| dental-materials | 10 |
| endodontics | 20 |
| orthodontics | 20 |
| pediatric-dentistry | 20 |
| **Total** | **220** |

## Site-matching starting file, validation and protection

- Canonical, access-controlled export: `/srv/fanoos/shared/research/bank-sittings/1391/residency-1391-1.json`.
- Exported by the versioned `scripts/import/import-bank.php export` against the matching production workspace and sitting. Contains **220 unique numbered questions**, **2 associated images**; SHA-256 of the JSON:
  `7ef8f7c4aabc620fe7fc132c76615a9f5517baa98f9d4c7a951f97f9efc2730a`.
- The official `import-bank.php import --dry-run` with that sitting and assets returned `questions: 220`, `questions_changed: 0`, `answers_recorded: 0`, `sources: 0`, `images: 0`. **No production import, assessment re-publication, question modification or source insertion was performed**.
- Private machine-readable receipt:
  `/srv/fanoos/shared/research/ops/residency-1391-classification-preflight-20261011.json`, SHA-256:
  `ebb7b66d3c2802ec20cd00a611e75c3f1005aee22dc1c0b2db437b026d9c7c9f`.
- Verified separate protected archive of the sitting, two associated images and preflight receipt:
  `/var/backups/fanoos/research/20261011-residency1391-preflight.tar.gz`, SHA-256:
  `873b0a8fb65ceb275a755acf39f16cffef60e6040591b4cc05e05d5bb600985b`. The archive was reopened and all **4 files** compared byte-for-byte via SHA-256. This is a research snapshot, **not** a newly performed official full-production database backup.

## Status: official source-year identification blocks all 12 subjects

**Verified new chapter+page classifications: 0/220.** Only 217 final-key questions are presently candidates for book-based evidence review; 3 voided are deliberately excluded from the scored backlog. Do not claim completion or insert chapter-only placeholders. A 2011/2012 book's mere existence in the library does not prove it was official in the 1391 exam.

1. Obtain the authentic **26th dental-residency entrance exam / 1391** official reference notice, including book title, exact edition/year, subject, and syllabus limits. Preserve reference provenance and record the evidence in the catalog through a tested PR.
2. For each actually listed edition, audit the private exact-version server PDF with `reference-library-inventory.php`, `verified_reference_pdf.py` and `build_chapter_pages.py`; prove PDF SHA-256, readability and complete boundary coverage without exporting full text.
3. Process eligible questions per `docs/product/09_CHAPTER_CLASSIFICATION.md`: verify short quoted evidence against the specific original PDF page and chapter; validate each decision and independently audit it. Keep missing editions and uncertain facts pending without stalling other eligible subjects.
4. Before writing live sources: match the frozen export, run the guarded source-only rollback preview, verify a **fresh full-production backup**, apply **only** verified source metadata, read back the DB and published assessment, verify CI/deploy where applicable, and update this report with exact per-question receipts and counts.
5. Never edit question stems, choices, keys, human decisions, voided state, frozen exams or attempts to complete the mapping. The protected research export is the base; the source-year policy is a hard precondition.

This record is deliberately a **blocked scientific preflight**, not a finished classification or a claim that any of the 220 source records were added.
