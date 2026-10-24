# National exam classification — 1404 and 1405

Procedure: `docs/product/09_CHAPTER_CLASSIFICATION.md` (exact official
edition, verified PDF, hash-bound chapter map, verbatim page evidence,
`apply_classification.py`, dry run `questions_changed=0`, verified backup,
import, publish). Owner request 2026-10-11.

## Conventions added for national sittings

- **Sitting that matches the site:** the Drive-import staging sitting that
  was imported on 2026-10-11 (`docs/ops/DRIVE_IMPORT_20261011.md`), copied to
  protected `/srv/fanoos/shared/research/bank-sittings/<year>/national-<year>-<round>.json`
  (images in `…-assets/`).
- **Decisions live per sitting:** `/srv/fanoos/shared/research/classification/decisions/national-<year>-<round>/<year>-<subject>.json`.
  The residency directory `decisions/` holds `<year>-*.json` too, and
  national 1404 has two sittings with overlapping numbers, so a national
  sitting is always validated with `--decisions=` pointing at its own folder.
- **Editions ready** (verified PDF + map bound to its SHA-256), national
  1404/1405 list: torabinejad 6e, carranza 13e, zarb 13e, summit 4e, craig
  14e, hupp 7e, malamed 7e, burket 12e, little-falace 9e, neville 5e,
  white-pharoah 8e, proffit 6e, national-oral-health 1394.
  **Pending (no hash-bound map):** mccracken-rpd 13e, rosenstiel 6e,
  shillingburg 4e, sturdevant 7e, mcdonald-avery 11e — their questions stay
  undecided.
- English (scope statement, no book) and the basic-science block (no
  official reference) get no source.
- The private post-validator audit script supports type-prefixed national,
  board, and promotion batches and reads `exam_type` and `round` from both
  study-only files. For national sittings the evidence check remains
  `apply_classification.py` plus the independent audit (every quote is
  re-read from the cited PDF page).

## Missing official lists and outside-scope flags — owner rule 2026-10-24

For a national subject with no exact year row in the catalog, use the
same-lineage selector's nearest official **national** list year for that
subject; an equal-distance tie goes to the later year. National 1397–1400
subjects with no catalog list therefore use the nearest later national list
where one exists (for example, a 1399 round-2 batch may select the 1401
list). The selector ranks editions newest first within the selected families.
An exact year+subject list suppresses all proxy rows. The same rule applies
to board and promotion only within their own exam type. Never add a synthetic
validity row.

A proxy decision records `classification_basis=same_lineage_proxy`, the
selected row's actual `reference_year`, a Persian `proxy_note` naming both
years and saying official status for the exam year is unverified, and
`newer_editions_searched` when the citation is not rank zero. Confidence is
at least 0.85. Example metadata (placeholders only; no question or book text):

```json
{
  "edition": "<selected-official-edition-key>",
  "reference_year": 1401,
  "classification_basis": "same_lineage_proxy",
  "proxy_note": "این آزمون ۱۳۹۹ از نسخهٔ پروکسیِ سال ۱۴۰۱ استفاده می‌کند؛ رسمی بودن آن برای سال آزمون تأیید نشده است.",
  "newer_editions_searched": ["<newer-eligible-edition-key>"]
}
```

An out-of-scope design location keeps its true chapter and page and records
`official_scope_status=outside_scope_exam_question` plus a Persian
`outside_scope_note`. The research validator uses `--allow-outside-scope`;
the source-only transaction checks that the chapter is wholly absent from a
structured official scope. The national note template is:

```text
محل طراحی این سؤال در فصلی خارج از محدوده سرفصل رسمی آزمون ملی <YEAR> است.
```

Use it for an exact official row or a same-lineage proxy row. For a proxy,
retain both `proxy_note` and `outside_scope_note`; the first records year/list
provenance and the second records the scope disposition. If scope is missing,
unstructured, or includes the cited chapter, leave the decision pending or
in-scope as appropriate; do not use the outside-scope flag to bypass another
guard.

## Guarded source-only workflow for an individual national sitting

The source-only exporter defaults to residency round 1. For a national
sitting, pass its type and round explicitly; the private study-only JSON
records both fields. Use a type-prefixed audit batch so the round and subject
are unambiguous. Decision files follow the sitting-specific directory
`classification/decisions/national-<year>-<round>/<year>-<subject>.json`.
The source-only importer requires matching `--type` and `--round` flags and
checks them against both study and validated files before its rollback-only
preview or backed-up apply.

Example for a national round-2 research batch (placeholders only; no question
text or answer data is part of this public example):

