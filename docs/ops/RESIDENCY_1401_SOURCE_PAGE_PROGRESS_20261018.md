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
- PR #459 added the guarded supporting-source operator for questions that
  already have one exact-page source. Merge commit
  `40b9697504565679b57744689844ae8b770cc824`; merge-commit CI passed 5/5.
  Official deployment request `01a14d42-f475-7120-98cd-1fa4de4507e3`
  succeeded, and the public `/health` plus updater sync check both passed.
- PR #460 added a guarded source-free outside-scope insertion route for exact
  official-edition locations in wholly unlisted chapters. Merge commit
  `f4867898b8669c3f54f16ad41b20e510f4f16fef`; CI passed 5/5 and deployment
  request `01a14d78-b7aa-7f6b-9280-ea826c553e0c` succeeded.
- PR #461 updated this public progress checkpoint. Merge commit
  `acbb93a26976bfe08f66feb03c4f39d6a3463d4f`; merge-commit CI passed 5/5 and
  deployment request `01a14d97-3308-74d9-9459-8495113f003e` succeeded.
- The live release and updater checkout are
  `acbb93a26976bfe08f66feb03c4f39d6a3463d4f`.
  `/health` is healthy and `check-sync.sh` reports in sync.
- Source repairs used verified full backups: `20261017T223754Z-54de61f4`,
  `20261017T224521Z-ab1cb215`, `20261017T232008Z-1b462620`,
  `20261017T233728Z-29fca03c`, `20261017T235448Z-56d03b46`,
  `20261018T002016Z-d98240b3`, `20261018T004643Z-1b2d79df`, and
  `20261018T005923Z-b3552a10`. Each backup was verified with 114 files.
  The Q147/Q150 group used deployment backup `20261018T043039Z-79da43a6`,
  independently verified with 114 files.

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
| 147 | Malamed 7e, ch13 | 213 / 230 | .900 / .900 / .900 | ASA page supports anterior teeth and premolar coverage in 72% when the MSA nerve is present; the official four-injection count is an inference, caveat preserved. Supporting source ch13/PDF222. |
| 150 | Malamed 7e, ch14 | 255 / 272 | .950 / .950 / .950 | Existing primary source retained; supporting sources ch14/PDF273 and ch13/PDF225 directly support the two techniques' no-bone-contact statements. |

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

### PR #459 deployment and Q147/Q150 supporting citations

PR #459 added `scripts/references/insert_verified_supporting_sources.php` for
published residency questions with exactly one existing source row. The new
operator preserves the primary source and appends one or two audited secondary
citations only after matching the immutable question/choice/key fingerprints,
official year scope, exact PDF hash, mapped chapter and page evidence. PR and
merge-commit CI passed 5/5. Merge commit `40b9697504565679b57744689844ae8b770cc824`
was deployed by request `01a14d42-f475-7120-98cd-1fa4de4507e3`; live health is
`ok`, and updater/live/main are in sync.

- **Q150** — primary Malamed 7e ch14, printed 255/PDF272; supporting citations
  ch14/PDF273 and ch13/PDF225. Applied page receipt SHA-256
  `6f6b47781ab621d561d13f53cd18729d531e076461ee795d208422b69859b852`;
  supporting-source receipt SHA-256
  `c09739cb2da86e0927808da196a75c38ebd2f4943159bda3c1e119f34899b7d2`.
- **Q147** — primary Malamed 7e ch13, printed 213/PDF230; supporting citation
  ch13/PDF222. Applied page receipt SHA-256
  `a02cfc604e4b8e1de6a36e16ac2b4db6e84aeb1647f8c65d89b4d3d3acc1a37d`;
  supporting-source receipt SHA-256
  `86674cd16afc6b781d455adb2d03fb1cd5e11eab89e9581c19c8b5fbe0f3ae20`.
- Grouped independent readback
  `residency-1401-malamed-q147-q150-supporting-independent-readback-20261018-v1.json`,
  SHA-256 `36ffd35c24618fa4a11d10be96c5f7de62111788a985e52a5fa310b1af95ac81`,
  verifies all five source rows and exact PDF evidence. Stem, four choices and
  latest final keys remain identical; assessment version remains **6/249**,
  with six versions, zero attempts and six review records. All apply receipts
  show zero question, choice, answer and assessment changes.
