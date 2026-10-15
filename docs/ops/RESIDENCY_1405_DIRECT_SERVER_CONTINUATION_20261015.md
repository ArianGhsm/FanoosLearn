# Residency 1405 direct-server source continuation, 2026-10-15

This checkpoint supersedes the **181 bound / 49 clinical incomplete** production
snapshot in `RESIDENCY_1405_ALL_SUBJECTS_PAGE_RESEARCH_20261015.md`. It records
actual production writes and independent live SQL readback. The original
research-only manifests and unchanged historical question content remain in
their protected server paths. No SentinelX route was used.

## Production release and backup

- Direct `sudo -n -u fanoosupd` access to `/srv/fanoos/shared/research` succeeded.
- The official updater deployed CI-green main `fb849fb869b4bbfb6cb486ec8b443b569fc7132a` via request `01a13f4c-be1b-78fb-b476-364bcdd7a668`. The live `/health` response reports `status=ok` and that exact release. Its approved Carranza 14e full-PDF chapter map was checked against SHA-256 `32f50c3c01d6de459b0fdf84a79e969ed99844605d742d5571392555ce074327` (1,876 original physical pages, 88 contiguous chapter runs).
- The official deployment's fresh full backup `/var/backups/fanoos/20261015T112438Z-8a529256` passed `scripts/ops/verify-backup.php`: 114 files, including the database dump. Every apply below used this verified backup and a separate protected plan, independent original-PDF audit, successful rollback preview and unique mode-0600 receipt.

## Verified source-only production corrections

The guarded `repair_verified_source_chapter_pages.php` operator corrected
only `bank_question_sources.node_id`, `printed_page`, `pdf_page`, and
`pdf_sha256`. Its input included pinned live question, four choices, final
answer, original anchor, source UUID, official-year chapter scope and exact
approved PDF quote. The operator requires AI-origin, unreviewed sources and
rejects a changed or voided answer. No question, choice, answer, assessment,
attempt, edition, legacy page, or anchor was edited.

| Subject | Q | Correct official chapter | Book printed page | Physical PDF page |
|---|---:|---:|---:|---:|
| Prosthodontics, Rosenstiel Fixed 6e | 105 | 7 | 232 | 243 |
| Prosthodontics, Rosenstiel Fixed 6e | 110 | 10 | 283 | 294 |
| Periodontics, Carranza 14e | 111 | 4 | 34 | 71 |
| Periodontics, Carranza 14e | 115 | 59 | 711 | 1232 |
| Periodontics, Carranza 14e | 116 | 5 | 72 | 124 |
| Periodontics, Carranza 14e | 117 | 8 | 107 | 231 |
| Periodontics, Carranza 14e | 120 | 32 | 458 | 777 |
| Periodontics, Carranza 14e | 121 | 41 | 546 | 909 |
| Periodontics, Carranza 14e | 122 | 16 | 237 | 431 |
| Periodontics, Carranza 14e | 123 | 62 | 755 | 1291 |
| Periodontics, Carranza 14e | 124 | 41 | 541 | 904 |
| Periodontics, Carranza 14e | 125 | 43 | 562 | 926 |
| Periodontics, Carranza 14e | 126 | **42 (was 45)** | 556 | 920 |
| Periodontics, Carranza 14e | 129 | **78 (was 79)** | 920 | 1618 |

Protected plans and applied receipts are in
`/srv/fanoos/shared/research/classification/reports/1405-{prosth,perio}-q<N>-correction-{plan,applied}-20261015.json`.
Each applied receipt says `mode=applied`, has the matching plan/audit digest,
identifies the above backup and records zero question, choice and answer
changes. Separate live SQL readback confirmed each row's chapter, printed
page, physical page and exact approved PDF SHA after application.

The live field-complete count is **195/250**: 20 English questions are
reference-exempt, and **35/230 clinical questions** remain field-incomplete.
Prosthodontics is **25/30**; periodontics is **12/20**. These counts do not
certify all previously bound rows against official year scope: historical
Q217 remains a separate out-of-scope scientific hold.

## Remaining prosthodontics work

- **Q81**: the original exam figure is present in private exam-image storage
  and was visually opened in this continuation. A figure-to-answer analysis
  and direct original-book page must still be independently audited in a
  one-question case before applying source metadata. Existing citation is
  McCracken 13e chapter 3, with physical PDF page 30 only a candidate.
