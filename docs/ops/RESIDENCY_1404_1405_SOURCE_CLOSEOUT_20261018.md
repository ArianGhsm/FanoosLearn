# Residency 1404–1405 source continuation — 2026-10-18

This checkpoint records the guarded source updates made after the 1401
continuation. It is a source-classification checkpoint, not authorization to
publish or republish either year.

## Code and production

- [PR #485](https://github.com/ArianGhsm/FanoosLearn/pull/485) added the
  guarded multi-page citation route for draft/voided questions; PR #486 fixed
  confidence parameter binding; PR #487 bound year/subject in the official
  chapter lookup. Their merge commits are `344d49a`, `0017e4f` and `cdd7f31`;
  each PR and post-merge CI passed 5/5.
- At the time of the Q2 source apply, production and updater checkout were on
  `cdd7f313b9083982ced7bb5b01cf31b123abe211`. The official updater request
  `01a150f1-a439-7235-9acf-fd51825d3045` succeeded. `/health`, the updater
  smoke check and `check-sync.sh` all passed.
- Pre-deploy full backup `/var/backups/fanoos/20261018T213913Z-128992b6`
  independently verified 114 files. Manifest SHA-256:
  `22fa3f6fcff0d169c62925ccc4236f63687c5b3284778e2da289f836315479eb`.

## Guarded source updates

All operations preserved question text, four choices, official answers,
assessment versions, reviews and attempts.

| Year / question | Recorded source | Disposition |
| --- | --- | --- |
| 1404 Q12 | Proffit 6e, chapter 16, printed page 544 / PDF 554 | Exact Carriere Motion and Class II elastic design location. Chapter 16 is outside the official 1404 list; the source row now says `outside_scope_exam_question` with a Persian note. Final key remains unchanged. |
| 1404 Q18 | Proffit 6e, chapter 18, printed page 582 / PDF 592 | Printed label and confidence vector 0.950 were accepted from the original PDF. Final key remains unchanged. |
| 1405 Q10 | Proffit 6e, chapter 9, printed page 277 / PDF 287 | Location recorded. The page conflicts with final key choice 3 about relative springiness; the key remains unchanged and the conflict remains a scientific hold. |
| 1405 Q19 | Proffit 6e, chapter 14, printed page 496 / PDF 506 | Location recorded; the original page supports the recorded treatment detail. |
| 1405 Q168 | Sturdevant 7e, chapter 13, printed page 477 / PDF 490 | The compomer passage is on the verified page and within the official partial chapter. |
| 1405 Q2 | Proffit 6e, chapter 2: printed pages 40–42 / PDF pages 50–52 | All three original pages are now cited. The question remains `draft`, and the official key remains `voided` with no choice assigned. |

Private plan, audit and receipt files are under
`/srv/fanoos/shared/research/classification/reports/`. The Q2 guarded bundle
receipt SHA-256 is
`65e4e434819aab4a9d5d121e00f89246b6fbb639374fed7a7c60f40b9528e74b`.
Its three source rows have the same approved PDF SHA and chapter; the original
row is primary and the other two are secondary. Independent readback report:
`residency-1400-1405-independent-readback-after-q2-bundle-20261018-v1.json`,
SHA-256
`f09d3650a6a832f50a4e0fd2acfe3049e808422334cd713670b6d57fd4b0a659`.
It confirms unchanged Q2 stem/options/voided-key hashes and assessment version
6, two attempts and six reviews.

Receipt and evidence digests:

- 1404 Q12: applied receipt `residency-1404-q12-carriere-outside-scope-applied-20261018-v1.json`, SHA-256 `01718a230b1c4d85ccfc5b05f834580b1f33c2b847e37ba3f449bb30999a4001`; guarded plan SHA-256 `54c556865d521d8dff95ca2701c4ba12fa7abd52ad6cca865bcc7fedde9dab6b`; source audit SHA-256 `6a0ba39334cfb783c315395301bbc778182064671f975129297242b8392254a1`.
- 1404 Q18: applied receipt `residency-1404-q18-retention-confidence-applied-20261018-v1.json`, SHA-256 `4d558b29f7d7896ff03f6d81dd3103710499a70c3095f2c27a721ad669bcbfef`; plan SHA-256 `b8a08c8cd59b4394c7db0c5bc3dcec4f4735e43adf434b78a228e2a6ca7c279b`; accepted provenance audit SHA-256 `92951ab9ba21c02ef6d2df7e04d5b25dec3a0016ed658ef67a33be5f01ef92b2`.
- 1405 Q10/Q19: applied receipt `residency-1405-orthodontics-printed-labels-20261018-applied-v1.json`, SHA-256 `1ddc62cf50beb9f3788d99b4da000bb562da057ddcf4c83243229a6fbe92a31c`; plan SHA-256 `7ba70591e2389bca37d7b0407fed34dfcc9b580f96b2f3a8d1a50412f1eeb04d`; audit SHA-256 `35aad921c19e4ef5fbc953b3a3a6b6bcf71af7a1865736ec277546af91032257`.
- 1405 Q168: applied receipt `residency-1405-operative-dentistry-printed-labels-20261018-applied-v1.json`, SHA-256 `18f11d1f3343e8d3efd83ecdc5f01aa56023109857f53bccab7c8d60544d1b29`; plan SHA-256 `a9ee49e2c0bb9f294d2fefc665e80874ed8629545e0a53bcd369fd1c3095c972`; audit SHA-256 `7e426b5f58487564f8304607496d32c8775bd7846f25a39843814119c88d7aec`.
- 1405 Q2: applied receipt, plan, bundle audit and single-question provenance audit SHA-256 values are respectively `65e4e434819aab4a9d5d121e00f89246b6fbb639374fed7a7c60f40b9528e74b`, `6bd96df24ec87d115fb03a98f729f18833b0636b3113dffad7628822c49f6b60`, `fc4eb6bad31629809f6833cae645deb5a66f71b98be5afd52b13a26afd8103b7`, and `a4e8ea64a25c927c4b221668675b194483d7c8a67f9c588f2107731977902594`.

The first four operations used verified backup `20261018T205941Z-cd4d12a1`
(114 files; manifest SHA-256 `63150affd7fad20f55d0cbe1ee0f5f7423d35cb65f2229e35fa5478a8bffdfe3`).
Q2 used verified deploy backup `20261018T213913Z-128992b6` (114 files; manifest
SHA-256 `22fa3f6fcff0d169c62925ccc4236f63687c5b3284778e2da289f836315479eb`).

## Current structural gates

The actual deployed whole-year gate now reports:

| Residency year | Structural result |
| --- | --- |
| 1400 | HOLD — 139 incomplete |
| 1401 | PASS |
| 1402 | PASS |
| 1403 | PASS |
| 1404 | HOLD — Q164 |
| 1405 | PASS |

The 1405 pass is structural. Q10's recorded key/source conflict still needs
scientific resolution before that year can be called fully reviewed or
republished. No assessment version was published or changed in this
continuation.

The PASS values for 1401–1403 are likewise structural only; their earlier
documented scientific and answer-alignment holds remain in force. Issue #422
was already closed for its separate 1405/login scope and was not reopened.
The published 1405 assessment remains version 6; no assessment was published
or republished in this continuation.

## Remaining blocker and sequence

1404 Q164 remains on hold. Its current Summit 4e chapter 9 / PDF 438 source
has no visible printed label, and the adjacent-page sequence does not
corroborate a label. Since the official 1404 scope is partial at printed page
217, `scope_unverified=true` remains correct. A separate original-PDF screen
of the allowed Sturdevant 7e chapters found no decisive alternative for the
keyed high-vapor-pressure statement. Private alternate-reference screen:
`residency-1404-q164-alternate-official-reference-screen-20261018-v1.json`,
SHA-256
`37be9e965b0126f811f9597eb578c0fee4fe584e4d6726f3deaed75159694057`.
Do not guess a printed page or mark it outside scope without evidence.

The sequencing precondition for starting 1400 is not met while 1404 remains
incomplete and scientific holds remain in the 1405 review. Preserve the
current source and attempt records; continue from Q164's exact original PDF
scope evidence when a verifiable printed-page or answer-bearing alternative
is available.
