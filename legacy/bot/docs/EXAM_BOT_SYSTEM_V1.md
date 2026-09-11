# Exam Bot System v1

Status: bot adapter implemented behind a disabled capability flag; canonical
website API and data migration are required before activation.

## Product invariant

The website, Telegram and Bale are three interfaces to one exam system. The
website remains authoritative for catalog, course access, enrollment, prices,
discounts, verified orders, attempts, answers, timers, study state, reports and
owner permissions. The bots store none of that data and expose the same shared
`DentBotApp` workflow.

A purchase verified on the website must unlock the same course immediately in
both bots. An order started in a bot is created by the website from a
server-side quote and becomes successful only after the existing gateway
callback verifies it. The bot never accepts an amount, discount result, card
number, website password or OTP from callback data or chat.

## Participant experience

The exam hub includes active/resumable attempts, purchased/enrolled exams,
available free or paid exams, upcoming exams and history. Course detail shows
access state, canonical price/discount, enrollment window, progress and exams.
Account signup/linking stays on the authenticated website; the bot does not
collect credentials.

The common question screen supports:

- assessment and learning modes;
- one question at a time, previous/next and question map;
- multiple choice and the site's supported essay/learning-only variants;
- server-authoritative progress and remaining time;
- answer selection, flagging, option strike, notes and highlights;
- immediate answer/explanation only in learning mode;
- explicit review and confirmation before final assessment submission;
- score, correct/wrong/unanswered counts, class average/rank, topic analysis,
  history, mistake notebook, weak-topic review and custom practice;
- safe HTTPS media links with a readable fallback on both transports.

An active assessment response must not contain the correct option, correctness,
explanation or answer-derived analytics. The bot renderer suppresses feedback
defensively until the attempt is submitted/expired, but the website must redact
it at the API boundary as the real security control.

## Owner experience

The owner hub may expose catalog/course/exam status, participants, active and
completed attempts, access/enrollment, sales/order summaries, report analytics,
attempt reset/revoke and existing course availability/payment settings. Every
owner action is re-authorized by the website and consequential actions require
an explicit confirmation view and private audit event. Refunds, price changes,
publication, attempt reset and access revocation must never execute from a list
tap without confirmation.

The first release should map all capabilities already present on the site. A
site feature that lacks a safe API is linked to its authenticated website screen
instead of being reimplemented in bot storage.

## Canonical active attempt

The current browser-only assessment/learning drafts must migrate to a central
active-attempt record before activation. At minimum it contains an opaque
attempt reference, canonical user/exam/mode, immutable question-set version and
order, status, current question, answers, revealed questions, started/expiry
times, revision, last platform and bounded study-state references.

Every mutation is atomic and includes a stable request ID. The website applies
optimistic revision checks and returns the current view on conflict. One active
attempt is resumable from site, Telegram or Bale; a stale concurrent tap cannot
overwrite a newer answer. The server owns expiry and finalization. Assessment
submission is idempotent and cannot generate two reports or attempts.

Custom practice also uses a server-created immutable question set based on
topic, difficulty, count and status filters. Browser local storage may cache UI
preferences, never canonical answers or completion state.

## Bot contract

All signed calls use `contractVersion=exam-bot-v1`:

- `examHubV1`: current participant view;
- `examOwnerHubV1`: owner-authorized management view;
- `performExamActionV1`: execute one opaque action reference with a stable
  request ID and return the next view.

The response is a structured `view`, not HTML:

```json
{
  "success": true,
  "view": {
    "kind": "question",
    "title": "آزمون نمونه",
    "mode": "assessment",
    "attemptStatus": "active",
    "description": "متن کوتاه",
    "statusText": "وضعیت دسترسی یا ثبت‌نام",
    "progress": {"current": 3, "total": 20, "answered": 2, "flagged": 1, "remainingText": "۱۲:۴۰"},
    "question": {"text": "متن سؤال", "mediaUrl": "https://trusted.example/media/...", "note": "یادداشت شخصی"},
    "sections": [{"title": "خلاصه", "body": "متن ساده"}],
    "actions": [
      {"label": "گزینه ۱", "ref": "opaqueRef", "row": 0},
      {"label": "بعدی", "ref": "nextRef", "style": "primary", "row": 1}
    ],
    "siteUrl": "https://dentistry1402tums.ir/exams/..."
  }
}
```

Action references match `[A-Za-z0-9_-]{1,20}`, are short-lived, opaque,
single-purpose, bound to the linked user/current revision and safe for a Bot API
callback under 64 bytes. They contain no user, exam, question, option, price,
permission or URL. A repeated `requestId` returns the same result.

Payment confirmation is a normal server-produced view. Its explicit action may
create an order; the returned next view contains only the canonical quote and
HTTPS gateway URL. Enrollment is granted from verified website state, not from
the bot seeing that URL opened.

## Activation and recovery

`DENT_BOT_EXAMS_V1_ENABLED` and `DENT_BALE_EXAMS_V1_ENABLED` default to `0`.
Enable both together only after the site contract and migration are deployed,
authenticated participant/owner acceptance passes on both adapters, gateway
sandbox or zero-value test flows pass without a real charge, and a fresh
restore-verified laptop backup exists. No production attempt should depend on
bot-local data, so replacing the VPS requires only code/config restoration and
the canonical website continues from the last committed action.
