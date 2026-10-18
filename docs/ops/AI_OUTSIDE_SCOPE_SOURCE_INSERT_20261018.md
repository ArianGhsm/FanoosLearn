# Guarded source-free AI citations outside the official syllabus

Use this route only when a published question has no source row and the exact
design location is in a whole chapter that is absent from the question's
structured official-year chapter list. Preserve that true location in the
bank and mark `official_scope_status=outside_scope_exam_question` with a clear
Persian note containing `خارج از محدوده`. This records provenance; it does not
add the chapter to the syllabus or resolve a conflict with the official key.

This is separate from the ordinary source-only importer and from the
existing-source outside-scope repair. Never send an outside-scope validated
sitting to `import_verified_sources.php`.

## Eligibility and evidence

- Work inside the existing private 30–40-question residency research batch.
  The package may contain in-scope accepted questions and unresolved items;
  only exact outside-scope entries go to this writer.
- Require the exact official edition, authenticated original server PDF,
  verified chapter map and direct evidence on the physical PDF page.
- This route accepts only a whole chapter absent from a non-empty structured
  official chapter list. Partial-chapter page exceptions use their own guard.
  Missing, malformed or unstructured scope stays pending.
- Require page-label corroboration on the target page and at least two nearby
  pages. If the target page has no printed label, independently prove that
  absence and keep `printed_page` null; include the prescribed Persian note.
- Bind an independent `audit_private_study_batches.py` report to the exact
  study, decisions and validated sitting hashes. The outside-scope insertion
  operator independently rechecks live source-free status, published stem,
  four choices, latest official key, edition, official-year validity, scope,
  chapter map, PDF SHA, page evidence and page-label status.
- Record any unresolved answer alignment explicitly in the private plan.
  Keep the official key and its history unchanged.

## Research-only validation

Add `outside_scope_note` to each outside-scope decision. It must clearly name
the omitted chapter and include `خارج از محدوده`. Then validate the private
study with the explicit outside-scope research flag:

```sh
sudo -u fanoosupd python3 -B scripts/references/apply_classification.py \
  --sitting=/srv/fanoos/shared/research/classification/<case>/bank-sittings/<year>/<stem>-study.json \
  --decisions=/srv/fanoos/shared/research/classification/<case>/classification/decisions/<year>-<subject>.json \
  --out=/srv/fanoos/shared/research/classification/<case>/classification/sittings/<year>-<stem>-validated.json \
  --allow-outside-scope --partial
```

This flag accepts only a chapter absent from a structured official chapter
list and a sufficient Persian scope note. It is for private research output;
the normal in-scope source importer still refuses the resulting out-of-scope
chapter. The separate protected writer below is the only insertion route.

Run the independent original-PDF study audit and keep its report under that
case's private `classification/reports/` directory:

```sh
sudo -u fanoosupd python3 -B scripts/references/audit_private_study_batches.py \
  --local=/srv/fanoos/shared/research/classification/<case> \
  --batch=<year>:<subject>:<stem> \
  --out=/srv/fanoos/shared/research/classification/<case>/classification/reports/<audit>.json
```

## Guarded preview and apply

Create a protected plan in the same case reports directory. Each entry pins
the original question number, exact edition/chapter/PDF page/SHA, evidence
fragments, confidence, printed-label state, explicit scope note, answer
alignment note and the original stem/choice/key fingerprints. Bind the plan
to the accepted study audit SHA-256.

Preview the exact source-only inserts as `fanoosupd`:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/insert_verified_ai_outside_scope_sources.php \
  --workspace=<workspace-uuid> \
  --case-root=/srv/fanoos/shared/research/classification/<case> \
  --stem=<study-stem> \
  --plan=/srv/fanoos/shared/research/classification/<case>/classification/reports/<plan>.json
```

Proceed only if preview reports `mode=rollback-preview`, the exact target
count, and zero question, choice, official-answer, assessment-version and
attempt changes. Apply that same plan only after a fresh full backup passes
the official verifier:

```sh
sudo -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php \
  php scripts/references/insert_verified_ai_outside_scope_sources.php \
  --workspace=<workspace-uuid> \
  --case-root=/srv/fanoos/shared/research/classification/<case> \
  --stem=<study-stem> \
  --plan=/srv/fanoos/shared/research/classification/<case>/classification/reports/<plan>.json \
  --apply --backup=/var/backups/fanoos/<verified-backup> \
  --receipt=/srv/fanoos/shared/research/classification/<case>/classification/reports/<unique-receipt>.json
```

After apply, independently read back every new source row and its question,
choices, latest official answer, page, SHA and scope note. Re-run the official
whole-year structural gate. Keep unresolved science or answer-alignment holds
open; this path never authorizes a partial-year publication or issue closure.
