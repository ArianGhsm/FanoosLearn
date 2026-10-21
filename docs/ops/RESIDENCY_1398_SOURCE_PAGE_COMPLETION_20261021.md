# Residency 1398 source-page completion — 2026-10-21

## Continuation status — 2026-10-21 08:43 UTC

PR #490 was merged as `c898e26f30708d210a62c9327c260b6ff9f6a897`; all five
required CI jobs passed. The production release is still
`f939bc031f213c1aa15e79d0888c4cace4cc604d` and `/health` is OK. No 1398
production source rows were changed: the latest readback remains 178/227
book-based rows complete, 49 source-free, and 9 existing source rows below
0.85 confidence. The rollback previews described below changed no live rows.

## Scope and live baseline

This continuation covers only the 1398 residency sitting. Before any database
apply, the live sitting had 247 questions: 20 English questions with valid
final/amended keys (reference-exempt) and 227 book-based questions. The last
independent live census was 178/227 same-row chapter+PDF-page+PDF-SHA sources,
49 source-free questions and 9 already-sourced rows below the 0.85 confidence
threshold. Re-read the database before each apply; these are dated counts.

The official 1398 catalog has 19 edition-validity rows. Eighteen exact PDFs
are ready in the private library; the only absent book is **Hupp Oral Surgery
6e**. Hupp 7e is an approved PDF with SHA-256
`35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83` and is
official for residency 1406. The owner instructed the agent to use newer
references for this 1398 completion. Accordingly, only the 14 1398 surgery
items that need Hupp use Hupp 7e as an explicitly labelled 1406 updated
reference proxy. They must be stored as `outside_scope_exam_question`, with a
note that Hupp 7e is not in the 1398 official reference list. This exception
does not change the 1398 catalog or answer key.

## Audited source-only batches prepared

All reports below are private and SHA-bound to the exact study, decisions,
validated pages, and current private reference PDFs. The guarded source-only
writer passed a rollback preview for every batch. **No production source rows
were changed while preparing this runbook.** Apply only after the reviewed
code is merged, CI is green, and the updater has deployed it.

| Batch | Questions accepted | Citation set | Independent audit SHA-256 |
|---|---:|---|---|
| 1398 oral surgery, official Malamed 7e | 2 | Q123 ch3 PDF110; Q124 ch11 PDF211 | `b2643a0bef3df19fe5108f74cb78701008b398ccde86761fee21231ac8a47633` |
| 1398 oral surgery, updated Hupp 7e proxy (reference year 1406) | 14 | Q110 ch11 PDF203; Q111 ch4 PDF60; Q112 ch3 PDF56; Q113 ch15 PDF298; Q114 ch19 PDF417; Q115 ch17 PDF373; Q116 ch23 PDF502; Q117 ch25 PDF547; Q118 ch24 PDF526; Q119 ch21 PDF452; Q120 ch26 PDF574; Q121 ch31 PDF672; Q122 ch2 PDF43; Q129 ch13 PDF237 | `fa57b42810d44f95672c6120ce3a60516613220c83ee587eafc270ed05589ea2` |
| 1398 pediatric dentistry | 16 | McDonald-Avery 10e; all proposed chapter/page pairs pass the current exact-PDF validator | `07ed170b580bf6e6d85a6675adf0fa38b20a3deec390080ef1b21d1950612bb7` |
| 1398 periodontics | 14 | Carranza 12e; all proposed chapter/page pairs pass the current exact-PDF validator | `e771af216b3b5638da31504d4af945304b1eb2b3d94ed7198456bfd138be67aa` |
| 1398 operative dentistry | 1 | Q188, Summit 4e ch11 PDF610 | `bfc5099f898a2f6687e691d4243388a8016a93a8fd516d56cdc71753b20baa90` |
| 1398 oral radiology | 1 | Q225, White & Pharoah 7e ch24 PDF457 | `0f3b75f96fa2c9679b07653304f8753245b0b99929a5160891a4d03a04a17c24` |
| 1398 prosthodontics voided Q97 | 1 | Shillingburg 4e ch22 PDF430 | `eaea25f22f5d6be09b53c24b10d3fd8a0846c45b18ecba1cb663d97dc5af6a8f` |

