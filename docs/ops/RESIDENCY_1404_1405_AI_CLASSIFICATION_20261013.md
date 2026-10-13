# Residency 1404/1405 AI classification checkpoint — 2026-10-13

Owner direction: complete exact-edition chapter/PDF-page classification by AI
without a human-review gate. `docs/PROJECT_PRINCIPLES.md` and
`docs/product/09_CHAPTER_CLASSIFICATION.md` govern the evidence and source-only
publication rules. Prior human-origin records remain attributed data; a
correction needs proof and an audited receipt, not human sign-off.

## Verified starting state and deployment

- Authorized IranServer host, FANOOS updater checkout. GitHub `main`
  `f49c1dcc91dc8b83dbb176d53264e8ca4a518b16` had green PR #396 and
  green merge CI run `38363155618` after a transient public ECR image pull
  rate limit in the first MySQL job. The rerun passed all five jobs.
- Updater request `01a1342f-c61d-7189-a0fb-8cbdeec27cfe` finished
  `SUCCEEDED`. Public `/health` reported that exact SHA; the Bale and
  Telegram bot services were active. Server-side `check-sync.sh` as
  `fanoosupd` reported updater checkout and live release equal to GitHub
  `main`.
- The updater created `/var/backups/fanoos/20261013T074015Z-367c0cba`;
  a separate `verify-backup.php` run passed **114 files**. This was the
  fresh verified backup for the source-only changes below.
- Read-only live SQL, using one-row `EXISTS(node_id IS NOT NULL AND pdf_page
  IS NOT NULL AND pdf_sha256 IS NOT NULL)`, counted **1404: 110/250** and
  **1405: 99/250** before this pass. Each sitting has 20 English questions
  with valid final/amended keys and no textbook citation requirement.
  Thus clinical backlog started at 120/230 and 131/230 respectively.

## 1405 source-only changes verified in production

The deployed `repair_existing_source_pdf_page.php` was run as `fanoosupd`
once in rollback-only preview, then once with `--printed-offset --apply`
for each of the seven single-question protected cases below. Each command
used `--workspace=01a1037b-0322-7c8f-8904-7c9334fb9cbe`, `--year=1405`,
its exact `--subject`, `--number`, `--stem`, `--case-root`, the independent
`--audit-sha` listed in
`RESIDENCY_1405_ELEVEN_READY_PAGE_CASES_20261012.md`, the verified backup
above and an unused receipt under that case's `classification/reports/`.
Every preview and apply returned `source_rows_updated=1` and zero changed
questions, choices, answers, chapters or human reviews. The seven private
receipt files are named `1405-<stem>-live-repair-20261013.json` under each
`recovery-1405-<stem>-<date>/classification/reports/` case folder.

| Question | Case stem | Subject | Chapter | Original PDF page |
|---:|---|---|---:|---:|
| 34 | `endo34` | endodontics | 22 | 473 |
| 51 | `pathology51` | oral-pathology | 12 | 543 |
| 57 | `pathology57` | oral-pathology | 15 | 732 |
| 88 | `prostho88` | prosthodontics | 14 | 217 |
| 143 | `surgery143` | oral-surgery | 28 | 637 |
| 185 | `pediatric185` | pediatric-dentistry | 23 | 502 |
| 186 | `pediatric186` | pediatric-dentistry | 17 | 360 |

SHA-256 of the seven persisted receipts, in the table's order:
`a9c411d5048ad0ee769f82d2b9378dba6a6be0a2bcf64d2d7634d3d77385b6e9`,
`ded9e1fd3c41f6b6aab5e895a8f17b36a47da5394312ed876c91b838b1c6a6b3`,
`4add945d50a0a7e739094aee0daa1e7dadb69ba629fbaf1b3c8749764388c052`,
`ee0e7cfed7038a9987caa7e5e6ab387da2cfa12bdbbc1858352bc7f8f9584993`,
`547dd1b08103fefa327c18b5eb3d0608e562c486309afcdfa2708705bf57de1c`,
`692df1ffb37987ddffa87047c9c4277794f8359590290030b5a5dc9ae6c7c6a7`,
`53a3dea0d3dd08390d1d0eb12f48414dffb1d58ae18abeada77941f1376d94fe`.

Live SQL independently read back all seven exact question numbers, original
chapter, PDF page, approved PDF SHA and `origin=ai`; the complete same-row
count became **106/250**. The earlier Q34 production lock timeout did not
recur after the updater's backup ended.

