# Oral-pathology residency source audit — 2026-10-10

**Status: research audit completed; production source metadata unchanged.**
Scope: dentistry residency, **oral-pathology only**, exam years 1398–1405.
Do not confuse existing chapter metadata with original-edition/page-verified citation.

**PDF workflow correction:** Some earlier candidate counts below came from a temporary full-book text derivative. Cleanup removed those derivatives. Treat the counts as historical leads only; re-check every cited page directly from the exact approved PDF before any new decision. Current tools read one page at a time and keep only concise decision evidence.

## Verified production read-only snapshot

- 159 pathology questions total: 1398 = 20; 1399 = 19; 1400–1405 = 20 each.
- All 159 have a reference source record and a chapter node, but **120 lack a page**.
- **119** older rows (1398–1403) cite Neville 4e in their edition/node keys while the anchor specifically says it came from **Neville 5e as a nearest-edition substitute**. All 119 lack the original-edition page. These are legacy/provisional mappings, not newly verified 4e evidence.
- The remaining missing page belongs to a **human-origin 1404** row. Do not change it without a distinct authorized human-review path.
- The 1398 Neville 4e catalog validity entry has official=false and no independently confirmed full syllabus. Twenty already-sourced 1398 pathology questions must not be counted as officially revalidated.
- Five assigned chapters are outside the recorded year-specific announced chapter scopes (one in 1399, two in 1402, one in 1404, one in 1405). The identities and clinical review status are in the private report; no answer or source was altered.
- Neville 5e (1404–1405) page checks fail closed until the chapter map covers every PDF page and is bound to the current PDF hash. Of its 40 existing source rows, **22 carry mechanically implausible small numeric page labels** (including 21 equal to the chapter number); these are audit flags, not verified corrections.

## Evidence progress using the actual Neville 4e book

The approved original Neville 4e PDF is verified at 878 pages, with 19 mapped chapters. A historical text-index pass searched each old stored anchor after stripping the legacy nearest-edition annotation; those results are leads for direct PDF page checks, not a substitute for them:

- 26 source phrases occur **verbatim within the source's currently assigned Neville 4e chapter**.
- All 26 matching PDF pages also passed a separate three-page (preceding/chosen/following) printed-label sequence check.
- Only **20** candidates also have a confirmed official-year edition and an in-scope chapter. Five belong to 1398, whose official syllabus is unresolved; one belongs to an excluded chapter in 1402.
- These 20 are **research-ready original-book page candidates**, **not approved source corrections**. Literal phrase presence alone is insufficient to prove the phrase supports the recorded official answer, especially for negative-choice, image-based, or ambiguous questions. An independent full stem/choices/official-answer/context check remains mandatory.
- For anchors taken from 5e that do not appear literally in 4e, search the original 4e by the clinical fact and official answer, not by a presumed quote or substitute chapter.

## Private reproducible checkpoint (not in Git)

Directory:
  /srv/fanoos/shared/research/classification/parallel/oral-pathology-review-20261010/

Artifacts: read-only database export script, 159-record private snapshot, original-edition literal-evidence audit script, adjacent-page label audit script, two JSON audit reports, and private README.

Verified content digests:

| Artifact | SHA-256 |
| --- | --- |
| Private 159-row read-only snapshot | 9d6b8efd99cb942e0f2b6488dcef0b2fd6e7ae2f01fb05534eca33efa435ac4b |
| Source-evidence audit report | d8a578a75fd4bb7a6ae1701bc7964c16d38ccc5b51493bd75db484cde3d6ae54 |
| 26 original-edition page candidate report | 2d01ef3b9a2d24e9548d649130b74f6dbfb7e5bb8b95d34bfc28c85ca314e33a |
| Approved original Neville 4e PDF | 6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6 |
| Protected on-host README | 83c272a516279d87878ec03616e8f771725a30ed1833befd75e9af1743ea4800 |

No private questions, options, official keys, book-page passages, PDF bytes, secrets or customer records are committed to this repository. Existing W04 research files were not changed.

## Publication safety and next actions

1. Reconfirm the 1398 *official* pathology source/scope from the original year notice. Never mark it verified from a partial change notice alone.
2. Inspect the approved original Neville 5e PDF page 1 and repair the chapter page-map front matter **only through a separate reviewed PR and tests** before continuing page-at-a-time clinical research. Never perform full original-text extraction.
3. Independently compare the 20 eligible 4e page candidates with complete current question, options, official answer, images and context read directly from the exact PDF page, then audit the other legacy questions the same way.
4. Investigate the five out-of-syllabus assigned chapters and all suspicious 5e page labels. Protect every existing human-reviewed record and keep uncertain evidence pending.
5. **Do not apply the source-only INSERT importer** to these questions: all 159 already have a source row. Any eventual source metadata correction needs its own reviewed, tested, readback-verified, exclusive and history-preserving update operation limited to reference/chapter/page, with original-edition evidence, a verified full backup and complete frozen-exam no-text/no-answer-change checks.
6. Record live receipts, CI, merge and updater deployment **only if those separate gates actually complete**. This documentation-only proposal itself does not modify production data and does not imply exam republishing.

This audit does not certify final pathology chapter classification. Its explicit purpose is to prevent legacy title/nearest-edition annotations and chapter-number page placeholders from being misreported as full original-reference verification.
