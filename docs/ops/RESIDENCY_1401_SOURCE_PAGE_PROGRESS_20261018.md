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
- PR #457 lets the guarded AI chapter-correction operator validate several
  exact page fragments independently, preserving the four-word minimum for
  each fragment. Merge commit `15cd6404f58e5514004f160a85a0ff6966a815eb`;
  all five CI jobs passed. Deploy request
  `01a14c74-991d-70de-8d17-950c7fe6418d` succeeded.
- The live release and updater checkout are
  `15cd6404f58e5514004f160a85a0ff6966a815eb`.
  `/health` is healthy and `check-sync.sh` reports in sync.
- Source repairs used verified full backups: `20261017T223754Z-54de61f4`,
  `20261017T224521Z-ab1cb215`, `20261017T232008Z-1b462620`,
  `20261017T233728Z-29fca03c`, `20261017T235448Z-56d03b46`,
  `20261018T002016Z-d98240b3`, `20261018T004643Z-1b2d79df`, and
  `20261018T005923Z-b3552a10`. Each backup was verified with 114 files.

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
| 82 | Zarb 13e, ch14 | 309 / 323 | .980 / .980 / .980 | Corrected the legacy ch12/page264 location. The chapter explains static mounting of the casts for a mandibular RPD opposing a maxillary complete denture. |
| 84 | Zarb 13e, ch7 | 139 / 153 | .980 / .980 / .980 | Heat-activated PMMA reaches water saturation more slowly because its water-diffusion coefficient is lower. |
| 91 | McCracken RPD 13e, ch11 | 136 / 149 | .900 / .900 / .900 | Corrected the stored printed page 133 to 136; the page explains cervical convergence and retentive undercut. |
| 93 | McCracken RPD 13e, ch12 | 150 / 163 | .930 / .930 / .930 | Corrected the legacy ch15/page207. The figure caption explains use of guide planes to establish a path of insertion. |
| 94 | McCracken RPD 13e, ch6 | 61 / 74 | .970 / .970 / .970 | An internal rest removes the visible buccal clasp arm, supporting the keyed esthetic advantage. |
| 96 | McCracken RPD 13e, ch10 | 125 / 138 | .980 / .980 / .980 | Corrected the legacy ch13/page186. Multiple exact fragments across the two-column page establish the Co-Cr splint-bar material and soldered attachment. |
| 98 | McCracken RPD 13e, ch18 | 252 / 265 | .980 / .980 / .980 | Corrected the legacy ch19/page271. Multiple exact fragments establish the existing denture remount at initial delivery after processing. |
| 100 | McCracken RPD 13e, ch24 | 314 / 327 | .990 / .990 / .990 | Corrected the legacy ch25/page329. Figure 24-5 matches the keyed ball clasp and second-molar circumferential clasp without occlusal interference. |
| 114 | Carranza 13e, ch16 | 241 / 608 | .880 / .880 / .880 | Inference is explicit: the page describes protective IgA/IgG/IgM antibody roles but does not directly name IgE as absent. The official key is unchanged. |
| 118 | Carranza 13e, ch25 | 332 / 743 | .880 / .880 / .880 | Inference is explicit: the figure caption identifies periodontal-ligament-space widening and the adjacent figure marks injury/repair stages; no standalone sentence states the equivalence. |
| 122 | Carranza 13e, ch52 | 556 / 1193 | .980 / .980 / .980 | Table 52.1 lists excellent oral absorption for amoxicillin. |
| 126 | Carranza 13e, ch64 | 653.e1 / 1441 | .990 / .990 / .990 | Corrected the legacy ch35 assignment. The chapter abstract directly contrasts guarded-to-poor prognosis for furcation involvement with fair-to-good prognosis for equivalent attachment loss on a single-rooted tooth. |
| 130 | Carranza 13e, ch72 | 716 / 1606 | .960 / .960 / .960 | Table 72.1 associates controlled history of periodontal treatment with posterior bitewing examinations. The printed-page row alignment was checked on the original page. |
| 136 | Hupp 7e, ch15 | 283 / 297 | .990 / .990 / .990 | Corrected the printed label 282 to 283. The page sets the no-modification threshold at a stable implant gap under 1 mm. |
| 137 | Hupp 7e, ch17 | 338 / 352 | .970 / .970 / .970 | Corrected the stored ch16/page322. The page connects buccal-space infection with a cutaneous sinus tract. |
| 142 | Hupp 7e, ch26 | 585 / 599 | .980 / .980 / .980 | Corrected the printed label 556 to 585. The page lists DO's stability/TMJ/neurosensory advantages and separately notes its two-procedure requirement. |

The source PDF SHA-256 values are Burket 12e
`ebb1c0a98c24b21778f19e038127402a205b672ae6a31356eee722c27d199ab7`, Little &
Falace 9e `d01cae23d09cd39eccbe3577765196dc5d3a50261af9bb3de3c09ad3a7b15cc0`,
Neville 4e `6fbc9bcba9003deda2f8fc006ccc4f23f9e15db0bcd0e237c56ddf6788578bb6`,
Proffit 6e `5f18cc196553b691635b0c136f9761a4e7c478bf115424d1c7f26f04b5b50015`,
Zarb 13e `bcf92916cd0a01915695d1cb5aad8c51e7f2dac5909477c4df4454d229156a3d`,
McCracken RPD 13e `4e05bee57d1066601b8a2b30c2ae72d4a5bf65298747b600ae3fdd8248a192d9`,
Carranza 13e `f0e411898ae010688ca5c0d21afe312cef6f5dae86d0e2e45648bc51ca8e2adf`,
and Hupp 7e `35e3c37fec67a831a4464205c1817cc1e51c0a9f8a0bd8660a1881ecd2f10a83`.

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