- Q147's official key is unchanged. Its four-injection count is an explicit
  inference using the listed maxillary techniques and the ASA page's 72% MSA
  conditional for premolars. Carry that limitation into the final year
  scientific manifest.

### Pediatric Q172–Q189 source-page repairs

Nine previously existing AI-source rows had no physical PDF page. The
protected Nowak 6e audits and exact original-page validators passed, followed
by fresh rollback previews and guarded source-only applies. All nine rows now
bind the official PDF SHA-256
`80278bc5831dc36c040be27a39958ae1b0e8acd29a9b74a8d45e9b62abbf165f`.
Chapter corrections are Q172 (20 → 12), Q175 (3 → 26), and Q183 (30 → 31).

| Question | Edition / chapter | Printed / PDF page | Confidence | Applied-receipt SHA-256 |
| ---: | --- | ---: | ---: | --- |
| 172 | Nowak 6e, ch12 | 174 / 213 | .980 | `f645dd37005b12beff4768802614c27cca4b0218aed6e689b056df8ef37db162` |
| 173 | Nowak 6e, ch14 | 207 / 248 | .960 | `2ba8bef7fa779c10df36752b05407ad27200525402e78cf01e830ad423e2ea9d` |
| 174 | Nowak 6e, ch16 | 233 / 275 | .980 | `0b1baf6c1709afc2db326c3043680ef473ae5b3f3bcd84312abc7156283fd511` |
| 175 | Nowak 6e, ch26 | 380 / 437 | .980 | `b96f64bcebb4ccbdfde9aae10a1dfacc31af7bbd2f1bceb5c692cbbcf47a9a79` |
| 178 | Nowak 6e, ch23 | 345 / 398 | .900 | `5986ddd1f3e16dfb71d26de3ef80493e797da04c53f9d3098502dfe5a41538fa` |
| 183 | Nowak 6e, ch31 | 433 / 501 | .990 | `430ac425dcb7919904f3843e0ee033e1de9aafcd9e70cca7621bd3486a62ddbd` |
| 184 | Nowak 6e, ch32 | 456 / 528 | .950 | `2ffc62ee5130a3b99021a86c3a1a646d13f833c29af1f2faf3ae3de7f125b254` |
| 187 | Nowak 6e, ch34 | 487 / 561 | .960 | `1a6c4ef80587fd89481fd747355b8b506018146e306c2b486fc8af0646ace7cd` |
| 189 | Nowak 6e, ch39 | 589 / 672 | .990 | `24a8c8d8dd5aa7d6fb3105405c4a639e80f086f3d7c2b131e3e69898c8081990` |

The independent live readback at
`/srv/fanoos/shared/research/classification/reports/1401-pediatric-q172-q189-source-readback-20261018-v1.json`
has SHA-256
`7a9be5465686d1e0b88eae8f30c3831215df1f890a3d8683d0447942e432664e`.
It verifies all nine source IDs, editions, chapters, printed/PDF pages,
PDF hashes and confidence values; stem, four-choice and latest-key hashes
match the protected pre-apply plans in all nine rows. Current assessment
remains published version **6/249**, six versions, zero attempts and six
review records. The published-assessment preflight independently reports
the full 249-question content identical. Backup
`20261018T045302Z-b9f691be` verified at 114 files and was used by every
apply. The backup-retention run then reduced completed full backups to the
five-set limit.

Source location is now recorded for Q178 and Q184, but their answer
alignment remains a scientific hold: Q178's official antibiotic key is not
recommended by the cited figure, and Q184's cited page says 0.5% APF while
the official keyed option says 0.05%. The keys were preserved. The protected
hold note is
`residency-1401-pediatric-answer-alignment-holds-20261018.json`, SHA-256
`62fcc3f970032175d7e3291324c952fc3739f924d036ef11a8be34be077c99f2`.

### Oral-radiology Q193 source location

