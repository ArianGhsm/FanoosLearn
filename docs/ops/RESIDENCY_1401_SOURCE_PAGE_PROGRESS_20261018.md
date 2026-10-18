# Residency 1401: source-page progress, 2026-10-18

This is the current public checkpoint for the 1401 whole-year work. It records
source locations and audits without publishing a new frozen assessment.
Question text, choices, official keys, human reviews, and attempts remain
unchanged.

## Code and server

- PR #452 added the protected source page/confidence writer; merge commit
  `ce7c43ee8429968961af5d6640d214765a5498c4`, all five CI jobs green, deploy
  request `01a14c00-2ba8-72f8-b056-2957645ace60`.
- PR #454 generalized guarded confidence-vector and chapter/page correction
  for residency years 1396–1500; merge commit
  `08aee36e4db0ddda0fdb9fba651a01db4bbdc21e`, all five CI jobs green, deploy
  request `01a14c1d-1145-7296-b99a-babdcecc3403`.
- PR #455 added printed-page persistence for verified pages in structured
  partial chapters; merge commit `dbfec5474164c99297f335b194ee64903d281801`,
  all five CI jobs green, deploy request
  `01a14c47-835c-74dc-90a4-6831e5065a14`.
- The live release and updater checkout are `dbfec5474164c99297f335b194ee64903d281801`.
  `/health` is healthy and `check-sync.sh` reports in sync.
- Source repairs used verified full backups: `20261017T223754Z-54de61f4`,
  `20261017T224521Z-ab1cb215`, `20261017T232008Z-1b462620`,
  `20261017T233728Z-29fca03c`, and `20261017T235448Z-56d03b46`. Each backup
  was verified with 114 files.

## Applied source locations

All rows use the exact official-year edition and SHA-bound private PDF.
Confidence is shown as source/node/page after the latest applied operation.

| Question | Edition / chapter | Printed / PDF page | Confidence vector | Note |
| ---: | --- | ---: | ---: | --- |
| 10 | Proffit 6e, ch14 | 470 / 480 | .900 / .900 / .900 | Printed page 470 is inside the partial chapter 14 allowance; excluded ranges are 462–465 and 472–476. The keyed translation statement is supported by the page's condyle movement description. |
| 41 | Burket 12e, ch4 | 75 / 92 | .900 / .900 / .900 | The page describes herpetiform aphthae. The question's 5–6 mm measure differs slightly from the source's under-5-mm description; preserve the final key and retain this note. |
| 43 | Burket 12e, ch4 | 60 / 77 | .970 / .970 / .970 | The page supports the recurrent-HSV clue in an immunocompromised patient; two literal evidence fragments preserve the PDF's column-order split. |
| 50 | Burket 12e, ch7 | 152 / 169 | .850 / .850 / .850 | In scope: chapter 7 permits soft-tissue pages through printed p161. The page places peripheral ossifying fibroma on gingiva. |
| 55 | Little & Falace 9e, ch4 | 66 / 106 | .980 / .980 / .980 | Corrected from the stored ch20; page supports naproxen after prior MI. |
| 57 | Little & Falace 9e, ch14 | 235 / 275 | .900 / .900 / .900 | The screening-criteria list supports the keyed oral-contraceptive exception by omission; keep the inference note. |
| 60 | Little & Falace 9e, ch24 | 450 / 490 | .970 / .970 / .970 | Corrected from the stored ch27; the page directs INR confirmation before surgery. |
| 64 | Neville 4e, ch5 | 174 / 181 | .970 / .970 / .970 | Oral tertiary syphilis with pseudoepitheliomatous hyperplasia. |
| 68 | Neville 4e, ch10 | 385 / 392 | .970 / .970 / .970 | Desmoplasia is identified as dense fibrosis; evidence was split at a column boundary. |
| 71 | Neville 4e, ch11 | 448 / 455 | .970 / .970 / .970 | The page links predominantly myxoid pleomorphic-adenoma stroma with increased recurrence. |
| 77 | Neville 4e, ch15 | 676 / 683 | .970 / .970 / .970 | The page identifies root resorption with central odontogenic fibroma. |
| 78 | Neville 4e, ch16 | 716 / 723 | .970 / .970 / .970 | The page identifies interleukin-6 in paraneoplastic pemphigus. |
| 80 | Neville 4e, ch17 | 772 / 779 | .850 / .850 / .850 | The page links candidiasis to iron deficiency. The keyed pernicious-anemia exception is supported by comparison across the chapter; the source does not state an explicit negative. Keep this key-alignment caveat. |

