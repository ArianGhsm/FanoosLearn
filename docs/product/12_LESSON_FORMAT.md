# 12 — Writing a lesson (درسنامه): format and reader

How a written lesson is laid out so the reader at `/app/lessons/<id>` shows
it as a paged, MedoFast-style micro-lesson. The house style (what to write)
is in `11_STUDY_CONTENT_1406_PLAN.md` §5 (on its own PR until merged); this
page is the format (how to write it).

## Pages

A lesson's body is Markdown (`content.format = "markdown"`, `content.body`).

- Each line starting with `## ` begins a **page**; its text is the page title.
- Page 1 is generated: «فهرست مطالب», every page title with its page number,
  each one clickable. Text written **before** the first `## ` appears on this
  page as the lesson's introduction.
- A page titled with «خلاصه» or «جمع‌بندی» is the **summary page**: it is
  marked in the contents and framed in green. Put it last.
- `### ` is a sub-heading inside a page; it does not start a new page.
- A lesson with fewer than two `## ` sections is not paged: it reads as one
  page, exactly as before.

The reader shows a progress bar (0 % on the contents, 100 % on the last
page), previous / next, «page N of M», «go to page» and «contents». The arrow
keys move between pages (← forward, → back). The page is kept in the address
(`#p3`) and remembered **on this device**, so the lesson reopens where it was
left. Progress kept on the account (every device) and on the lessons list
comes with the lesson ↔ chapter links (plan Phase 1b/2).

## Inside a page

| Write | Shows | Use for |
|---|---|---|
| `- text` | a bullet with the FANOOS mark | one fact per bullet |
| `**text**` | bold | ordinary stress |
| `{{term:text}}` | blue, bold | a term being defined |
| `{{key:text}}` | red, bold, on a soft red background | the fact the exam asks |
| `{{note:text}}` | green, bold | a secondary fact |
| `{{warn:text}}` | orange, bold, wavy underline | an exception, "except", a common trap |
| `\| a \| b \|` lines | a table; a `\|---\|---\|` line under the first row makes it the header | the summary table, classifications, comparisons |

Each emphasis kind differs in shape as well as colour, and a screen reader
announces its kind («اصطلاح»، «نکته‌ی کلیدی»، «نکته»، «استثنا»). A malformed
token (unknown kind, empty text) shows as its literal text so the mistake is
visible. Nothing is ever read as HTML.

## Example

```markdown
مرور فصل ۱۰ استوردیونت ۷ برای آزمون ۱۴۰۶.

## باندینگ به مینا
- {{term:اچ اسید}} سطح مینا را {{key:میکرومکانیکال}} گیر می‌دهد.
- زمان اچ مینا {{key:۱۵ ثانیه}} است؛ {{warn:مینای دندان شیری}} بیشتر می‌خواهد.

## باندینگ به عاج
### اسمیر لایر
- {{note:در سیستم‌های سلف‌اچ}} حذف نمی‌شود.

## خلاصه و جمع‌بندی
| مبحث | نکات کلیدی |
|---|---|
| مینا | اچ ۱۵ ثانیه؛ گیر میکرومکانیکال |
| عاج | لایه‌ی هیبرید |
```

(The example shows the format only; real lessons are written from the
official 1406 edition's pages and record them.)

## Code

`apps/platform/public/assets/web/pages/lesson-pages.js` (paging rules),
`markdown-rules.js` (inline runs, tables), `markdown.js` (nodes),
`lesson-reader.js` (the reader); tests in `tests/web/lesson_pages_test.mjs`.
The same emphasis and tables render in explanations, which use the same
renderer.
