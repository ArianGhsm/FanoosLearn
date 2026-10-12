# Residency 1405: eleven independently audited exact source-page candidates (2026-10-12)

## Verified production baseline
The scoped live residency 1405 round-1 database had **250 questions, 99 source records with node_id + pdf_page + pdf_sha256**, 20 English exempt and **131 textbook questions remaining incomplete**. This is only a populated-field count, not certification of old chapter choices. Site /health returned ok, still on older release cd3b7913640dcb6ccdcb471e637e9e3236b89c4e; the canonical GitHub main has newer SHA-bound reference maps and a guarded end-of-line dehyphenator.

## Seven existing-source candidates: all PASS guarded rollback-only preview
Each has an original immutable live question, four choices, final or amended official key, official 1405 edition/chapter, SHA-pinned approved current server PDF, original source anchor found on the exact original PDF page (rejoining only end-of-line hyphens), verified printed book label and two adjacent page labels. Primary PDF validator and independently hash-pinned provenance audits passed, 1 accepted/0 rejected/0 pending per case. Each current main-code guarded preview returned applied=false, one eligible existing source, and ZERO protected-domain edits to stem/choices/answer/chapter/human review.

| Q | Edition | Chapter | Printed | Real PDF | Private protected case |
|---|---|---:|---:|---:|---|
| 34 | Torabinejad6e | 22 | 466 | 473 | recovery-1405-endo34-20261011 |
| 51 | Neville5e | 12 | 533 | 543 | recovery-1405-pathology51-20261011 |
| 57 | Neville5e | 15 | 722 | 732 | recovery-1405-pathology57-20261011 |
| 88 | McCracken13e | 14 | 204 | 217 | recovery-1405-prostho88-20261012 |
| 143 | Hupp7e | 28 | 623 | 637 | recovery-1405-surgery143-20261012 |
| 185 | McDonald11e | 23 | 496 | 502 | recovery-1405-pediatric185-20261012 |
| 186 | McDonald11e | 17 | 352 | 360 | recovery-1405-pediatric186-20261012 |

Exact audit SHA-256 (in Q order):
34 fb054d98cacbfd84d0c2c53e9157fda7f39bf13797882437d4fd831021fefafd
51 e8f710c2ba139473ea3d69daa98e96d8dcdece9a30cfe23619e9aad160a1a341
57 b7424f311049d3fe19dda731ceb71a9192660acfe7046a7fb849e7ae7ffbaaa3
88 ddbdba9a111518a219a45e3c78d127ea9801667968d56f275bb6a22b0f84305a
143 ba49d258c222484ea197e74ed52732ed395e23abdd70878904da9e1dcbd88927
185 c35ba7384e296b94c246c757b99ea77e1d15f0cc5a221673f2d1a770683b6471
186 bd40d1c1a5c9d73bfe98ca242cd41f45d11341b8de67d024af18a0504aa50792

Cases located as direct children of /srv/fanoos/shared/research/classification/, each preserving original study, decisions, validated output and protected provenance audit. Q185 official answer was latest amended and was preserved verbatim, not changed.

## Four oral-radiology source-FREE questions: atomic source-only dry run PASS
Approved reference White/Pharoah 8e, SHA 228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a, official 1405 chapter scope, true PDF page:
Q191 chapter1 PDF60, Q192 chapter2 PDF94, Q193 chapter3 PDF120, Q194 chapter4 PDF186. The original live questions, choices and latest final answers were preserved without changing their contents. Independent validator and audit: 4 accepted, zero pending; audit SHA 2abf5865abdb948b347d33abb4bcaa0302b23af82724fe3413c534b4fcbfc2a3. Case: /srv/fanoos/shared/research/classification/recovery-1405-radiology191-194-20261012/.

Green PR #370 merged as 09c410e9bca19911cf73ff81ccacc8f2d2bf4ced; QA checkout pinned to exact merge passed PHP lint and all three new security contract tests. Rollback-only source-only importer tested against live DB with that main code returned applied=false, source_rows=4, questions_changed=0, choices_changed=0, answers_changed=0. No source row inserted yet. For Q191 verify the published image label itself before asserting scientific answer validity.

## Periodontics limitation: NOT safe to autoclassify
Carranza14e current approved server PDF has 105 pages with scattered excerpts, SHA eb2ebe1a682635f9be38b2da3016af6c521d6a5f70052a8acf90841bf3d4ae18. The existing legacy unbound map covers putative PDF pages 2..1875 and CANNOT be pinned to the current 105-page PDF or trusted for a real page number. All 20 remaining periodontics original source anchors were compared against all 105 actual PDF pages; ZERO matched on their source strings. The legacy printed page field for many is a chapter number or placeholder rather than a real printed page. Obtain the correct approved complete edition or independently identify exact actual scientific evidence before assigning PDF page. No DB edits.

## Required gates to close
Only owner-authorized deployment.manage may request the canonical green-main updater deploy. Do not impersonate an owner, bypass CI or run unstaged code for source edits on live DB. On successful release and health, take and independently verify a fresh full backup; rerun guarded previews from the DEPLOYED code; use existing-source repair with exact audit SHA for 7 cases and source-only importer for 4 radiology cases, each with unique receipts. Read back exact original question/option/key and frozen published version unchanged, verify hashes and actual source pages, then update scoped count. Q207 remains scientifically cross-chapter and source-free; preexisting chapter-scope anomalies Q44/Q129/Q130/Q217/Q218 remain open.

This document records 11 independently reviewed, safe rollback-preview eligible cases, **not 11 applied production completions**.