The source PDF SHA-256 values are Burket 12e
`ebb1c0a98c24b21778f19e038127402a205b672ae6a31356eee722c27d199ab7`, Little &
Falace 9e `d01cae23d09cd39eccbe3577765196dc5d3a50261af9bb3de3c09ad3a7b15cc0`,
Neville 4e `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`,
and Proffit 6e `5f18cc196553b691635b0c136f9761a4e7c478bf115424d1c7f26f04b5b50015`.

## Receipts and independent readbacks

The protected per-question plans, original-page reviews, applied receipts, and
readbacks are under `/srv/fanoos/shared/research/classification/`. Applied
receipt SHA-256 values:

- Q41: `1401-burket-q41-confidence-vector-applied-20261017-v2.json` —
  `8ac8f39a4d981bf04f5ff2af31287df0c2ba844fa0b969d541061d54564b79e0`.
- Q43: `1401-burket-q43-confidence-vector-applied-20261017-v2.json` —
  `5b9730398d7292a7dc6073715c9e3e6af1fde4d329bdfba5933b40854a659cac`.
- Q50: `1401-burket-q50-page-applied-20261017-v1.json` —
  `f10f5265cc21201a092b0b19c53224ba719da4ed2b640789d5546e03bfb10b3c`.
- Q55/Q60 chapter corrections: `1401-little-q55-chapter-page-applied-20261017-v2.json` —
  `d4969998a480a7eb8986cf9ec8900e0ec6001683634f6134acae45ab0ca52b6e`;
  `1401-little-q60-chapter-page-applied-20261017-v2.json` —
  `9d1d982cd9b3cd0588628236d9caeaae9e4a3357b4a63c1126ac9b27f1b3bd67`.
- Q57 page/confidence and printed label: `1401-little-q57-confidence-page-applied-20261017-v2.json` —
  `2ad6012a4371d02113ead3e1a8c519a9dddcb2ad242521e27ee90eae5cc3d554`;
  `1401-little-q57-source-page-applied-20261017-v1.json` —
  `36f6dc0ea617c55cff1a892b392687c6f13beac449a6f6529727b87f6e980d48`.
- Q64/Q68/Q71/Q77/Q78 receipts, in order: `3bd6b0c5657ae06fa67bcda570bec6d844426d8414d2ccb2e566f2c251d10702`,
  `149c033879768313701e957980dbb75914846459a2a4e22dcced8de139844fa0`,
  `934abea0c29ec0abb7b630542d6bb5a22fc45d022cf9826e655d0c7ca88de07f`,
  `535a0653f460c7be40b4ef682e46a35ca16106ca71c81746d4f29f19220da189`,
  `13a77cbeb3ab19ed7c39b19fe15e060602682cc62fd5ddcc2725fc1f76138613`.
- Q10/Q57/Q80 receipts, in order: `ea7471c7d4a43617b4003874a803fcde26f5e32553c4b38a7b7f1d2503932431`,
  `36f6dc0ea617c55cff1a892b392687c6f13beac449a6f6529727b87f6e980d48`,
  `07e00595c068d10705494727a6768ad7fa3f7021e7388181771a9e82272f0659`.

Independent readbacks: Q41/Q43/Q55/Q57/Q60 report
`3969bf7c98cdad8e61367814e0648f2446ae0ea03c397bea2ebefe3d6e4728e2`;
the Q41/Q43 confidence-hold follow-up report
`residency-1401-q41-q43-confidence-vector-followup-20261017-v1.json` has SHA-256
`5944fe1f60eb560bdf814d1db737c9ef5af092b2b7530b34efd9f5465328e093`;
Q64/Q68/Q71/Q77/Q78 report
`fbf78d203cf043064db657c9b3af6b3e5505133fc84c8d2e35a341cb31fc2543`;
Q10/Q57/Q80 report
`6b3cae1f81fc4783687166a6999285a12c1c71305ba5b98e16ad2501de8e2cff`;
Q50 report `52e2e49e888c8585b69638500e610379fadb2ea41ae256d4e86d1f77c2b063e0`.
They confirm unchanged question, four choices, latest official answer, and
published assessment version **6/249**. All source operators report zero
question/choice/answer changes. No new assessment version was published.

## Whole-year state and next work

The read-only official gate currently reports 249/249 questions in the 1401
inventory with **40 incomplete**. The 1400 inventory remains 250/250 with
**139 incomplete**. The 1401 sitting is not complete, 1400 has not been
started, and 1399 remains untouched under the user's year-order requirement.
Do not publish or close a year until its complete scientific audit and
whole-year gate pass.

The next 1401 gate examples are Q82, Q84, Q91, Q93, Q94, Q96, Q98, Q100,
Q114, Q118, Q122, and Q123. Q80's source page is now stored, but its key
relationship remains explicitly inferential as described above; do not hide
that caveat or change the official key.
