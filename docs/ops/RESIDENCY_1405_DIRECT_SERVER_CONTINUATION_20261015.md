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
copies were removed after verifying the implemented files on main. No remote
branch or another contributor's working tree was deleted.
