# Website handoff: Exam Bot v1

Owner: the task that owns `../dentistry1402tums`. This workspace must not edit
or deploy that repository.

## Existing behavior to preserve

The current site already supports free/paid course access, discount-aware
collection orders, assessment and learning modes, MCQ and essay/learning-only
content, timers, question map, flags, strike/highlight/note study state,
mistake notebook, custom practice, weak-topic review, attempt history, ranking
and topic analytics. Course access is granted by owner/free access or a verified
paid order. These remain canonical.

## Required site work

1. Add a versioned `exam-bot-v1` service module to the existing signed bot API
   with `examHubV1`, `examOwnerHubV1` and `performExamActionV1`.
2. Migrate assessment and learning drafts/current question/reveals/custom
   practice from browser local storage to canonical active attempts. Retain
   local storage only as a non-authoritative presentation cache.
3. Persist every answer/navigation/study mutation atomically with optimistic
   revision and idempotent request IDs. Enforce a server clock and idempotent
   finalization.
4. Return one safe question at a time. Redact correct answer, correctness and
   explanation for active assessment attempts. Bind all opaque action refs to
   user, attempt, revision and purpose with short expiry.
5. Add explicit enrollment state/window/capacity if registration is distinct
   from existing course access. Existing evergreen courses may map to implicit
   enrollment. Verified paid access must grant the same enrollment/access
   visible from all three interfaces.
6. Reuse the existing payment quote/order/gateway/callback code. Calculate
   amount and discount on the server. A bot action only selects a bound quote;
   payment success requires verified callback.
7. Return the structured view defined in `docs/EXAM_BOT_SYSTEM_V1.md`. Do not
   return raw HTML or the entire exam payload.
8. Add owner authorization, confirmations and audits for publication, access,
   reset/revoke, pricing and other consequential actions.
9. Update the web exam client to use the same central attempt mutations, so an
   attempt can resume on another interface without divergence.
10. Expose a capability/version health result so operations can refuse to
    enable the bot flags against an older API.

## Required acceptance tests

- purchase on site appears in Telegram and Bale; purchase started in either bot
  appears on site only after verified callback;
- free, paid, unavailable, upcoming and owner-granted access;
- discount validity/expiry and duplicate order idempotency;
- resume site -> Telegram -> Bale with identical selected answers/current
  question/timer;
- simultaneous answer taps return a deterministic revision conflict and do not
  lose the winning write;
- retry does not duplicate answer, enrollment, order, attempt or report;
- timer expiry and final submit race produce exactly one terminal attempt;
- active assessment never leaks answer/explanation; learning reveals only after
  the answer/reveal action;
- question map, flags, notes, strike, mistake review, custom/weak-topic practice,
  history and results;
- owner/participant authorization and stale/expired action references;
- Persian, ZWNJ, RTL, emoji and media-link rendering on Telegram and Bale;
- legacy reports and paid access remain intact after migration;
- production backup is pulled to the laptop, checksum/JSON/SQLite verified and
  restore-tested before schema deployment.

The bot flags stay disabled until this handoff and signed authenticated
acceptance are complete.