```sh
php scripts/references/export_server_candidates.php \
  --workspace="<approved dentistry workspace UUID>" \
  --type=national --year=1404 --round=2 \
  --subject=community-dentistry \
  --out="<protected research root>/bank-sittings/1404/national-r2-study.json"

python3 -B scripts/references/apply_classification.py \
  --sitting="<protected research root>/bank-sittings/1404/national-r2-study.json" \
  --decisions="<protected research root>/classification/decisions/national-1404-2/1404-community-dentistry.json" \
  --out="<protected research root>/classification/sittings/1404-national-r2-validated.json" \
  --pdf-pages

python3 -B scripts/references/audit_private_study_batches.py \
  --local="<protected research root>" \
  --batch=national:1404:2:community-dentistry:national-r2 \
  --out="<protected research root>/classification/reports/national-r2-audit.json"

php scripts/references/import_verified_sources.php \
  --workspace="<approved dentistry workspace UUID>" \
  --type=national --year=1404 --round=2 \
  --subject=community-dentistry --stem=national-r2 --expected=1 \
  --audit="<protected research root>/classification/reports/national-r2-audit.json"
```

The importer uses the type-specific official validity row and expects the
validated sitting at `classification/sittings/1404-national-r2-validated.json`.
The command above is a rollback-only preview; apply still requires a fresh
verified full backup and a unique private receipt. Residency imports continue
to use their existing CLI form and round-1 scope.

## National 1405 (مرداد) — done 2026-10-11

| Subject | With chapter | Notes |
|---|---|---|
| endodontics | 11 / 12 | Q15 undecided (AAE form is a figure) |
| periodontics | 11 / 12 | Q73 not found |
| oral surgery | 10 / 12 | Q85 key doubtful (trismus and immunocompromised both listed); Q88 frequency is a figure |
| oral medicine | 7 / 12 | Q25, Q27 only in out-of-scope chapters / summary pages |
| oral pathology | 11 / 12 | Q39 not found |
| oral radiology | 9 / 12 | Q123 only in ch. 27 (out of scope) |
| orthodontics | 6 / 12 | **Q1: key (medial to Meckel) contradicts Proffit p43 ("just lateral")** — owner to review the key |
| community dentistry | 6 / 8 | Q133, Q138 not decided |
| dental materials | 3 / 8 | |
| prosthodontics | 3 / 22 | only Zarb ready |
| operative dentistry | 3 / 12 | only Summit ready; several facts outside Summit's scope chapters |
| pediatric dentistry | 0 / 12 | McDonald 11e not ready |
| English, basic sciences | — | no official reference |
| **Total** | **80 / 210** | 0 rejected |

Run: validated sitting `classification/sittings/national-1405-1.json`;
dry run 210 questions, `questions_changed 0`, sources 80; backup
`/var/backups/fanoos/20261011T024417Z-19b39d6d` (verified); import; publish
(assessment `01a12826-8a7f-78f2-9414-4974fa8281bb`, version
`01a128d9-4464-7b05-aa6f-87a052461a1a`). Read-back: 80 sources, all with
`pdf_page` and `pdf_sha256`; the runner shows e.g. Q13 «فصل ۱ · پاتوژنز
بیماری‌های پالپ و پری‌اپیکال» (Torabinejad 6e, PDF p25).

## National 1404-1 (مرداد) — done 2026-10-11

Before classifying, the converter bug that filed Q141–Q146 (general
pathology) under oral pathology was fixed (PR #288). The site's sitting was
rebuilt from the docx: the diff against the live sitting showed only those six
subjects; dry run, backup `/var/backups/fanoos/20261011T030045Z-d3447c94`
(verified), import, publish.

61 / 210 sources (0 rejected), with the same editions and gaps as 1405.
Validated sitting `classification/sittings/national-1404-1.json`; backup
`/var/backups/fanoos/20261011T040220Z-fd67a915` (verified); assessment
`01a12826-98fd-746f-a272-f35ea19d6011`.
**Q6 orthodontics: the key (activator) conflicts with Proffit 6e, which
recommends a hybrid functional appliance** — owner to review the key.

## National 1404-2 (دی) — done 2026-10-11

| Subject | With chapter | Book |
|---|---|---|
| endodontics | 6 | Torabinejad 6e |
| periodontics | 9 | Carranza 13e |
| oral surgery | 9 | Hupp 7e / Malamed 7e |
| oral medicine | 9 | Burket 12e / Little-Falace 9e |
| oral pathology | 12 | Neville 5e |
| oral radiology | 10 | White-Pharoah 8e |
| orthodontics | 10 | Proffit 6e |
| community dentistry | 5 | National oral health 1394 |
| dental materials | 7 | Craig 14e |
| operative dentistry | 4 | Summit 4e (Sturdevant not ready) |
| prosthodontics | 2 | Zarb 13e (fixed/RPD books not ready) |
| pediatric, English, basic sciences | 0 | McDonald 11e not ready / no reference |
| **Total** | **83 / 210** | 0 rejected |

Validated sitting `classification/sittings/national-1404-2.json`; dry run
210 questions, `questions_changed 0`, sources 83; backup
`/var/backups/fanoos/20261011T050943Z-ad6dea07` (verified); assessment
`01a12826-8262-7a42-85b1-2d43a6822ae4`, version
`01a1295e-a94b-78ea-a6f9-6c90edecee5d` (206 questions; Q108, Q189, Q191 and
Q204 left out because their official answer is voided).

## Known gap: `questions_changed` does not cover the subject

The importer's `questions_changed` compares stem, image and choices only. A
changed subject is written without being counted. Before importing a rebuilt
sitting, diff its subjects against the site's (as was done for 1404-1).