- **Q85**: one-question McCracken 13e chapter 21 / PDF 308 original-PDF audit
  was previously accepted. The target page's printed label is not extractable
  by the deployed guard; nearby pages suggest 295 but cannot replace the
  operator's required target-page evidence.
- **Q87**: the exact 1.5 mm selective-pressure reduction claimed by the
  original answer needs direct answer-bearing evidence. PDF 312 is only a
  candidate, and its printed label is unconfirmed.
- **Q99**: the keyed small-tooth/single-denture exclusion needs exact
  original-book answer-bearing evidence. Zarb 13e chapter 13 / PDF 315 is
  only a candidate, with printed label unconfirmed.
- **Q100**: one-question Zarb 13e chapter 14 / PDF 323 original-PDF audit was
  previously accepted. As with Q85, the target page printed label is not
  extractable by the deployed guard; nearby 308 and 310 labels suggest 309,
  which is not itself sufficient under that guard.

The other eight periodontics questions retain independently accepted
original-chapter/PDF-page research cases but did not pass target printed-page
corroboration: **112, 113, 114, 118, 119, 127, 128, 130**. Keep their
production source fields unchanged until their actual book pagination is
proven under a reviewed publisher. Do not count academic research or visual
page inference as a production apply.

The two local work branches that held earlier squash-merged PR #408/#409
copies were removed after verifying the implemented files on main. Following
the deployment and this report's PR #410 merge, a separate exact-SHA inventory
matched 110 remote branch tips to their merged PR head commits; none had an
open PR. Those 110 completed branch refs were deleted with explicit Git
force-with-lease checks (15 residency-1405 refs and 95 older refs). A fresh
GitHub listing confirmed the branch count fell from **121 to 11**. `main`, the
open PR #388 branch, and nine refs whose tips differed from their merged PR
heads were retained for review; no other contributor's working tree was
changed. Branch deletion did not change the live release or database.

## Later direct-server continuation: four more live source corrections

This section supersedes the **195/250**, prosthodontics **25/30**, and
periodontics **12/20** snapshots above. PR #412 narrowly taught
`PrintedBookPageEvidence` to read a numeric book label at the end of the
bounded *first-line* `Chapter N` running header, even when that line is long.
PR #413 added the uppercase `CHAPTER N` form. Ordinary long body lines and
the requirement for at least two matching neighboring page labels remain
excluded/required. Both PRs passed five checks on the PR and exact merged
`main`. The official updater deployed the second change as release
`15dcd02079c8c62cbc2b921d47c132c06f025f3d`, request
`01a13fc2-2913-7e6b-bf92-f927735c4342`, state `SUCCEEDED` at
2026-10-15 13:33:34 UTC. Live `/health` and `check-sync.sh` independently
reported that exact release and GitHub-main agreement. No SentinelX route was
used.

| Subject | Q | Official chapter | Book printed page | Original physical PDF page | Source fields actually changed |
|---|---:|---:|---:|---:|---|
| Prosthodontics, McCracken RPD 13e | 85 | 21 | 295 | 308 | PDF page + SHA only |
| Prosthodontics, McCracken RPD 13e | 87 | 22 | 299 | 312 | PDF page + SHA only |
| Prosthodontics, Zarb Edentulous 13e | 100 | 14 | 309 | 323 | PDF page + SHA only |
| Periodontics, Carranza 14e | 112 | 5 | **63 (was 5)** | 115 | printed page + PDF page + SHA; chapter node unchanged |

The three prosthodontics original-question, four-choice/latest-key research
audits were accepted before publication. Q87's book passage gives general
relief of 0.5–1.0 mm plus *additional* 1.0 mm over the ridge crest; the
official answer's 1.5 mm minimum follows from those two quantities. Each
target PDF page's own book label, at least two neighboring labels, short
answer-bearing original-page quote, exact approved PDF SHA and official
chapter run passed the deployed guarded page-only operator. Protected plans
and one-row applied receipts are
`/srv/fanoos/shared/research/classification/reports/1405-prosth-q{85,87,100}-page-{plan,applied}-20261015.json`.
Each successful rollback preview preceded its applied receipt. The
independently verified 114-file full backup was
`/var/backups/fanoos/20261015T131958Z-e2153095`.

