# Residency 1402 — Burket 13e and Torabinejad 6e exact original-PDF-page repairs

**UTC date:** 2026-10-12. **Live source ledger** for `residency / 1402 / round 1`. This record distinguishes source-row updates from the separate frozen-assessment publication step. It is an append-only scientific and operational checkpoint; no question/option/answer text or original private PDF material is stored in this document.

## Live starting point and immutable policy

The previously verified 1402 assessment was **published version 7** with 243 scored questions and seven official-voided question numbers 60, 96, 99, 102, 103, 144, 181. Strict source coverage before this chapter-page campaign was **91 of 250 questions**. An earlier adjacent action source-corrected Burket Q41 and Q43 (91→93); during the resumed operation a further six Burket questions and nine Torabinejad questions were added (93→108). The site-wide requirement remains that **chapter and the exact physical original PDF page must be in the same source row with the approved PDF SHA-256**, not an unverified legacy printed page or a nearest edition.

Two allowed field changes ONLY: `bank_question_sources.pdf_page` and `bank_question_sources.pdf_sha256`. The guarded versioned `scripts/references/repair_verified_source_pages.php` requires an existing unreviewed AI source row with original official edition + in-scope chapter, same immutable original anchor and old printed page, final/amended official answer, exact source ID, an actual supporting phrase on the originally approved edition PDF page, PDF hash-bound chapter-page map, and printed-page pagination corroboration. Each production apply followed a successful rollback-only preview and was independently read back. Source history and all protected human decisions remain unchanged.

## Original approved books

| Official edition for residency 1402 | Original PDF SHA-256 |
|---|---|
| `burket-oral-medicine@13e` | `0ca841beb1820e8e0a33c96b5ce37cc36c94d6adce53fbf172b27312e0fd5252` |
| `torabinejad-endodontics@6e` | `09333f079cb3300550cc0702985aae216d952eacabdaac005e1c66fafebe13e0` |

## Eight Burket 13e question-page repairs

| Q | Chapter | Exact PDF page | Printed book page | Direct answer-bearing book check |
|---:|---:|---:|---:|---|
| 41 | 3 | 78 | 58 | Pemphigus bulla arises on otherwise normal mucosa |
| 43 | 3 | 64 | 44 | CMV can manifest as a large single ulcer in an immunocompromised patient |
| 44 | 4 | 128 | 108 | Oral lichen planus plaque-type lesions in smokers |
| 45 | 4 | 117 | 97 | Clotrimazole local application among angular-cheilitis treatments and pregnancy cautions |
| 46 | 5 | 172 | 152 | Alcohol-associated posterior pigmentation, including the soft palate |
| 47 | 5 | 159 | 139 | Hairy tongue classified under exogenous pigmentation |
| 48 | 6 | 193 | 173 | Hyperlipidemia association with Fordyce spots not substantiated |
| 50 | 6 | 208 | 188 | Oral lipoma differential includes abscess, sialolith, and granular cell tumor |

Burket private guarded-plan/receipt pairs are in `/srv/fanoos/shared/research/classification/reports/`, with filenames
`residency-1402-burket13e-batch-1-{...}-20261012.json` (Q41/43),
`residency-1402-burket13e-batch-2-{...}-20261012.json` (Q44/45/46),
`residency-1402-burket13e-extra48-50-{...}-20261012.json`, and
`residency-1402-burket13e-q47-{...}-20261012.json`.
Applied receipt SHA-256, in that same order:
- `7d40697d4b18e1e88c0083db0c454b8c0eb412b944f1c17e09929cb055ecd2d4`
- `537be39c685fec97455e21bea271131c78a1a4e87e08d18fd6414591cf792473`
- `0f777254f756a15e300156317105a4c1abfc8a6ea3fcc4301fe024347a40120a`
- `9db42e94ba4e5018791ea6a31c86b8215b3bb64951c4df72cc19fb16dbc194e0`

The eight successful source updates were backed by independently verified full `114-file` official backups (including `/var/backups/fanoos/20261012T153557Z-38668c66` for the final six Burket corrections). Previous Burket 1402 research found Q42 less directly supported by its old printed-page citation; Q49, Q51, Q52, Q54 were flagged outside the existing structured official 1402 scope. Do not fill these cases by extrapolating page offsets.

## Nine Torabinejad 6e question-page repairs

| Q | Chapter | Exact PDF page | Printed book page | Direct answer-bearing book check |
|---:|---:|---:|---:|---|
| 2 | 3 | 58 | 51 | Beam-hardening produces dark, rather than bright, streak/band artifacts |
| 3 | 3 | 60 | 53 | CBCT generally has higher vertical-root-fracture diagnostic performance than 2D imaging |
| 4 | 4 | 73 | 66 | Gingival recession and attachment loss reduce cold-test sensitivity |
| 8 | 14 | 327 | 320 | Calcium hydroxide may aid dissolution of necrotic remnants |
| 9 | 8 | 149 | 142 | Supplemental intraosseous mepivacaine improves IANB success |
| 12 | 10 | 194 | 187 | At least 5 mm of MTA apical plug for adequate seal |
| 13 | 10 | 194 | 187 | Apexogenesis retains vital-pulp therapy, unlike apexification |
| 18 | 4 | 79 | 72 | Sinus tract drainage indicates chronic rather than acute apical abscess |
| 20 | 11 | 210 | 203 | Crown-root fractures often occur in premolars |

