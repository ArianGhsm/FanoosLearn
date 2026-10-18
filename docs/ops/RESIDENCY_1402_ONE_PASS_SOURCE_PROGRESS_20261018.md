# Residency 1402: one-pass source-page progress, 2026-10-18

**Status: not complete; no new exam version published.** This is a live
continuation record after the owner clarified that source location is found in
one pass: identify the answer-bearing page in the exact official PDF and read
its chapter from that same edition's page map immediately. Do not create a
chapter-only staging result.

The binding procedure was merged in PR #472 as `75f78be31ee816f7a8bdd040c5856424645dd264`;
CI on the merge commit succeeded. It makes page-to-chapter lookup one decision
from the first pass. Later audit checks that decision; it does not repeat
page discovery. Protected backups, guarded source-only writers and production
readbacks remain required for bank changes.

## Source-only repairs applied

The current book-based editions below were available as verified private
PDFs. Each applied receipt reports zero changes to question text, choices,
official answers, frozen assessments and attempts. The independent readbacks
confirmed those invariants; 1402 remains on published assessment version 12.

| Question(s) | Exact edition and location now recorded | Scope / audit note | Verified backup |
|---:|---|---|---|
| 54 | Little & Falace 9e, ch. 1, print 3 / PDF 43 | Outside official scope; final choice 4 preserved. | `20261018T123425Z-d8f1963f` |
| 107 | Zarb 13e, ch. 9, print 192 / PDF 206 | Corrected the old ch. 5 assignment; key 3 unchanged. | `20261018T132849Z-a8da4d8a` |
| 164 | McDonald/Avery 11e, ch. 20, print 404 / PDF 410 | Corrected old ch. 4; exact page supports the mandibular canine root completing after primary molar roots; key 4 preserved. | `20261018T153354Z-0fe8444c` |
| 104 | Zarb 13e: primary ch. 5, print 59 / PDF 73; supporting ch. 4 / PDF 63 | Primary page supports thick cheeks affecting retention. Supporting page documents ridge-related stability/retention challenges; the specific knife-edge-to-stability mapping remains inferential. Preserve key 2. | `20261018T152150Z-546d4a00` |
| 243 | Van Noort 4e, ch. 2.1, print 65 / PDF 80 | Corrected old ch. 3.8; ch. 2.1 is outside official scope; key 4 unchanged. | `20261018T134023Z-983c8c8f` |
| 135, 137 | Carranza 13e: Q135 ch. 18, print 249 / PDF 624; Q137 ch. 3, print 25 / PDF 68 | Both exact pages are in scope. Q137's source confidence was raised to .950 after the accepted page audit. | `20261018T135515Z-b5810e84` |
| 139 | Carranza 13e, ch. 34, print 412.e2 / PDF 892 | Outside official scope; page links family history with aggressive-periodontitis predisposition. The first-degree-screening wording remains inferential; key 2 unchanged. | `20261018T140220Z-dbcd5fe9` |
| 138, 148 | Carranza 13e: Q138 ch. 27, print 350 / PDF 776; Q148 ch. 60, print 616 / PDF 1361 | Corrected old ch. 14 and ch. 65 assignments; both target chapters are in scope. | `20261018T141006Z-cfbf20da` |
| 167 | McDonald/Avery 11e, ch. 26, print 576 / PDF 579 | Corrected old ch. 18 assignment; the page explains the Rainbow stabilizer's ventilation and reduced hyperthermia risk. Key 3 unchanged. | `20261018T142207Z-321be524` |
| 112, 150 | Q112 McCracken RPD 13e, ch. 9, print 99 / PDF 112; Q150 Carranza 13e, ch. 64, print 655 / PDF 1443 | Q150 corrected old ch. 35 and the page directly identifies the distobuccal root resection recommendation. Q112's page records disuse atrophy but does not resolve the official choice-4 pain/sensitivity answer; preserve key 4 and keep this as a scientific conflict hold. | `20261018T143747Z-6269945a` |
| 151 | McDonald/Avery 11e, ch. 1, print 12 / PDF 25; supporting figure citation, same chapter, PDF 21 | Corrected old ch. 4; the text gives the numbering rules and the figure supplies the tooth-position mapping. Key 3 unchanged. | `20261018T150211Z-c4c40d2f` |

Every listed full backup was independently verified with 114 files. Applied
receipts and independent live readbacks are private under
`/srv/fanoos/shared/research/classification/reports/`. Their SHA-256 digests
are:

| Question(s) | Applied receipt SHA-256 | Independent readback SHA-256 |
|---:|---|---|
| 54 | `87a1071a077bfe04feba5493a639da87763c51fc8f0b719eb9735568c79e192a` | `f0695a29647030e3f7d95bcf3b242ff88251187264d862d4ecae8fd3235207cb` |
| 107 | `95208504b171392155682bccc0c13f7dda21b1c02ac382e062ddf881bdc192e3` | `705afb1f439764acc61e28c9682c5b19763f193c8cc57bc2565cd7317b0bb894` |
| 164 | `3a8d8ada9526ad81afce50d0d7932566ac847ae2be5ce125673e2e40f491cf5a` | `c75c3205fe7aaad98dc8138c2f1378331761ea20539e03db79b823e80051ea79` |
| 104 | `910685a46d055bf05b6ed813282fb47e97d096c97126f0d5f7a63a878e5b9e90`; `045aef0329fead1a74469721d24e54bee11a8cbe629c7f901444311a52c2194f` | `8188e50f2ceb79805ffe63637ef144179716d5edb1e6524a54ac625fc84c49e7` |
| 243 | `29ac3194a34aabfcbf1dceec9b3c7c6cdad9760f1c3d788a7457c4d71f723a65` | `9d4331dabde384df8fab8181502a0d117903fe8cb2c94baf4f4a6832e54da9fa` |
| 135, 137 | `3ca0dbf8db63e1663ae2f551d17e5546ea7c447fedbf6ec91838bda3fd64ef53`; `6281fd0838218e5bf3ca4da1b371c993b07b2083c3543ddddc133f2221d34b71` | `81d1747322f2d253494da9ceaf8f072e4f46a994e1bbc7df99a2d4878d379ec0` |
| 139 | `32036d705f406a0717eac97e8b4eef2f35b719ea9c3b1964c0e334c184003a11` | `a1b37b6a46fbe04e38d49f219fdcebae3e7636399f8cbfcf7f6d69dc4c73a757` |
| 138, 148 | `9f947408558a30c6feba0097d6f89d85e398459bc07110aad6e845790281f755`; `d7552034ac29a420c92c08eb171f85150caa9bc4c110cc270e1b087884d3738a` | `3ebeed82dc29aeb0928d4c1f975e256f38b85b421d3825bc131a9415bab1b345` |
| 167 | `5e761fec3ed87c272e04ed1c0308f6f4017774c42ad0bcb8151d8da9e12b98b7` | `ce3bc7ce382fdb942fa976628e371e725791c7f5c7a7e928536964ecdef949d5` |
| 112, 150 | `cc09149e45f7a6093861f9495a5e7a2b146f870cb16eaa2588baf9bcad46fb77`; `a1f025d3a6b4c1650352d3bf3824c0490c9e72b3faf373e4c898b5acfc41b949` | `7ace20feaf9ecafde1594558e0318fc7e366e0e67f35c3c94f8e08db2add722d` |
| 151 | `b766e7b02be38e179d2e3b565fcc7e7a4885d188347cb1781c1f7559907dfe7a`; `4e59ff996be37ec851bd611ccf1d55edea1606954e45ff541312300d978345c0` | `2cdd9f227994fbcd94fc13cf2b60d39e8abfd860cab86946b6019765bd46bbe7` |

The readbacks confirm the frozen assessment remains v12, with 12 versions and
zero attempts.

## Current official gate and open items

The latest read-only production `ResidencyYearPublicationGate` census at
2026-10-18 15:35 UTC reports:

Private gate receipt:
`/srv/fanoos/shared/research/classification/reports/residency-1401-1405-gates-after-1402-q164-20261018-v1.json`, SHA-256
`8943b1fccd31a36c727e79cb6e8b1a3e71f254eb5ed499a051a79d0cfb52ae03`.

| Year | Inventory | Structural result | Remaining structural questions |
|---:|---:|---|---|
| 1401 | 249 | HOLD: 5 | 123, 128, 165, 168, 204 |
| 1402 | 250 | HOLD: 4 | 7, 175, 190, 244 |
| 1403 | 250 | PASS | Structural gate passes; the separate year-wide science review is not closed. |
| 1404 | 250 | HOLD: 3 | 12, 18, 164 |
| 1405 | 250 | HOLD: 4 | 2, 10, 19, 168 |

For the remaining 1402 questions, the exact work is:

- **Q7:** Torabinejad 6e ch. 6 PDF 115 introduces the AAE difficulty form but does not establish that taurodontism maps to the keyed moderate category. Keep the answer-source relationship pending.
- **Q104:** The two source pages are now recorded. The key is preserved, but assigning the knife-edge-ridge clue specifically to stability remains inferential, so keep it on the year-wide scientific review list.
- **Q175:** Summit 4e ch. 9 PDF 441 supports three-step etch-and-rinse as the gold standard. Its page and neighboring pages do not expose a corroborated printed label, so the partial-chapter cutoff at print 217 cannot be evaluated.
- **Q190:** Craig 14e ch. 13 print 277 / PDF 288 directly discusses the 20–30 second etch for deciduous unground enamel. The existing row is Summit 4e ch. 9 PDF 450 with no corroborated printed label. The current approved writers cannot replace the edition on this already-bound row, so no alternate write was attempted.
- **Q244:** Craig 14e ch. 9 print 154 / PDF 165 discusses bulk-fill materials and gives filler volume percentages; the official item compares a weight percentage. The existing source points to Van Noort 4e ch. 2.2, outside the listed 1402 sections. The answer/source alignment and guarded edition replacement remain unresolved.

The corresponding remaining questions in 1401, 1404 and 1405 also have exact
page-label, answer-source or figure-evidence holds recorded in their current
private research files. Structural completion of 1403 is not a scientific
closeout. No year was republished, no issue was closed, and the 1400 sequence
has not started. Keep the existing frozen assessments live and unchanged.