Q112's independent accepted one-question Carranza original-page audit is
`recovery-1405-perio112-20261015/classification/reports/1405-perio112-audit.json`,
SHA-256 `59c8898af928f676a42af102e2e8a7f188ceda0448ba756b2470995aae513289`.
Its exact PDF page 115 has uppercase chapter header and printed label 63;
two neighboring labels corroborated it. The protected plan
`1405-perio-q112-correction-plan-20261015.json` had SHA-256
`888dcda0927e5154df1e97ab7d2f4402ca88bb39a4e2fb890d235cb3cd91926f`.
The guarded chapter/printed/page operator accepted a rollback-only preview
and issued the one-row applied receipt
`1405-perio-q112-correction-applied-20261015.json` using independently
verified 114-file backup `/var/backups/fanoos/20261015T133304Z-44ff3ab0`.
Its unchanged original anchor, stem, four choices, final answer and chapter
node were SHA/identity-pinned. Both operators reported zero question,
choice and answer changes. Separate live SQL readback confirmed the four
source IDs, exact original-PDF hashes and page numbers after commit.

The current live **field-complete count is 199/250**: **20 English
reference-exempt** and **31/230 clinical questions field-incomplete**.
Prosthodontics is **28/30**, periodontics **13/20**. This is field coverage,
not scientific certification of every old row; field-complete Q217 still has
an out-of-1405-scope historical chapter. Prosthodontics Q81 remains on a
figure/official-key reconciliation hold; Q99 lacks a decisive original-book
passage for its keyed exclusion. Neither was changed.

The seven remaining periodontics questions are Q113, Q114, Q118, Q119,
Q127, Q128, and Q130. Original academic PDF/question audits are accepted,
but their publication checks are unresolved. In Carranza's original PDF,
several candidate pages carry insert-style book labels such as `108.e2`,
`136.e1`, `364.e1`, `364.e2`, and `373.e1`; the present printed-page field
and guarded operator accept numeric labels only. Q128 begins with a case
scenario, without a corroborated target-page numeric book label. Q130's
candidate PDF page 1505 says printed 845, but the neighboring pages include
845.e1 and do not provide the required two sequential numeric labels.
These labels must not be truncated to a fabricated integer or bypass the
pagination guard. This also means the 1405 classification file **cannot yet
be closed as complete**. A fresh remote inventory after PR #413 confirmed
**11 branches** remain: `main`, open PR #388, and nine differing-tip refs
retained for individual review.

## Final periodontics continuation: all 20 sources field-complete

This section supersedes the **199/250**, periodontics **13/20**, and seven
periodontics publication holds above. The approved original Carranza 14e PDF
uses actual inserted book labels, including `108.e2` and `779.e1`. Its
`bank_question_sources.printed_page` database field is `VARCHAR(20)` and
already supports exact textual labels. PR #415 added narrowly bounded
first/last-line extraction of these labels and requires the immediately
previous **and** next physical PDF pages to show the exact predecessor and
successor labels. The ordinary numeric two-neighbor check remains in place.
Both guarded source correction operators now accept the exact insert-label
syntax. PR #415 and its merged `main` commit passed all five CI jobs. The
official updater deployed release
`2c685cca2b28c71cd1149f259c28d1414656531e` through request
`01a13fcf-78bf-706a-8235-bd90fb08652f`, state `SUCCEEDED` at
2026-10-15 13:48:18 UTC. Live `/health` and `check-sync.sh` matched that
commit. The fresh full backup
`/var/backups/fanoos/20261015T134742Z-f1118c79` was independently verified
with 114 files.

| Q | Official chapter | Exact printed label | Original physical PDF page | Historical source correction |
|---:|---:|---|---:|---|
| 113 | 8 | `108.e2` | 235 | printed 1 replaced |
| 114 | 10 | `136.e1` | 279 | printed 10 replaced |
| 118 | 25 | `364.e1` | 606 | printed 25 replaced |
| 119 | 25 | `364.e2` | 607 | printed 1 replaced |
| 127 | 25 | `373.e1` | 621 | printed 25 replaced |
| 128 | 65 | `779.e1` | 1398 | printed 2 replaced |
| 130 | **72 (was out-of-scope 87)** | `845` | 1505 | chapter and printed 87 replaced |

