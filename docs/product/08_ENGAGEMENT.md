# 08 — Engagement: points, ranking, coins, study rooms, affiliates

The owner's decision (2026-10-06): build the infrastructure for MedoFast's
engagement features now, while the bank is still being filled, so they work
the day questions arrive. This document is the design; each part lands in
its own pull request and is marked here when it ships.

| Part | Status |
|---|---|
| Difficulty level, measured | ✅ |
| Daily points and ranking | ✅ |
| Coins earned | ✅ |
| Coins spent, discount codes | planned |
| Group study room | planned |
| Affiliate programme | planned |

## Difficulty (سطح دشواری)

`Engagement\QuestionDifficulty` measures a question from everyone's answers
in `exam_question_stats`, once at least 10 answers are in:

| Share of right answers | Level |
|---|---|
| 70 % and above | easy (آسان) |
| 40–69 % | medium (متوسط) |
| below 40 % | hard (دشوار) |

Below 10 answers, the question's own authored `difficulty` stands in; with
neither, the level is unknown. The runner shows the measured level, which is
served as `stats.difficulty` with each question. If the level is unknown, the
runner shows the authored one, or a dash.

## Daily points and ranking (امتیاز روزانه و رتبه)

**Earning points.**
- Each right answer earns 5 (easy), 10 (medium) or 20 (hard) points. A
  question of unknown difficulty earns 10.
- Points are awarded when an attempt is scored, inside the same transaction
  (`ExamService::scoreAndClose` → `PointsService::award`).
- The answers counted are the ones `QuestionStatsRecorder` counts. A blank
  earns nothing, and neither does an answer revealed before choosing.
- A question earns points **once per day**. Retaking it the same day adds
  nothing, so points cannot be farmed.
  (`engagement_point_awards` has one row per user, day and question.)

**Calendar.**
- Days are the workspace's local days.
- A week runs Saturday to Friday.
- A month is a Jalali month (`Support\JalaliCalendar`, arithmetic only, so it
  does not need the intl extension).

**Daily goal.** The goal is 500 points a day. The first time a day crosses it,
the row is marked (`goal_reached_at`) and one coin is credited to
`engagement_coin_ledger`. The ledger has one row per (reason, reference),
which makes the credit idempotent.

**Ranking.**
- Among the students of the same workspace only.
- A student sees **their own** place and how many students have points in
  the period. Nobody else's name or score is shown.
- A student without points has no place.

**Where it shows.**
- `GET /workspaces/{id}/points` feeds `/app/points`: the goal ring, coins, the
  three places, and the last seven days, weeks and Jalali months.
- The exam report shows `points_earned` and `daily_goal_reached` from the
  submit response.

**Not rebuilt.** Points are a record of what was earned. Unlike the question
statistics, `rebuild-question-stats.php` does not recompute them.

## Coins and discount codes (planned)

- The coin balance is the sum of the ledger.
- Spending a coin writes a negative row whose reference is the discount code
  it bought.
- The owner defines the "boxes" (how many coins buy which discount on which
  plan) as data. A box mints a personal, single-use code that expires after
  a few days.
- The same discount-code table serves codes the owner creates directly.
- Checkout validates a code against its product, expiry, use count and
  owner, and records the redemption with the order.

## Group study room (planned)

- A private invite link.
- At most 10 members per room, and each member in at most 10 rooms.
- Each member's study today, shown side by side: minutes from exams and the
  timer, questions answered, and points.
- Leave at any time. The creator can remove members.

## Affiliate programme (planned)

- Each account can get a referral link. A sign-up through it is attributed
  to the referrer.
- A paid order by an attributed buyer earns the referrer a commission,
  recorded in a ledger. Payouts are marked by the owner.
- The rates are the owner's to set; until then the programme stays off.
