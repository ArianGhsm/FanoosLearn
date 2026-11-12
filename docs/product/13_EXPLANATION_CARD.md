# The explanation card (پاسخنامه) in the runner

Status: implemented on `feat/explanation-card-20261112`; pilot content in
`data/bank/explanations/residency-1406-pilot.json` (10 questions, not yet
imported). Writing rules: `11_STUDY_CONTENT_1406_PLAN.md` §5.2.

## Where it shows

One renderer, `pages/explanation-view.js`, everywhere an explanation is read:

| Screen | Call | Answer line |
|---|---|---|
| Learning-mode reveal (under the question) | `renderReveal` | hidden — the reveal head already says it |
| Post-exam review | `renderReviewQuestion` | shown |
| مرور اشتباه‌ها | `mistakes-review.js` | shown |

## What it draws

From the Markdown `BankPublisher::explanation()` already writes (parsed by
`pages/explanation-rules.js`, pure and tested):

1. **Answer line** (`**پاسخ: …**`): success-soft strip with a check icon.
2. **Reasoning** (`short_answer`): plain Markdown with `{{key|term|warn|note:…}}`.
3. **از رفرنس** (`reference_explanation`): info-coloured quote block, book icon.
4. **بررسی گزینه‌های دیگر** (`why_wrong`): one row per other choice — a
   letter badge and a tag. A `why_wrong` text that starts with `درست —` is a
   true statement in a negative question («همه صحیح است بجز») and is drawn
   green «درست است»; any other is red «رد». The row the student picked is
   outlined and tagged «انتخاب تو».
5. **نکته‌ی آزمون** (accent callout, bulb) and **دام رایج** (warning callout,
   triangle).
6. **منبع** line (`source_location`, else the primary source):
   `Book edition · فصل N · ص page`.

Colours are the runner's tokens only (success, danger, info, accent,
warning and their `-soft` forms), so light and dark themes follow
automatically. Unknown headings or older free-form Markdown fall back to the
reasoning block unchanged.

## Pilot file

`residency-1406-pilot.json` maps `question_key` → the `explanation` object of
`06_QUESTION_FORMAT.md` (`short`, `reference`, `why_wrong`, `tip`, `trap`,
`location`, `from{ref,pdf_page,pdf_sha256}`). Every `from` is the question's
primary verified page in an official residency-1406 edition. Reaching the
live site needs the normal path: merge, updater deploy, then an approved
import with its own verified backup (AGENTS.md); this branch writes nothing
to production.