Q193's exact source location is White & Pharoah 8e, chapter 3, PDF page 119,
bound to PDF SHA-256
`228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`.
The private 6-question study audit accepted Q193 and left five questions
pending (audit SHA-256
`65d48fc6073372c0d2eb2e52afbd4c29eea5e8045e2dd995b84bddc6c9b9df10`).
The source-only apply receipt SHA-256 is
`b08b81808535354bebd93fcee1f19fe4ddaf125b66e160f49e28c4db10b40248`.
Independent live readback
`1401-q193-hormesis-independent-readback-20261018-v1.json` has SHA-256
`30d2635510182eee0fb07e6c114610e38bb48febe2d71d7fc41156c1964b63a8`.
The question, four choices and latest final key match the original study.
The key remains unchanged, but the keyed high-dose protective effect conflicts
with this page's discussion of possible low-dose protective hormesis; retain
Q193 as a scientific hold despite the recorded page.

### Oral-radiology Q192/Q209/Q210 outside-scope source locations

The user-approved outside-scope route is now active after PR #460,
merge/deploy SHA `f4867898b8669c3f54f16ad41b20e510f4f16fef`, CI 5/5. The three
questions were source-free; the exact White & Pharoah 8e original pages were
validated and recorded with `official_scope_status=outside_scope_exam_question`
and Persian notes that name the out-of-scope chapters. The approved PDF SHA
for all three is
`228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`.
None of the selected pages has a corroborated printed label, so printed-page
stays null and the physical PDF page is displayed in the source record.

| Question | Chapter / PDF page | Confidence | Alignment note |
| ---: | ---: | ---: | --- |
| 192 | 26 / 1422 | .900 | Page locates ameloblastic carcinoma but does not establish the keyed giant-cell differential; preserve the final key and keep the science hold. |
| 209 | 28 / 1554 | .980 | Page identifies retention pseudocyst and reports that it may disappear from imaging within days; the question's six-month interval is not stated exactly. |
| 210 | 27 / 1518 | .950 | Page places zygomatic-arch fractures about 1 cm posterior to the zygomaticotemporal suture; matching the keyed proximity is an inference. Preserve the key. |

The three-row source-only receipt SHA-256 is
`c1e95f5471f16e75992311e5bed4c10f223c9ec7e1e96d645c87ceb5b7209763`.
Plan SHA-256 is
`9ee86e311383a42b710cb9985741a07ab85d5374c1c159cec07608deb9dc5a1e`;
the independent 6-question PDF audit SHA-256 is
`43f857ef68c90845f024f70f3a770152e1cf86ff2fbd724dd8079d2178ec6520`.
Independent live readback
`1401-open35-oral-radiology-outside-independent-readback-20261018-v1.json`,
SHA-256 `5af5969843c5ffc503c52498c5eebec409941fa159a580cb3ddaef89427b199a`,
passes all three source and immutable content checks. Assessment remains
6/249 with six versions, zero attempts and six reviews; the published-content
preflight reports all 249 question definitions identical. Apply backup
`20261018T053007Z-b63c3b12` verified with 114 files.

### Operative Q151/Q160 printed-page labels

The existing Sturdevant 7e chapter-1 rows already had exact PDF pages and
SHA. The guarded printed-label-only operator filled the two labels after
checking the original PDF target page, adjacent-page sequence, partial scope,
source identity and immutable question/key fingerprints. It changed only
`printed_page`:

| Question | Edition / chapter | Printed / PDF page | Confidence |
| ---: | --- | ---: | ---: |
| 151 | Sturdevant 7e, ch1 | 11 / 24 | .900 |
| 160 | Sturdevant 7e, ch1 | 4 / 17 | .950 |

Apply receipt SHA-256
`38ab04a7f98c7ffa193bf759815e3eecf24239258f4314c4f6a52bb7d36947b4`;
independent live readback
`1401-operative-q151-q160-printed-label-independent-readback-20261018-v1.json`,
SHA-256 `4831c64af5cafc1f6d3d15e8feeac868cbeb9371c407ad9275daa352b0d19964`.
Q151's page relates cementum thickening to attritional wear; identifying the
cellular subtype uses its apical association and remains an inference. Q160's
page directly states that enamel solubility increases toward the DEJ. Both
official keys remain unchanged. Backup `20261018T054935Z-3992ce78` verified
with 114 files.

