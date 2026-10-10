# Neville 5e original PDF physical page-map correction — 2026-10-10

Scope: Only Neville oral pathology 5e in data/bank/reference-chapter-pages.json. The original approved private PDF SHA-256 is 4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac; the actual PDF has 983 pages.

The historical map omitted first/last pages and included frontmatter or appendix as chapter content. Original page-by-page inspection found these precise chapter-opening PDF pages for chapters 1–19:

11, 61, 127, 157, 182, 211, 239, 282, 331, 364, 470, 524, 588, 628, 695, 757, 829, 871, 891.

Pages 1–10 are nonchapter front matter. Chapter 19 is pages 891–923; the appendix, prescriptions and final blank pages are 924–983, not chapter 19. The resulting map covers all 983 pages continuously, with null runs for nonchapter pages. The historical heuristic supported_pages scores were deleted for this edition rather than being misrepresented as recalculated checks after adjusting boundaries.

The original approved Neville5e PDF SHA-256 is `4d35199b9cda526997717802e174144071d38f0179e725e7ed6a90b2f98565ac`. A former temporary page-text derivative was removed during cleanup; chapter boundaries are checked from requested PDF pages and the stored map is bound to this PDF hash. Source local page-map hash: a3cf792a52d0d6d7ca8b0aaf9b54d9be61a04a4f035c0102f6cb8fe4198b6b89.

This mapping change does not authorize updating any existing source, answer, question or assessment, does not alter official 1404/1405 chapter scope, and is not evidence that all existing pathology citations are valid. Research audit identified 10 exact 5e source phrase matches with page labels validated against neighboring original PDF pages. The rest require specific clinical content review and independent original-book evidence. Details: RESIDENCY_ORAL_PATHOLOGY_REFERENCE_AUDIT_20261010.md.

No protected text or question content is committed. The regression test pins exact original chapter openings and the separate official syllabus scope.
