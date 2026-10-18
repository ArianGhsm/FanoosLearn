# Residency 1402: voided source locations recorded outside syllabus scope

**Checkpoint:** 2026-10-18 UTC. This records three source-only repairs for
officially voided questions. Their actual design locations are preserved with
an explicit note that the locations are outside the 1402 syllabus and that
the resulting source/syllabus conflict remains visible. No exam was published.

## Applied locations

| Question | Official reference | Printed page | Physical PDF page | Disposition |
| ---: | --- | ---: | ---: | --- |
| 60 | Little & Falace 9e, ch. 22 | 399 | 439 | Outside 1402 scope; conflict recorded |
| 102 | Zarb 13e, ch. 19 | 373 | 387 | Outside 1402 scope; conflict recorded |
| 103 | Zarb 13e, ch. 22 | 401 | 415 | Outside 1402 scope; conflict recorded |

Original-PDF audits accepted the source locations, printed labels, and exact
approved PDF hashes. The regular voided in-scope page operator correctly
refused these chapters because they are outside the official scope. Following
the owner's instruction to retain the actual book location and document the
scope conflict, the source rows were previewed and applied through the
versioned `mark_verified_outside_scope_sources.php` operator. Its live preview
revalidated the chapter map, original PDF page, neighboring printed labels,
scope disposition, and immutable question/choice/key fingerprints. Only source
location and outside-scope metadata changed.

The full verified backup was
`20261018T115909Z-b89dad56` (114 files); its manifest SHA-256 is
`bb558276111410d029377a102623fd4b1ac1fa99abec36ad40e16a03c1312dda`.
The protected original-page audits are:

- Q60 audit SHA-256: `f759e9855eebe9f9d147a357377af6d925fc69ba5ec9329e69b7ec9b13b8cf21`.
- Q102/Q103 audit SHA-256: `fdb5c084cd76c8aa36e10960f9f64da06b73d79ec85a49cb625acc88e6b4c780`.

Applied receipt SHA-256 values:

- Q60: `b40722fd32def68861d067d2db77b6f10d93d959a5e07567379eaf00e107f904`.
- Q102/Q103: `a0d57ba63dd752f39610c3be7f8b2d2fb67ef509427c803993f8e766dfbcc9f7`.

The independent live readback is protected at
`/srv/fanoos/shared/research/classification/reports/residency-1402-voided-outside-scope-independent-readback-20261018-v1.json`,
SHA-256 `892e4d8d03730a0067d6564d775f47fa93847976a451cc708aeae0a00925ce86`.
It confirms all three source identities and question/choice/key hashes match
their accepted audits. Every item remains `draft` with an official `voided`
key and null choice position. The 1402 assessment remains version 12 with 12
versions, zero attempts, and 12 reviews.

## Current whole-year status

The live official structural gate now reports **18 unresolved questions** for
1402, down from 21. It remains on hold. The other current structural results
are 1401: 5 unresolved; 1403: structural gate passes; 1404: 4 unresolved; and
1405: 4 unresolved. A structural pass for 1403 is not a scientific closeout;
its existing source/key holds remain open. No year was republished, and no
related issue was closed.

The user's sequencing condition remains in force: do not begin 1400 until
1401–1405 have each passed the full scientific and structural review. None of
the holds above is waived by recording an out-of-scope source location.