The original four-question radiology case was left unchanged. Q191 refers
to diagrams الف/ب/ج/د, but live question and protected study have no image;
the recorded choice 2 cannot be checked against the absent diagrams. A new
0600 private case
`/srv/fanoos/shared/research/classification/recovery-1405-radiology192-194-20261013/`
contains only the original unchanged Q192–Q194, their existing verified
decisions and a fresh validated sitting. Commands:

```sh
python3 -B scripts/references/apply_classification.py \
  --sitting=<case>/bank-sittings/1405/radiologythree-study.json \
  --decisions=<case>/classification/decisions/1405-oral-radiology.json \
  --out=<case>/classification/sittings/1405-radiologythree-validated.json \
  --pdf-pages
python3 -B scripts/references/audit_private_study_batches.py \
  --local=<case> --batch=1405:oral-radiology:radiologythree \
  --out=<case>/classification/reports/1405-radiologythree-audit.json
php scripts/references/import_verified_sources.php \
  --workspace=01a1037b-0322-7c8f-8904-7c9334fb9cbe --year=1405 \
  --subject=oral-radiology --stem=radiologythree --expected=3 \
  --audit=<case>/classification/reports/1405-radiologythree-audit.json \
  --audit-sha=8262bcb24cd7a1e525b4772d4b936d4d9b3f76a6096ef7e83b289628ef6a4494 \
  --case-root=<case>
```

The validator returned **3 accepted, 0 rejected**; independent provenance
audit returned **3 accepted, 0 pending**; rollback-only import preview
returned `source_rows=3`, `questions_changed=choices_changed=answers_changed=0`.
The same import with `--apply`, verified backup above and receipt
`<case>/classification/reports/1405-radiologythree-live-sources-20261013.json`
committed three source rows. Independent live SQL read back Q192 ch2/PDF94,
Q193 ch3/PDF120 and Q194 ch4/PDF186, each on White/Pharoah 8e SHA
`228ceb46982c3057d8a7d78b1d10759e72ed1a3a385a76a9affc38e45874c25a`;
receipt SHA-256 `2715568ae229dd8f73a5697726450a8ce6b55d3b5cc75972d6b0d65c07178b4d`.
Q191 still had no source row. **Final read-back: 109/250**, 20 English
exempt, **121/230 clinical incomplete**. This is a same-row field count,
not certification that every older populated citation is scientifically sound.
The published frozen assessment remained version **5**, **246** questions,
definition SHA-256
`710f48cbbfb143e9ea106a5f8e24f7ed537e3779438ade97e248d085a6d895bb`;
no assessment version was appended by these source-only operations.

## Remaining evidence work

- 1404 remained **110/250**, 20 English exempt, **120/230 clinical
  incomplete**. Its community, radiology, pediatric and periodontics subjects
  still have zero complete source rows. Fifty-four incomplete 1404 rows have
  human-origin provenance; the new AI policy removes an approval gate but
  does not invent missing exact PDF evidence.
- The present approved Carranza 14e server object has only **105 excerpt
  pages**, so 1404/1405 periodontics cannot be closed against a complete
  exact edition. Do not use Carranza 13e as a substitute or copy its page map.
- 1405 Q191 remains without source pending recovery of the original exam
  diagram and answer-label comparison. Q207 has exact in-scope evidence in
  **two** official chapters (White/Pharoah 8e ch23/PDF1161 and
  ch24/PDF1294); the current source-only publisher and independent audit
  accept only one source per question, so a safe two-citation workflow is
  required before publication. See
  `RESIDENCY_1405_RADIOLOGY_Q207_TWO_VERIFIED_PAGES_20261013.md`.
  The sixteen other audited 1405 research cases
  in `RESIDENCY_1405_ACCELERATED_56_PAGE_RESEARCH_20261013.md` remain
  research-only because legacy anchors are paraphrased and the current
  existing-source operator correctly refuses their mutation. Do not count
  them as production updates.
- PR #388 is a separate 1403 draft prototype explicitly marked unapproved
  for merge/production; it was not merged in this pass.

This checkpoint records actual server writes above. Policy/site-language
changes under `policy/ai-evidence-classification-20261013` require their own
green PR and updater deployment; until then, this document does not claim
that the new UI wording is live.