The seven previously accepted independent original-question, four-choice,
official-answer and original-PDF audits were rechecked in seven protected
one-row plans. Each target label and its two adjacent original-PDF labels
passed the new verifier against the exact approved Carranza PDF SHA-256
`32f50c3c01d6de459b0fdf84a79e969ed99844605d742d5571392555ce074327`.
The guarded chapter/printed/page operator accepted a rollback-only preview
for **each** plan, checking its independent audit digest, immutable source
anchor, stem, choices and latest final/amended answer, official-year chapter
scope, bound chapter-page map and short original-page evidence. Seven
separate applies then used the verified backup and unique mode-0600
receipts. Plans and receipts are
`/srv/fanoos/shared/research/classification/reports/1405-perio-q<N>-insert-{plan,applied}-20261015.json`,
where `N=113,114,118,119,127,128,130`. The receipts report zero question,
choice and answer changes. Independent live SQL readback compared all seven
source IDs and every pinned source/question/choice/answer digest to their
plans, and confirmed the exact printed labels, chapters, physical PDF pages
and PDF hash after commit. Q130 alone changed its chapter node; the other
six retained their existing in-scope chapter nodes. The legacy `page` field
and all human review, attempts and published assessment history were left
unchanged.

The live **field-complete count is 206/250**: 20 English questions are
reference-exempt, and **24/230 clinical questions remain field-incomplete**.
Periodontics is **20/20**; prosthodontics is **28/30**. The remaining clinical
field gaps are pediatric dentistry 10, endodontics 3, oral surgery 3, oral
pathology 2, dental materials 2, prosthodontics 2, oral medicine 1 and oral
radiology 1. Historical field-complete Q217 still cites an out-of-1405-scope
chapter and is a separate scientific hold. Q81's figure/key ambiguity and
Q99's insufficient decisive original-book evidence remain unresolved. Thus
the **periodontics source-field backlog is closed**, while the full 1405
exam classification cannot yet be declared complete.

## Same-day continuation: seventeen more verified sources, 223/250 field coverage

This section supersedes the **206/250** field count above. No sitting import,
question/choice/key edit, assessment republish, attempt edit, or raw SQL source
update was performed. All source changes used the deployed guarded operators
with separate SHA-pinned private plans, rollback-only previews, verified fresh
full backups and unique mode-0600 receipts. Independently read-back source IDs,
chapters, original source-anchor and immutable exam-content hashes agreed with
the protected plans after commit.

PR #414 merged the previous periodontics handoff to `main`. PR #416 added a
bounded first-line parser for Torabinejad 6e's numbered, spaced `CHAPTER`
running header. PR #417 added bounded first-line patterns for McDonald/Avery
11e's alternating chapter/PART headers and Craig 14e's book-title header. All
three PRs passed five CI jobs. Merged `main` at `826c6942fbbf8ff877c62b42c1d4a96960357c17`
initially had a MySQL CI failure because the external MySQL container registry
returned `toomanyrequests`; rerunning that failed job passed, and all five
jobs were green for the exact commit. The official updater deployed it by
request `01a140f7-e91f-7af9-af19-725b858adcc8`, which succeeded at
2026-10-15 19:11:38 UTC. Its full backup
`/var/backups/fanoos/20261015T191117Z-ee83a1ea` independently verified 114
files. The later exact green `main` at
`67c3b2326393fbf32cad194d9877a6c385481dce` was officially deployed by
request `01a14103-7aea-7d5a-9270-5afbd5792919`, succeeded at
2026-10-15 19:23:52 UTC, and produced independently verified 114-file full
backup `/var/backups/fanoos/20261015T192327Z-8177c659`. Both deployments
passed live `/health` and server `check-sync.sh` checks against GitHub `main`.

