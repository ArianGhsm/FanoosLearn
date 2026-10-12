# Residency 1405 — strict original-source quote verification across PDF line-wrap hyphens (2026-10-12)

## Rechecked live baseline

Live public `/health` was `status=ok` on release `cd3b7913640dcb6ccdcb471e637e9e3236b89c4e`. A scoped, independent, read-only MySQL count for `residency:1405:round1` returned **98** source rows with `node_id`, `pdf_page`, and approved `pdf_sha256` in the same row. There are 250 total residency questions and 20 valid English reference-exempt questions; this left 132 textbook-based incompletes. Such a field count is not a guarantee that each legacy chapter is within official scope.

The guarded, unchanged production operator successfully completed Q66 oral-medicine source-only repair at **2026-10-12 15:38:32 UTC**. This uses the original audited case `/srv/fanoos/shared/research/classification/recovery-1405-medicine66-20261012` (audit SHA-256 `4d2b5d79650418baf07dd74127896e67ae975098a134dcdf971cd48d9e3253d8`), official Burket 13e **chapter 6**, printed book page 181 and **PDF page 201** (approved PDF SHA `0ca841beb1820e8e0a33c96b5ce37cc36c94d6adce53fbf172b27312e0fd5252`). A fresh official full backup `/var/backups/fanoos/20261012T153533Z-0a404a37` passed independent `verify-backup.php` (**114 files**). The protected live receipt at `/srv/fanoos/shared/research/classification/recovery-1405-medicine66-20261012/classification/reports/1405-medicine66-live-repair-20261012.json` confirms `applied=true`, precisely one existing source row changed, and all **question/choices/answers/chapter/human-review** change counts equal zero. Independent post-commit MySQL readback confirmed Q66 `printed_page=181,pdf_page=201` and the scoped field count incremented to **99/250**, leaving **131 incomplete textbook-based**. This is the current verified live count in this effort.

## Newly verified source evidence, held until a proper release

The current **GitHub main / updater checkout** contains approved PDF SHA-bound chapter maps for McCracken 13e and McDonald/Avery 11e. The still-live deployed release does **not** contain those SHA pins, so its `verified_reference_pdf.py --require-map` correctly fails closed. The following scientific cases were validated and audited *only with the SHA-pinned canonical updater checkout*; the guarded **preview** returned `applied=false` and all protected change counts zero. **No production source was written for these questions.**

| Question | 1405 official reference | Chapter | Printed book page | Actual PDF page | Original audited evidence SHA | Guarded preview |
|---|---|---:|---:|---:|---|---|
| 88 | `mccracken-rpd@13e` | 14 | 204 | **217** | `ddbdba9a111518a219a45e3c78d127ea9801667968d56f275bb6a22b0f84305a` | pass on SHA-pinned checkout |
| 185 | `mcdonald-avery-pediatric@11e` | 23 | 496 | **502** | `c35ba7384e296b94c246c757b99ea77e1d15f0cc5a221673f2d1a770683b6471` | pass on SHA-pinned checkout |
| 186 | `mcdonald-avery-pediatric@11e` | 17 | 352 | **360** | `bd40d1c1a5c9d73bfe98ca242cd41f45d11341b8de67d024af18a0504aa50792` | fail on original whitespace-only normalizer; hard-wrap proof below |

Every case has 1 accepted/0 rejected in `apply_classification.py --pdf-pages` and 1 accepted/0 pending in the independent provenance audit. Original live question, choices and **latest official answer** were copied *unchanged* into private immutable single-question research packages at `/srv/fanoos/shared/research/classification/recovery-1405-prostho88-20261012`, `.../recovery-1405-pediatric185-20261012`, and `.../recovery-1405-pediatric186-20261012`. Q185 has a **latest amended official answer**, not `final`; its `amended` status and exact answer were preserved. Do not publish or reinterpret it. Approved PDF SHA values are McCracken `4e05bee57d1066601b8a2b30c2ae72d4a5bf65298747b600ae3fdd8248a192d9` and McDonald/Avery `a344465f89ff6711099a8bee1878e83236c9778340447ac7491bbc27a89fb629`.

## Evidence for conservative wrap-aware source text comparison

The old PHP `PrintedBookPageEvidence::normalize` only lowercased and collapsed whitespace. The official PDF often extracts an ordinary word as a **terminal hyphen immediately followed by a physical line break**. The original stored source anchor contains the *same uninterrupted word*; Python's existing scientific `apply_classification.flat` already dehyphenates precisely this line-ending pattern. This created false-negative PHP guard rejects for the following already-reviewed research-only or unchanged historic cases:

| Question | Edition/chapter | Printed → PDF page | Plain PHP-equivalent match | Match after removing **only** actual `-\n` line-wrap hyphens |
|---|---|---|---|---|
| 34 | Torabinejad 6e ch22 | 466 → 473 | false | true |
| 51 | Neville 5e ch12 | 533 → 543 | false | true |
| 57 | Neville 5e ch15 | 722 → 732 | false | true |
| 143 | Hupp 7e ch28 | 623 → 637 | false | true |
| 186 | McDonald/Avery 11e ch17 | 352 → 360 | false | true |

For each, read **only the single approved SHA-checked PDF page** in memory and compare against the immutable existing DB `anchor_text`; the match was demonstrated without stripping in-line hyphens, altering any sentence punctuation, dropping arbitrary words, or relaxing the page, chapter, SHA, neighbor-label or original question/key guards. For example, the exact official Q34 page physically splits a word after `rea-` at line end; Q143 splits a word after `exten-` at line end. This is a layout artifact, not a different citation.

The minimal patch in `PrintedBookPageEvidence::normalize` joins **only** `/-\\h*\\R\\h*/u` and otherwise retains the existing lowercase/whitespace and exact substring check. The new regression exercises same-page PDF word joins while rejecting punctuation changes, ordinary hyphen changes, unrelated words and missing line breaks. The independently verified printed page and at least two neighboring labels, exact current approved source-PDF checksum, year-specific official chapter scope, unchanged AI source anchor, source-only transaction, protected full backup and human-review preservation remain mandatory.

**No hard-wrap case is counted as production complete merely because this test or research passed.** After PR and exact canonical main CI are green, a genuinely approved release must be deployed and health-checked; fresh complete backup and protected dry-run must precede each single-row guarded source repair. Recheck the same live original published question, four choices and latest final/amended answer and save private receipts. If any guard fails, hold that question and proceed to others.

## Radiology unsourced backlog

There remain **five source-free non-English oral-radiology questions** in residency 1405: Q191, Q192, Q193, Q194 and Q207. The 1405 official reference is White/Pharoah 8e with approved SHA `228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`, 1958 PDF pages, a complete SHA-bound chapter map and a specific official chapter scope. They require independent *actual content* review (and image evidence for the filtration graph in Q191). Do not invent pages or use existing-source repair on a source-free question; use separately guarded **source-only insertion**, after original stem/choices/latest key verification and provenance audit, without changing the frozen published exam.
