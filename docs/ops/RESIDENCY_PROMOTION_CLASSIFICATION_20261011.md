# Residency 1397 and promotion 1405 classification — 2026-10-11

> Follow-up (research only): the direct-PDF chapter-map and official-scope
> audit in [CHAPTER_SCOPE_AND_PDF_MAP_CHECKPOINT_20261011.md](CHAPTER_SCOPE_AND_PDF_MAP_CHECKPOINT_20261011.md)
> found 25 outside-scope source rows in the newest published sittings and
> validated 7 additional, **unpublished** residency 1397 endodontics and 7
> promotion 1405 round-3 prosthodontics decisions. The published counts below
> are unchanged; do not add these research decisions to them before an audited
> import and live read-back.

Procedure: `docs/product/09_CHAPTER_CLASSIFICATION.md`, with the national
conventions in `docs/ops/NATIONAL_CLASSIFICATION_1404_1405.md` (each sitting
has its own decisions folder; evidence is re-read by `apply_classification.py`
from the cited, hash-bound PDF page). Owner request 2026-10-11: “after
national, do residency 1397 and promotion 1405 too”.

## Server layout used

- Sittings matching the site: `/srv/fanoos/shared/research/bank-sittings/<year>/<type>-<year>-<round>.json`.
  - Residency 1397: the Drive-import staging sitting (`drive-20261011b`) that was imported.
  - Promotion 1405: exported from the bank with `import-bank.php export`
    (PR #289). Each re-import dry run showed `questions_changed 0` and
    `answers_recorded 0`.
- Decisions: `classification/decisions/residency-1397-1/` and
  `classification/decisions/promotion-1405-<round>/`.
- Validated sittings: `classification/sittings/<type>-<year>-<round>.json`.
- Import copies: `/var/lib/fanoos/bank-import-<type>-<year>-<round>/` (sitting plus `assets/`).
- The import sequence for each sitting:
  1. full apply;
  2. dry run, stopping unless `questions_changed` is 0;
  3. `backup.php`, then `verify-backup.php`, which must print "Backup verified";
  4. import;
  5. `publish --actor --reviewer`.

## Residency 1397 — done

Editions ready for 1397: carranza 12e, mccracken 12e, zarb 13e, summit 4e,
neville 4e and proffit 5e. **Torabinejad 5e** has a map, but its
`chapter_coverage` reports a gap/overlap, so it is invalid. Endodontics stays
pending, and the map was not edited.

| Subject | With chapter | Book |
|---|---|---|
| periodontics | 10 | Carranza 12e |
| oral pathology | 18 / 20 | Neville 4e |
| orthodontics | 16 / 20 | Proffit 5e |
| prosthodontics | 14 / 30 | Zarb 13e (complete), McCracken 12e (RPD); fixed-prosthodontics books not ready |
| operative dentistry | 5 | Summit 4e (only the questions cited to Summit) |
| endodontics | 0 | Torabinejad 5e map invalid |
| other subjects | 0 | no ready edition for 1397 |
| **Total** | **63 / 247** | 0 rejected |

Run:

- dry run: 247 questions, `questions_changed 0`, 63 sources;
- backup `/var/backups/fanoos/20261011T050321Z-9cfddc6a` (verified);
- import and publish: assessment `01a12826-05e0-7730-ae1d-88e4eba50d13`,
  version `01a12958-de11-70de-bc3c-cc7285f0c678`, 247 questions.

## Promotion 1405 — done

Specialty rounds: 1 endo, 2 perio, 3 prosthodontics, 4 operative,
5 surgery, 6 oral medicine, 7 oral pathology, 8 radiology, 9 orthodontics,
10 pediatric.

Rounds with a ready edition:

- r4: craig 14e, summit 4e (Phillips 13e and Rosenstiel 6e not ready);
- r6: burket 13e, little-falace 10e, neville 5e, white 8e;
- r7: neville 5e;
- r10: nowak 6e, proffit 6e (McDonald 11e not ready).

Rounds 1, 2, 3, 5, 8 and 9 have no ready edition and stay pending.

### Promotion 1405 — done (rounds 4, 6, 7, 10)

Search terms covered only the ready editions. In r4, only the questions
cited to Craig or Summit were searched; the questions cited to Sturdevant
("آرت"), Phillips, Dawson, Rosenstiel and Misch stay pending.

| Round | With chapter | Books | Backup | Assessment |
|---|---|---|---|---|
| 4 operative | 16 / 100 | Craig 14e, Summit 4e | `20261011T070717Z-cc48b930` | `01a11d32-1a56-79c3-ab87-23c4e53ccc96` |
| 6 oral medicine | 78 / 100 | Burket 13e, Little-Falace 10e, Neville 5e, White 8e | `20261011T071032Z-dcf801a9` | `01a11d32-1cd8-71c5-a746-5070ecc1da1f` (Q38 withdrawn, left out) |
| 7 oral pathology | 56 / 100 | Neville 5e | `20261011T071401Z-3bf638d7` | `01a11d32-1e2f-726b-8b4c-afa4b364d9b7` |
| 10 pediatric | 35 / 100 | Nowak 6e, Proffit 6e | `20261011T071626Z-53086d26` | `01a11d32-21da-7e10-9816-1e4eb936dea1` |

Every dry run showed `questions_changed 0` and `answers_recorded 0`, and
every backup was verified. Undecided questions are mainly those that cite
journal articles (r10 Q91–Q100), Regezi or Robbins (r7), or a book that is
not ready.
