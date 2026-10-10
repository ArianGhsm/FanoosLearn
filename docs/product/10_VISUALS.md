# Visual study material: mind maps, flowcharts, tables, capsules

Owner request (2026-10-11): MedoFast-style visual summaries — its «مدومایند»
(3,000+ mind maps: tree summaries per topic) and «کپسول» (short high-yield
summaries) — built on FANOOS's own strength: every visual is made from the
book pages a question comes from, and says so.

## What a visual is

| Kind | Body | Drawn by |
|---|---|---|
| `mindmap` | `tree`: a JSON outline `{text, note?, children: [...]}`, ≤ 7 levels, ≤ 600 nodes | the site, as a collapsible tree (root, branches, leaves) |
| `flowchart`, `diagram` | `image`: PNG / WebP / JPEG (no SVG, which can carry script) — or a `tree` when the flow is a hierarchy | an `<img>` served by the API with the student's access |
| `table`, `capsule` | `markdown`: `#` headings, `-`/`1.` lists, `\| a \| b \|` tables, `**bold**` — nothing else | the site, from text only (never HTML) |

Each visual names what it explains (`chapters`: `reference@edition#node`,
`concepts`, `questions`) and the book pages it was made from (`from`: edition,
`pdf_page_from`–`pdf_page_to`, the PDF's SHA-256). Like every machine-made
value it has `origin` and `confidence`; a reviewed visual is never
overwritten by an import. Only `published` visuals reach students.

Tables `bank_visuals` and `bank_visual_links` (migration 0045, data model 05);
file format `contracts/bank/visuals.schema.json`, example
`contracts/bank/examples/visuals.example.json`.

## Making one (the rule that keeps it true)

1. Start from a chapter that has questions (the book view shows which).
2. Read its pages in the verified PDF (09 §3; one page at a time, nothing
   copied out of the book).
3. Write the outline / capsule from those pages only; cover what the
   chapter's questions asked first.
4. Record `from` with the exact PDF pages and the PDF's SHA-256, link the
   chapter and the questions it explains, keep `origin: ai` and
   `status: draft` until a person has checked it against the pages, then
   publish (`status: published`, re-import).

```sh
php scripts/import/import-bank.php check  --workspace=<uuid> --file=<visuals.json> [--assets=<folder>]
php scripts/import/import-bank.php import --workspace=<uuid> --file=<visuals.json> [--assets=<folder>] [--dry-run]
```

A visual file is study material, not exam content: no backup gate beyond the
normal one is needed, but drafts are imported first and published only after
review.

## Where students find them

- `/app/visuals` — the library, by subject, with a kind filter and search
  (home menu «نقشه‌های ذهنی»).
- `/app/visuals?edition=<ref@ed>&chapter=<chNN>` — one chapter's, linked from
  the bank's book-and-chapter view («نقشه · N» beside a chapter).
- `/app/visuals/<key>` — one visual, with the chapters and concepts it
  explains and the pages it was made from.

API: `GET /bank/visuals` (`?subject=&kind=&edition=&chapter=`),
`GET /bank/visuals/{key}`, `GET /bank/visuals/{key}/image`.