Summit 4e Q165/Q168 remain pending: their bound PDF pages (476/438) have no
printed label on the page or adjacent searchable pages, so the partial
chapter-9 cutoff cannot be established. No printed value was inferred.

### Oral-surgery Q146 source-page repair

Q146's existing Malamed 7e chapter-18 source now binds printed page 333 / PDF
page 350, SHA-256
`cc668f86a731bed4c016feb1a3e20be033637c7d0a30da86358dbc9b2a4c08e7`.
Independent study audit accepted the exact page and original question/key
identity. The guarded repair added the PDF page/hash and raised the existing
source confidence vector from .750 to .900; the printed page and old anchor
were preserved. Receipt SHA-256
`709a97c8392b24d27a2c3222c578b25d2d8117ecc4d4860298eb7affe1ad63f7`, plan
SHA-256 `f4fb772768927c1d27d9e72a187b4355f8741316416a7b021454b3da03eccfe6`,
and audit SHA-256
`8ce02c03776c934868d8a7e0e34aebf4f7803a5a14c79f9b968add7280611453`.
Independent readback
`1401-q146-malamed-source-independent-readback-20261018-v1.json`, SHA-256
`346b44467e5319f35a89d1b35fbbfebf5a597d5becaacac361f13662eff57c7b`,
confirms the source row and unchanged final key choice 4. The cited page
supports the CO2/seizure-threshold relation, but does not resolve the whole
all-but-one item; keep Q146 as a scientific answer-alignment hold. Backup
`20261018T060323Z-0e476ec9` verified with 114 files.

## Whole-year state and next work

The latest live official gate reports 249/249 questions in the 1401 inventory
with **6 structurally incomplete**. The 1400 inventory remains 250/250 with **139
incomplete**. 1399 has not started. Neither 1401 nor 1400 is ready for
publication, and no new frozen version or issue closure is authorized.

The 6 structural gate holds are Q123, Q128, Q165, Q168, Q204 and Q208.
Q123's answer-bearing phrase is split
into a two-word terminal fragment, below the validator's evidence minimum.
The latest Carranza13e page re-read confirmed no alternate four-word exact
fragment; protected follow-up report
`1401-q123-carranza-followup-search-20261018-v2.json`, SHA-256
`afe3ef7e85c2e0d86eadbe895234e78c5dfbb2aa288b4df18ffcdc84dda0064c`.
Q128's only located answer is Carranza13e ch64/PDF1454 (printed 659.e1), an
end-of-chapter case-scenario question rather than instructional evidence;
private hold report `1401-q128-review-only-page-hold-20261018-v1.json`, SHA-256
`9c3204b8386158acad458f8216843e2fb124aaaf55ff90e74eb3e50793ed0a78`.
Q146 now has a
source page but retains an unresolved source/key conflict. Q192 now has its true chapter/page recorded,
but its page does not establish the keyed differential. Q193's high-dose key
conflicts with the page's low-dose hormesis discussion. Q210's keyed proximity
is an inference and remains a science hold. Q209's pseudocyst location is
recorded, with the duration caveat above. Q204 and Q208 still lack decisive
evidence for the exact projection and lesion distinctions. Q178 and Q184
have complete source-page rows but remain separate scientific answer holds.
The second in-scope-only search for Q204/Q208 is recorded in private report
`1401-oral-radiology-q204-q208-followup-search-20261018-v1.json`, SHA-256
`ee0855c73b652b681532c8e8143ba0092fecd14a1594b8d98483b29df9b60d7e`; it
found no decisive original-page evidence and made no assignment.

The current 35-question batch has 29 applied source rows: prosthodontics
3/3, periodontics 5/7, oral surgery 6/6, operative dentistry 2/4, pediatric
dentistry 9/9, and oral radiology 4/6. Q147 and Q150 have source receipts as
recorded above. Q146's source row is complete but its answer alignment remains
open. Preserve all official keys. No year
publication or issue closure is authorized.
