# Residency 1401 oral medicine: Burket Q41, Q43, Q50 source pages

This checkpoint records three source-only repairs. It does **not** complete
the 1401 sitting or authorize a new frozen exam version.

## Code, deployment, and backups

- PR #452 added the guarded AI source page/confidence operator and audit
  confidence binding. Merge commit `ce7c43ee8429968961af5d6640d214765a5498c4`;
  all five merge-commit CI jobs passed.
- Official updater request `01a14c00-2ba8-72f8-b056-2957645ace60` succeeded.
  Production, updater checkout, and `/health` report release
  `ce7c43ee8429968961af5d6640d214765a5498c4`; `check-sync.sh` is in sync.
- Q41/Q43 used the verified deploy backup
  `/var/backups/fanoos/20261017T223754Z-54de61f4` (114 files). Q50 used the
  newer verified backup `/var/backups/fanoos/20261017T224521Z-ab1cb215`
  (114 files), taken after Q41/Q43 and before Q50.

## Applied source rows

All three questions use the approved Burket 12e PDF, SHA-256
`ebb1c0a98c24b21778f19e038127402a205b672ae6a31356eee722c27d199ab7`.
The direct original-page audits and validators accepted the mapped pages.

| Question | Chapter | Printed page | PDF page | Source update |
| ---: | ---: | ---: | ---: | --- |
| 41 | 4 | 75 | 92 | Added `pdf_page` and PDF SHA; page confidence 0.750 → 0.900. |
| 43 | 4 | 60 | 77 | Added `pdf_page` and PDF SHA; page confidence 0.750 → 0.970. |
| 50 | 7 | 152 | 169 | Added printed page, `pdf_page`, and PDF SHA; existing page confidence 0.850 retained. Page 152 is within the official soft-tissue chapter 7 limit through printed page 161. |

Q41's source describes herpetiform recurrent aphthous stomatitis with more
than ten small ulcers below 5 mm; the question states approximately 10–12
ulcers at 5–6 mm. This size difference is recorded as a source/key alignment
note. The final key (choice 2) is unchanged. Q43's accepted page supports the
recurrent HSV diagnosis in immunocompromised patients; the PDF text layer
splits a word at a column boundary, so the private evidence record retains
separate literal page fragments. Q50's page states that peripheral ossifying
fibroma occurs on gingiva, supporting the keyed exception for a lateral-tongue
lesion.

## Audit receipts and immutable checks

Applied receipts:

- Q41: `1401-burket-q41-page-confidence-applied-20261017-v1.json`,
  SHA-256 `8957d8e178f85e7e889726ed3f6f35e7769708e16e272d95fc82ca2a423a7f05`.
- Q43: `1401-burket-q43-page-confidence-applied-20261017-v1.json`,
  SHA-256 `1723d586c75e3755b88262b45349f4f0a17a3323d8e7af4790fbb829698c14e4`.
- Q50: `1401-burket-q50-page-applied-20261017-v1.json`,
  SHA-256 `f10f5265cc21201a092b0b19c53224ba719da4ed2b640789d5546e03bfb10b3c`.

Independent production readbacks:

- Q41/Q43: `residency-1401-burket-q41-q43-independent-readback-20261017-v1.json`,
  SHA-256 `9a72c5504c4b337436c738990fa2ab40fbd01412c2b5bf3c5385b2220d787075`.
- Q50: `residency-1401-burket-q50-independent-readback-20261017-v1.json`,
  SHA-256 `52e2e49e888c8585b69638500e610379fadb2ea41ae256d4e86d1f77c2b063e0`.

The readbacks confirm that stems, four choices, and final keys match their
pre-apply hashes. The publisher preflight matched all three reviewed questions
to the 249-question frozen assessment version 6. No assessment version,
question, choice, official answer, human review, or attempt was changed.

The private original-PDF audits, plans, and receipts remain under
`/srv/fanoos/shared/research/classification/` on the server.

## Current gate and next work

The official read-only whole-year gate now reports 249/249 inventory and **50
incomplete** for residency 1401. 1400 remains at 250/250 inventory with **139
incomplete**. Both years are on hold; 1399 has not started because the required
1400 and 1401 completion condition is not met. The published 1401 assessment
remains version 6. Do not republish or close year-level work until every
non-English source is verified and the whole-year audit passes.

Next 1401 gate examples are Q10, Q55, Q57, Q60, Q64, Q68, Q71, Q77, Q78, Q80,
Q82, and Q84. Resolve each with the exact official edition, chapter, original
PDF page, scope boundary, and protected source-only workflow; preserve the
Q41 size note and all official keys.

**Confidence-vector follow-up:** the first Q41/Q43 page-only re-audit changed
`confidence_page` from 0.750 to the accepted score, but the existing
`confidence_source` and `confidence_node` values remain 0.750. These two rows
must remain pending for scientific completion until the accepted full
confidence vector is written through the guarded format-2 source page repair
and independently read back. The structural publication gate does not check
these confidence columns, so its 50-incomplete count must not be presented as
a scientific-completion count.