| Question(s) | Exact original edition | Bound chapter, physical PDF page and printed book page | Source change |
|---|---|---|---|
| 22, 25, 26 | Torabinejad Endodontics 6e | ch2/34/27; ch5/99/92; ch6/114/107 | PDF page and SHA only |
| 42 | Neville Oral Pathology 5e | ch5/183/173 | Corrected placeholder printed `5`; bound PDF page and SHA |
| 79 | Little/Falace Dental Management 10e | ch17/306/268 | Corrected old printed `263`; bound PDF page and SHA |
| 137, 139 | Hupp Oral Surgery 7e | ch16/347/333; ch19/409/395 | PDF page and SHA only |
| 171, 173, 179, 181, 188, 190 | McDonald/Avery Pediatric 11e | ch1/29/16; ch3/58/45; ch12/240/229; ch15/302/291; ch27/607/604; ch29/666/663 | Corrected printed page; bound PDF page and SHA |
| 184, 189 | McDonald/Avery Pediatric 11e | ch20/427/421; ch28/647/644 | PDF page and SHA only |
| 215 | Craig Restorative Materials 14e | ch12/277/266 | PDF page and SHA only |
| 187 | McDonald/Avery Pediatric 11e | ch26/586/583 | New one-question original-PDF audit; corrected old printed `582`; bound PDF page and SHA |

For Q187, the unchanged published stem, four original choices and latest
official final key were placed in a protected study-only package
`recovery-1405-pedi187-20261015`. Direct reading of physical PDF page 586
showed the ASD context and Papoose Board recommendation on that same original
page; the printed label 583 and neighbors passed the deployed verifier. The
primary classification validator accepted **1/1**, and the independent
provenance auditor accepted **1/1, 0 pending**. The private audit SHA-256 is
`ca0436d22e5030f01e412b298fc6a6d04b682e71afbc29d5e4e0dd0a1533d8f4`.
Its guarded chapter/printed/PDF correction rollback preview and apply each
reported one source and zero question, choice or answer changes. Independent
post-apply database readback rechecked its immutable-content digests.

The sixteen existing independently accepted one-question audits above were
reused for the other sixteen updates. The exact private protected plans and
receipts remain under
`/srv/fanoos/shared/research/classification/reports/` as
`1405-endo-q<N>-page-{plan,applied}-20261015.json` for `N=22,25,26`,
`1405-q<N>-followup-{correction,page}-{plan,applied}-20261015.json` for
`N=42,79,137,139`,
`1405-q<N>-pediatric-craig-{correction,page}-{plan,applied}-20261015.json` for
`N=173,179,181,190,215`, and
`1405-q<N>-pediatric-late-{correction,page}-{plan,applied}-20261015.json` for
`N=171,184,188,189`; Q187 uses
`1405-q187-pediatric-correction-{plan,applied}-20261015.json`. The Q22,
Q25 and Q26 applies used the first verified backup above; the other fourteen
used the second. All task-only PHP scripts were removed from the protected
research temporary directory after readback.

The final direct database recount is **223/250** with a chapter, physical PDF
page and approved PDF SHA on the **same source row**. The 20 residency-English
questions remain explicitly reference-exempt, leaving **7/230 clinical
questions field-incomplete**. Endodontics, oral medicine and periodontics are
each **20/20**; pediatric dentistry is **19/20**. The seven remaining field
gaps are **Q44** oral pathology, **Q81/Q99** prosthodontics, **Q133** oral
surgery, **Q174** pediatric dentistry, **Q207** oral radiology, and **Q218**
dental materials. Q133 and Q174 have official *voided* keys, so the current
publisher must not invent final answers. Q44 and Q218's current chapters are
outside year-1405 official scope; answer-bearing in-scope replacement is not
established. Q81 needs original exam-figure/key reconciliation. Q99 lacks a
decisive original-book passage for its keyed exclusion. Q207 needs the two
independently indicated in-scope original-book citations, not an arbitrary
one-source collapse. The already field-complete Q217 still cites an
out-of-scope chapter and remains a separate scientific hold. **The full 1405
exam is not scientifically closed.**

The current public server health is `ok`; live release and updater checkout
both equal exact GitHub `main` `67c3b23`. This session did not create another
permanent checkout. The prior duplicate-branch cleanup removed 110 exact-tip
remote branches; the remaining 11 remote branches are `main`, the open draft
PR #388 and nine distinct-tip historical branches. Their differing work was
preserved rather than silently deleted. The work done above is fully merged
into the single canonical `main` and deployed; no 1405 change from this
continuation remains in an unmerged branch after this report is merged.
