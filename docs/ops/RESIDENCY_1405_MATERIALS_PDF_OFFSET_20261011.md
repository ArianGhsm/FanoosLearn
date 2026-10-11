# Residency 1405 — guarded printed-page/PDF-offset research: Q214

## Evidence and safety (2026-10-11)

The original production Q214 in dental materials is already assigned to **Craig's Restorative Dental Materials, 14th ed., chapter 12**, legacy book printed page `246`. The old source has `pdf_page=NULL` and `pdf_sha256=NULL`; it is `origin=ai` and has no recorded human source review. **This is an existing source row: do not insert a duplicate or import a whole sitting.**

The **exact 1405 official validity** allows Craig 14e chapter 12. The current server-approved Craig 14e PDF has SHA-256 `333e81ae8a1818cdff57f00e3f870698776e3b8e02fcc49169f397f2fbaf90b9`, with a hash-bound chapter map. On **PDF page 257**, the book itself prints page **246**. The adjacent page-label test confirms the exact offset, and the original source's old evidence matches the text on this same page. The page contains Table 12.5 about compression strain of impression materials, directly relevant to the official-keyed question. Chapter map and same-year syllabus include the referenced chapter.

A **fresh original question/choices/latest-key study-only** was extracted read-only from production into the protected folder:

`/srv/fanoos/shared/research/classification/recovery-1405-materials214-20261011/`

Private research files:
- `bank-sittings/1405/materials214-study.json`
- `classification/decisions/1405-dental-materials.json`
- `classification/sittings/1405-materials214-validated.json`
- `classification/reports/1405-materials214-audit.json`

`apply_classification.py --pdf-pages` reported **1 accepted, 0 rejected**; `audit_private_study_batches.py` independently reported **1 accepted, 0 pending**, original content unchanged. Audit file SHA-256: `ea04f9d644ad8695504fac78feeb8e7a505d15c0270232ad364a96ac680b844f`.

## Protected repair path

The original `repair_existing_source_pdf_page.php` only allowed identical printed and PDF numbers; do not bypass that limitation with a false page label. This change adds an explicitly selected `--printed-offset` mode, restricted to an existing AI source whose *legacy page and printed_page are the same*, but the independently verified PDF page differs. The mode is fail-closed unless the actual approved PDF (hash-pinned) has the old printed label on that candidate PDF page, **at least two adjacent pages** corroborate the expected printed-page progression, and the **existing live source anchor text** is found on that same page. The single original question, all its choices, its final official key, reference/edition/node, year-specific chapter validity, unreviewed AI status, unchanged existing legacy page and the separately SHA-audited decision must also match. Only `pdf_page` and `pdf_sha256` may be changed.

After green PR CI/merge and green exact-main CI, deploy via official updater, verify live SHA/health, create and independently verify a *fresh complete full backup*, run rollback-only preview, and only then run an exact one-source apply and save a unique protected receipt. Keep original `page=246`, `printed_page=246`, `chapter=12`, original anchor, stem/choices/key and all histories untouched.

Operator invocation template, only with private authorized workspace and verified backup paths:

```sh
FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php \
  scripts/references/repair_existing_source_pdf_page.php \
  --workspace=<approved-workspace-uuid> --year=1405 \
  --subject=dental-materials --number=214 --stem=materials214 \
  --case-root=/srv/fanoos/shared/research/classification/recovery-1405-materials214-20261011 \
  --audit-sha=ea04f9d644ad8695504fac78feeb8e7a505d15c0270232ad364a96ac680b844f \
  --printed-offset

# Authorized apply adds:
# --apply --backup=/var/backups/fanoos/<fresh-verified-backup>
# --receipt=/srv/fanoos/shared/research/classification/recovery-1405-materials214-20261011/classification/reports/1405-materials214-live-repair.json
```

## Other printed-offset candidates are unconfirmed

Craig 14e Q211, Q212, Q219 have plausible printed-to-PDF offsets but their **existing old anchors did not independently match the proposed pages** during limited preliminary checking. Do not use this source-repair procedure on them before replacing the research dossier with genuinely supported primary evidence and independently proving the old anchor and quoted location. Q218 is outside the official 1405 Craig chapter scope and must remain on hold.

**Research-only checkpoint until explicit post-apply, post-live-readback documentation.** Do not increase the completed count based on this document alone.