Private 0600 guarded plans and atomic receipts:
`residency-1402-endo6e-verified-{1,2,3}-20261012.json` and corresponding `-applied-20261012.json` (Q2/4, Q8/9, Q12/13/18/20). Receipt SHA-256:
- `22221f03023ecd4d1c7d534b8b0f63024650dd3025e73e8dd561549d42883a9f`
- `dcef680a05a48de09f9a330ee1f546566d92a304ef85f6beb9282c45e5da5ab7`
- `5386f64cdadd7c8570bd4564c6c4a4180a6f6bb13ebfa2a5d0c34d471f1a332f`
These eight were applied with the independently verified full `114-file` backup `/var/backups/fanoos/20261012T154533Z-a21afa79`.

Q3 initially lacked an exact verifier-matching quote. A new pinned private plan with a direct original-page quote passed guarded rollback preview, official page/header-neighbor checks, and exact official chapter scope. Applied with independently verified `114-file` backup `/var/backups/fanoos/20261012T155537Z-9a271573`. Private receipt `residency-1402-torabinejad6e-q3-applied-20261012.json`, SHA-256 `ddfc931ebd82fbfd1bc90e2185ff2a65615e799278c4dbb4d7a15118db79184e`. Read-back confirmed chapter 3, original PDF page 60, printed page 53, original PDF SHA.

**Held by the original-page verifier:** Torabinejad Q1, Q5 and Q6 had an answer-bearing passage at the old apparent page, but adjacent printed-page corroboration **failed**, so no PDF page was written. Other endodontic questions remain for independent, explicit original-book research. Do not infer a whole book's pages from an assumed constant `printed+7` offset.

## Independently confirmed production count

Live MySQL read-back after all 17 applied sources on 12 October:
- **250** total 1402 residency questions.
- **108** questions have a chapter node, PDF page, and exact PDF SHA-256 *on the same source row*. An atomic row-field completeness measure, not a full scientific re-audit of all legacy pages.
- **142** raw incomplete. **20** are English with official final answer and reference-exempt; **122** non-English cases remain unresolved. At the previous phase six non-English records were voided and excluded from the scored assessment.
- Earlier independent official-year structured-scope screening found 29 potential out-of-announced-chapter citations; these overlap incomplete/completed rows and **must not be added** to the 122. Eight had already been PDF-bound. Scope resolution requires the actual year-specific official announcement and partial-chapter limits, not automatic relabeling.

Reproduce as correlated `EXISTS` on `bank_question_sources s` with `s.question_id=q.id AND s.workspace_id=q.workspace_id AND s.node_id IS NOT NULL AND s.pdf_page IS NOT NULL AND s.pdf_sha256 IS NOT NULL`, scoped through `bank_exam_sittings` and `bank_exam_types.type_key='residency'`, `exam_year=1402`, `exam_round=1`.

## Assessment and deployment gate

At source-write read-back, the frozen **v7** assessment still contained **243** scorable questions. The live `audit_published_assessment.php` preflight returned `question_content_identical=true`, `full_sitting_questions=243`, and 20/20 matching protected radiology-study questions. The application health check remained `status: ok` at release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. No source-only patch changes stems, options, official answers, human decisions, attempt history or older frozen assessment versions.

**Verified production v8 publication:** Following Q3, a new full production backup `/var/backups/fanoos/20261012T160058Z-a923565c` finished successfully and the independent `verify-backup.php` validated **114 files**. A fresh full-sitting preflight against frozen v7 returned `question_content_identical=true`, **243** scorable questions, and 20/20 matched protected radiology study questions. The owner-authorized canonical `scripts/import/import-bank.php publish` created **assessment version 8** (version ID `01a130d9-7de0-7fe3-b747-b65181591a10`), preserving all seven officially voided/non-scored question numbers and prior frozen v7. Post-publication `audit_published_assessment.php --previous=7 --current=8` returned `question_content_identical=true`, `questions=243`, `updated_explanations=17`. An independent exhaustive explanation-ID comparison found **exactly the 17 original Burket/Torabinejad corrected questions** and **zero unexpected/missing IDs**. Live MySQL read-back confirmed assessment status `published`, `current_version_no=8`, strict source completeness **108/250** and unchanged old frozen v7. Site `/health` remained `status: ok` at release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. This was a source-derived explanatory revision, not a modification of stems/options/keys/scoring order or prior attempts. Backup work can overlap with other exam years; never rerun an ambiguous backup job or overwrite a receipt without first inspecting the active process and production state.
