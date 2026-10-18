# Residency 1403 Q152 and Q162 source-citation repair

## Purpose and scope

This is a narrowly pinned source-only repair for the two remaining 1403
publication-gate questions. It uses the protected, accepted, one-question
original-PDF audits for Q152 and Q162. The operator refuses any other question
or a changed research artifact, source row, official scope, printed-page label,
PDF hash, or immutable question/key snapshot.

The Q152 Sturdevant 7e Chapter 1 location is PDF page 26 / printed page 13.
The Q162 Craig 14e Chapter 13 location is PDF page 288 / printed page 277.
Both exact pages are in their 1403 official subject scopes and have SHA-bound
chapter-map and printed-label corroboration.

## Protected evidence

The private accepted audits and their source-page snapshots are pinned in
`Residency1403AiSourceCitationReplacementGuard`. Their server paths and SHA-256
digests remain in code and in the access-controlled research workspace; no
question text, answer choices, or book excerpts are stored in Git.

## Guarded operator

`scripts/references/repair_residency_1403_ai_source_citation_replacements.php`
accepts one private plan at a time. A plan is a mode-0600 JSON file under
`/srv/fanoos/shared/research/classification/reports/` with format
`fanoos.residency1403.ai-source-citation-replacement-plan/1`. It pins the
question, exact new citation, audit path and digest, old source row identity
and digest, a short original-page anchor, and one to four exact-page evidence
fragments. The command rechecks the original PDF, current official scope,
question, four choices, official answer, assessment, attempts and reviews.

The versioned plan builder is
`scripts/references/prepare_residency_1403_ai_source_citation_replacement_plan.php`.
It accepts only the exact mode-0600 page-evidence input pinned in the guard,
validates its SHA-256, rereads the current live source/question/key, and writes
a private compare-and-swap plan without database writes. For either question,
run it as `fanoosupd` with
`FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php`:

```sh
php scripts/references/prepare_residency_1403_ai_source_citation_replacement_plan.php \
  --workspace=01a1037b-0322-7c8f-8904-7c9334fb9cbe \
  --number=152 \
  --evidence-file=/srv/fanoos/shared/research/classification/reports/1403-q152-sturdevant-source-citation-page-evidence-20261018.json \
  --output=/srv/fanoos/shared/research/classification/reports/1403-q152-source-replacement-plan-20261018.json

php scripts/references/repair_residency_1403_ai_source_citation_replacements.php \
  --workspace=01a1037b-0322-7c8f-8904-7c9334fb9cbe \
  --plan=/srv/fanoos/shared/research/classification/reports/1403-q152-source-replacement-plan-20261018.json \
  --preview-receipt=/srv/fanoos/shared/research/classification/reports/1403-q152-source-replacement-preview-20261018.json
```

Repeat for Q162 using its pinned Craig evidence input and distinct plan and
preview names. Apply must pass the exact preview receipt produced for that
plan, a fresh verified backup path, and a new private `--receipt`:

```sh
php scripts/references/repair_residency_1403_ai_source_citation_replacements.php \
  --workspace=01a1037b-0322-7c8f-8904-7c9334fb9cbe \
  --plan=/srv/fanoos/shared/research/classification/reports/1403-q152-source-replacement-plan-20261018.json \
  --preview-receipt=/srv/fanoos/shared/research/classification/reports/1403-q152-source-replacement-preview-20261018.json \
  --apply \
  --backup=/var/backups/fanoos/<fresh-verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/reports/1403-q152-source-replacement-applied-20261018.json
```

The apply command rechecks every plan, source, research, scope, PDF and
assessment condition; a plan or preview is not an apply authorization.

Preview rolls back its transaction. Apply is restricted to `fanoosupd`,
requires a fresh independently verified full backup and a new private receipt,
and compare-and-swaps only the existing AI source row. It changes citation
metadata and confidence only. No question, option, key, review, assessment,
attempt, or score field is writable by this operator. Rerun the whole-year
publisher gate and independent live readback after each apply; this repair
does not publish a new exam version.

## Delivery status

This code path is not usable until its focused PR passes CI, is merged to
`main`, the merge-commit CI passes, and the official updater deploys that
commit. Production database changes must then follow the preview, fresh
backup, apply receipt, and independent readback sequence. Keep both questions
open in the year gate until those live checks pass.