### Additional guarded applies on 2026-10-18

All receipts are private under `/srv/fanoos/shared/research/classification/reports/`.
Each line gives the applied-receipt SHA-256; the source-only operators report
zero question, choice, and answer changes.

- Q82: `6f4b2ec2c18ff9a86f95ad72eed5c6e4dbe2f115fbf6f0cfd8135c6098735a25`;
  Q84: `0fef4aa226cc150338df0a509b2d070437829fab7b45c6aa28a928f3215b72d4`;
  Q91: `8db8c59fb372afbada13c332aa5f3074e3da7dc90fb0cbbc8ec123e9e5fae507`;
  Q93: `f427e7ecc7bb290ce64622d293a6b7f892e62b283e3c8ff8d7e3455922c0775a`;
  Q94: `05fbd5e1f36cc8c1062e09806854829286a111f560be7d0da46e37a336a647f2`.
- Q96: `88b212e90ffaa7384a4374fec1232199eeb263e8b88b3321f7418e47b8607b59`;
  Q98: `fb345792995468e3cff37ba2fe751b55916f7cdd4e1988129bff39ce1bb1d458`;
  Q100: `7349b82fa7c018bd864783b81aea04cb29db9eb8e4009487815470ae18f5fea7`.
- Q114: `4ee1a2309bf449174adae888cb1a61223f773c5e4a51a27df6317528d62c5c04`;
  Q118: `21e4bc4c015587753b4a65ecb7ed75330971ab5aaa3fa85bb97ae50aad76b73b`;
  Q122: `c56d1009592dff64e814d80582d2db6a819c5ae9ad51edb2843c655f1f1d018e`;
  Q126: `222c7491cfb1bc883e4ada8c378f38e8b3d5237dac86a31bab6cf5421a578084`;
  Q130: `e5ed14df5137a3f4f74dafa811523cd73e1b7763c0fd20a11bb8ec5dd395ba27`.
- Q136: `892f88f845dfcb9750556abb8027324979e0e990afc94f05296d32fdf82c6141`;
  Q137: `245d59413b7ea3018d375c68b687a2f6b0c5c4ab29e7b60c07c8cd205dcbb8ee`;
  Q142: `57e9857a663bc32e91232890ae4eebc1f3760ce6ab7211e08f70765d24dc15a6`.

Independent live readbacks for the additional rows:

- Q82/Q84/Q91/Q93/Q94: `087ccbf708e35ea90d5869468298fdf62f24c043ac528faccb3740f3c5936fb7`.
- Q100: `9dc25c526db30e0c2e1f0835bd3e571758df2447bb3c1bb46cbef828bb935b41`.
- Q96/Q98/Q126: `c8331d5d55f737497b3d231fc8e8b4da1921481aba3bce3fe9e645cca78a3233`.
- Q122/Q130: `05e735ec66826b68c2e91f62182bc2d4ae5da0f33e9619e39998a959e09eb6e1`.
- Q118: `e7e7516fa702f21687de91a745c5548d3e77cee248c41a54466973c33f6000da`.
- Q114: `985083b6fca23576fe8575476cafb6348788c29cca13f3ce2af6e5c3e8fa4446`.
- Q136/Q137/Q142: `d13c2e94af23bd628ce5cbb91bce85bdc80e86104b9f7f42c0762111ef4c2d`.

The applied groups used independently verified backups `20261018T002016Z-d98240b3`,
`20261018T004643Z-1b2d79df`, `20261018T005923Z-b3552a10`, and
`20261018T012037Z-fee7f4ab` (114 files each).

## Whole-year state and next work

The latest live official gate reports 249/249 questions in the 1401 inventory
with **24 incomplete**. The 1400 inventory remains 250/250 with **139
incomplete**. 1399 has not started. Neither 1401 nor 1400 is ready for
publication, and no new frozen version or issue closure is authorized.

The current 1401 gate examples are Q123, Q128, Q146, Q147, Q150, Q151, Q160,
Q165, Q168, Q172, Q173, and Q174. Q123's exact answer-bearing phrase is split
in the PDF text layer into a two-word terminal fragment, so the four-word
evidence rule still prevents a guarded write. Q128's only located direct
answer is in an end-of-chapter review question, which cannot serve as source
evidence; the instructional pages have not yet yielded an answer-bearing
passage. Keep both pending and continue the other items independently.

The current 35-question batch has 11 applied items: prosthodontics 3/3,
periodontics 5/7, oral surgery 3/6, operative dentistry 0/4, pediatric
dentistry 0/9, and oral radiology 0/6. Oral-surgery Q146, Q147, and Q150
remain pending while their cross-chapter evidence is checked; preserve the
official keys and do not compress separate facts into an unsupported single
source.
