# Legacy source — Dentistry1402TUMS, imported for migration

This tree is the working source of `ArianGhsm/Dentistry1402TUMS`, imported so the
FANOOS rebuild can be a **translation of code that already worked with real
students** rather than a reimplementation from summaries. Two rounds of the latter
lost real things — the 109-institution catalog and the entry-term step — which is
why the source now lives here instead of being read from another repository.

- `bot/` — the Telegram and Bale bot runtime (`bot_runtime/` upstream)
- `site/` — the website (`public_html/` upstream)

## It is not in the execution path

Nothing here runs. It is not deployed, not imported by any FANOOS module, and not
served. It is source material to migrate from, file by file, into FANOOS's own
structure. When the migration is finished this directory is deleted.

## What was deliberately left behind

One cohort's exam content, roughly 34MB of it: `api/exams_term6_reference_data/`,
`exams_bank.php`, `exams_endotorabinejad_data.php`, `exams_radiology1_data.php`,
`exams_term6_reference_catalog_data.php`, and the `api/data/*_mcq_fa.txt` question
banks. That is Dentistry-1402 course material, not product code, and FANOOS is
meant to serve any class. It remains available in the upstream repository if it is
ever wanted as seed data for that specific class.

## The one rule while migrating

The legacy system served exactly one class, so nothing in it carries a workspace
identity. Every piece moved into FANOOS must thread `workspace_id` through, and
must pass the tenant-isolation tests already in CI before it goes live. A single
missed filter means one class reading another's grades, and that kind of bug
stays invisible until it is expensive.
