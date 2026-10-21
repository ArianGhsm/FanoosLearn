# Residency 1395 proxy reference PDF maps — 2026-10-21

## Purpose and policy

This change refreshes hash-bound chapter-to-physical-PDF-page maps needed to
classify Residency 1395 under the owner-approved retrospective policy. The
allowed basis remains the earliest subject-specific official reference year
from 1397 onward whose exact private PDF, chapter map, and page evidence are
verified. A later edition is not represented as an original 1395 reference.

The worktree started at `703da25442f8154efc0b2116d9f9817003e9ed22`, the
deployed main release. No bank rows were changed while building these maps.
PDF pages were read transiently from the approved private storage objects; no
PDF text file or search index was created.

## Maps rebuilt from the exact approved PDFs

| Official 1397 edition | Physical PDF pages | Chapters | PDF SHA-256 | Map result |
|---|---:|---:|---|---|
| McCabe & Walls Materials 9e | 615 | 30/30 | `1b3f8518dbdcaa979387308be32c827540474c7bd29e0c65c740cbc8fd1a9c1c` | contiguous; all chapters seen |
| Craig Restorative Materials 13e | 412 | 16/16 | `ce3d562839812c08d37c431307bcc0d7be8d04fc0ad78b6c78675f7411958b8e` | contiguous; all chapters seen; chapter 1 has weak direct page support |
| Sturdevant Operative Dentistry 6e | 756 | 23/23 | `6553a52e6db77092c2fa2d6349b3c7ade4b69d8a7ed246219855469ccaafddf5` | contiguous; all chapters seen |

Each final map passed `verified_reference_pdf.py --require-map` against the
current approved server PDF. Chapter presence in a map does not establish
that the chapter was in the official year-specific syllabus; the live
`bank_reference_validity.scope_chapters` check remains required for every
source citation. Partial printed-page ranges also remain subject to manual
scope review.

The current PDF maps for Burket 11e and Little & Falace 8e were not refreshed
in this change: the rebuilt candidates failed the chapter-coverage standard
(Burket had two unseen chapters; Little & Falace had 25 unseen chapters).
For 1395 research, use a later exact edition only when its own official
reference-year row, approved PDF, valid map, and chapter/page evidence pass;
otherwise leave the question pending. The incomplete candidate maps were
removed from this worktree.

## 1395 classification state

The independently read-back, applied source batches remain:

- Oral pathology: 20/20.
- Community dentistry: 10/10.
- Dental materials: 10/10.
- Endodontics: 20/20.

Their source receipts and independent read-back reports are stored under the
protected server research directory. Total confirmed 1395 book-source rows at
this checkpoint: 60. Classification and application continue in separate
30–40-question research cycles. The published assessment remains unchanged;
no whole-year publication is authorized until every book-based question is
classified and independently checked.

## Next gate

Merge and deploy this map-only change through the official updater after CI
passes. Then use the deployed code for guarded source-only previews and
applies, with a fresh verified full backup, unique private receipts, and
independent live read-back. Do not treat a chapter map alone as a completed
question citation.
