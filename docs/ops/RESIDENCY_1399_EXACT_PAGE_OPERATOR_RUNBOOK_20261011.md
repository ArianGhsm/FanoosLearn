## Prepared guarded existing-source metadata operator (PR #305)

`scripts/references/repair_residency_1399_verified_pdf_pages.php` with
`tests/references/test_residency_1399_existing_source_pdf_pages.py`
has been proposed in a **separate, explicitly reviewed code PR**. The fixed
allowlist is endodontics Q25/Q30/Q32 and periodontics
Q115/Q118/Q122/Q123/Q125. It refuses any unlisted year/subject/number,
source row with a human review, wrong edition/chapter/official scope or
unexpected final-answer key. It reads each original PDF page through the
canonical verified bridge, checks short evidence and two neighboring printed
labels, and checks exact pinned SHA-256 before touching any source.

Only after green PR CI, merge, official updater deploy, `check-sync` and
site health should a privileged operator create the exact private plan
folder, then run the two batches separately:

```sh
# Execute from the deployed, immutable release as service user fanoosupd.
mkdir -m 0700 /srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages
php scripts/references/repair_residency_1399_verified_pdf_pages.php \
  --subject=endodontics --prepare \
  --plan=/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/endo-plan-UNIQUE.json
sha256sum /srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/endo-plan-UNIQUE.json
# Use the official fresh full backup operator, verify-backup.php, validate
# the frozen 249-question sitting before publishing, then:
php scripts/references/repair_residency_1399_verified_pdf_pages.php \
  --subject=endodontics --apply \
  --plan=/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/endo-plan-UNIQUE.json \
  --plan-sha=THE_64_HEX_RESULT --backup=/var/backups/fanoos/FRESH_VERIFIED_ID \
  --receipt=/srv/fanoos/shared/research/classification/reports/residency-1399-exact-pages/endo-receipt-UNIQUE.json
# Prepare/verify/apply periodontics separately, with --subject=periodontics
# and its own sealed private plan, fresh backup and receipt.
```

The plan fingerprints **all stored question/option/official-answer and source
rows** of each target and compares them under a transaction at apply time.
The sole SQL `UPDATE` is restricted to four allowed
`bank_question_sources` metadata columns and uses compare-and-swap guards.
Q25 corrects the old *printed* page 7 to 51 alongside the new original
physical PDF page 63. A source-only DB update does **not** automatically
re-publish old immutable student assessment versions: compare the frozen
assessment and run the approved review-and-publish operation, then audit
entire sitting `--previous/--current` under
`scripts/references/audit_published_assessment.php`. Record the before/after
strict same-row count, expected increase of **3** for a fully verified
endodontics batch and **5** for periodontics, actual receipt/checksum,
backup ID, GitHub and deployed SHAs, version numbers and /health.

**Status at authoring:** proposed operator and docs only; no live row is
marked repaired, no frozen version was published, no source count changed.

