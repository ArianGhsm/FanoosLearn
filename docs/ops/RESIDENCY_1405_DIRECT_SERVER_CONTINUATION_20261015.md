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