The 14 pediatric and 14 periodontal citations use the exact official 1398
editions. For each batch, the live writer rechecks question status, stem,
four choices, latest official key and absence of a source before insertion.
The Q97 plan is narrower: it allows only the exact existing draft question
with a null-choice `voided` answer, and changes only its source row.

## Answer alignment and invariants

Private independent reports record the conflicts found while locating source
pages. Examples include Q188's snowplow/key mismatch, Q225's radiology/key
mismatch, and the Q97 null-choice voided state. Pediatric and periodontal
reports also record mismatches or evidence limits question by question.
Every stored official key, question stem, choice, question status, frozen
assessment version, review, attempt and score remains unchanged. A source page
does not authorize rekeying an exam item.

## Code and deployment gate

The active release is `f939bc031f213c1aa15e79d0888c4cace4cc604d` and remains
healthy. PR #490 (`c898e26f30708d210a62c9327c260b6ff9f6a897`) adds the exact-page
1398 source-only gates, decimal Van Noort section-node support, and explicit
Hupp 7e updated-reference proxy labeling. CI passed 5/5 on both the PR and
merge commit.

The updater checkout is still on `codex/retention-two-20261019` at the older
release SHA and has eight pre-existing dirty files for backup-retention work.
The updater requires a clean canonical checkout. Those unrelated changes were
left untouched; no deployment request was queued and no release files were
edited. Deployment and all production applies remain blocked until that
checkout can be made clean through its own reviewed workflow. Do not bypass
the official updater.

After deployment, use a fresh verified full backup and apply each
batch with `scripts/references/import_verified_sources.php --case-root=...`,
the exact private audit SHA above, a unique receipt and independent readback.
The Hupp batch must retain `classification_basis=updated_reference_proxy`,
`reference_years=[1406]` and the outside-scope note in every inserted row.
Re-run the frozen-assessment preflight for the published question groups.
Do not create a new assessment version while any 1398 question remains
uncited or any citation is outside the 1398 official list without this
explicit proxy label.

## Remaining work

All nine low-confidence rows now have independent exact-page evidence and
protected plans; none has been applied. The five dental-materials confidence
repairs passed rollback preview against the branch's decimal-node map:

- Dental materials: Q241, Q246, Q247, Q248, Q250 (Van Noort 4e fractional
  sections; audit nodes such as `1.6` resolve to catalog keys such as
  `ch01.6`). Their plan SHA-256 values are Q241
  `9509c03c144408b34cbce4b96a357af73d48f393eca79368007f6e7e1e3ac9f2`, Q246
  `c336aa9243639b38721f98efb0886709b9b2ee2b554f00636875674d975df1be`, Q247
  `8ea6180068da45544158da8b4149f4a25d6177179f263f87fb9dcfa521dff0b6`, Q248
  `f1307cc226064e350df5ca431ed6f71d6177e69d4d47c08c729663c6c2bdfa4f`, and
  Q250 `f6825141636e69c699464f05d2e07e766776cfcce26c39cefcb4c9b2a712182e`.

The other four rows passed an independent two-batch audit (4 accepted, 0
pending; SHA-256
`41f81bc577cd421d41d428d770d28611dd84637eb5a2f94f8cd7c67acb4dcdde`) and an
exact guarded rollback preview. The private plan SHA-256 is
`1f73f5e1e33d5593bf086ba1a6db35bff7c0b1c5ce883d8b744e9216ba3c1ff1`; preview
receipt SHA-256 is
`e6b4c7d8249c59ee9605f5ed5b5cdcf4b2f57dd7d00ab33757fa76e8b796926b`.

- Q48: Burket 12e ch7, printed 153 / PDF 170.
- Q176: Sturdevant 7e ch10, printed 318 / PDF 331.
- Q180: Sturdevant 7e ch10, printed 347 / PDF 360.
- Q182: Sturdevant 7e ch5, printed 156 / PDF 169.

The audit records official-key conflicts for Q48, Q176 and Q182; every
official key remains unchanged. Q180's source supports its official key. The
source-only operator changes only citation and confidence metadata, and the
preview verified unchanged stems, choices, official keys, frozen assessment,
reviews and attempts.

In addition, the proxy citations above are complete source locations but are
not part of the official 1398 reference list. Keep that distinction visible
in the live source note, census and any future publication gate. The 49
source-free rows and nine low-confidence rows must still be applied and
independently read back before 1398 can be called complete. Do not publish or
close the sitting while either group remains.
