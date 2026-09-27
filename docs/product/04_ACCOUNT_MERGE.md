# Joining a student's bot account and website account

**Status:** the structure is laid out. The schema and a read-only planner
exist; the merge itself is not built yet. Owner request, 2026-09-27.

## Why two accounts happen

The bot and the website are two front doors onto one account model, but a
student can walk through both separately:

- **Bot:** the join wizard ends in phone verification, and the verified phone
  finds or creates an account (`ClassMembershipService::requireVerifiedAccount`),
  named after the phone and linked to that chat.
- **Website:** sign-up creates an account with a username and password and no
  phone (`StudentRegistrationService::register`).

The same person can then hold two accounts, each with its own attempts,
purchases and memberships. Two actions already notice the collision and
refuse rather than guess:

- adding that phone on the website gives `account_phone_in_use`;
- linking that chat from the website gives `platform_subject_conflict`.

Both refusals are where a merge will be offered.

## Rules

1. **The website account survives.** It has the username and password, the
   only way to sign in to the site. The bot-made account is the *source* and
   is folded into the *target*.
2. **Both halves are proven in one flow, by the person.**
   - The target is proven by being signed in on the website.
   - The source is proven by one of:
     - (a) a link code opened in the bot, from the chat the source is linked
       to (the existing link-challenge flow); or
     - (b) an SMS code sent to the phone the source is registered with
       (`AccountPhoneService`'s OTP).

   No operator or owner merges accounts on a guess, and no merge happens from
   a single proof.
3. **The student confirms it.** They see what will be combined (the
   planner's inventory) and confirm. A merge is irreversible short of a
   database restore.
4. **One transaction.** Both accounts are locked, rows are moved, conflicts
   are resolved by the fixed rules below, and one `iam_account_merges` row
   records who, when, which proof and what moved. The audit log records the
   same.
5. **The source is not deleted.** It becomes a tombstone
   (`iam_users.merged_into_user_id`, status `merged`), so every historical
   foreign key and audit row still resolves. It can never sign in again.

## What moves, what stays, what is revoked

| Table | Column | Treatment |
|---|---|---|
| `tenant_workspace_memberships` | `user_id` | **move**; a workspace both belong to keeps the target's row |
| `rbac_role_assignments` | `user_id` | **move**; a duplicate role at the same scope is dropped |
| `exam_attempts` (results follow by attempt id) | `user_id` | **move**; `exam_question_read_rate_guards` for the source is dropped |
| `entitlement_grants` | `subject_user_id` | **move** (purchases stay the person's) |
| `commerce_orders` | `buyer_user_id` | **move** |
| `content_delivery_issuances`, `protected_media_artifacts` | `user_id` | **move** (forensic marks keep pointing at the same person) |
| `notification_recipients` | `user_id` | **move** |
| `notification_preferences`, `notification_channel_preferences` | `user_id` | the target's are kept; the source's only fill gaps |
| `messaging_links` | `user_id` | **move** per platform; if the target already has a live link on that platform, the source's is revoked |
| `iam_user_identifiers` | `user_id` | the phone **moves** to the target; the target's username stays |
| `iam_student_profiles` | `user_id` | the target's profile is kept; empty fields are filled from the source's |
| `class_creation_requests`, `tenant_workspace_role_upgrade_requests` (`user_id`), `form_submissions` | | **move** |
| `iam_sessions`, `iam_authenticators` | `user_id` | the source's are **revoked** |
| authorship and review columns (`*_by_user_id`, `reviewer_user_id`, `owner_user_id`), `audit_events` | | **stay**, as history of what that account did |

Attempt limits: attempts move as they are. A merged student may have more
attempts at an assessment than its limit allows. They keep them, and simply
cannot start another.

## Built now

- Migration `0026_account_merges.sql` (expand-only) adds:
  - `iam_account_merges`, the record of each merge;
  - `iam_users.merged_into_user_id`, the tombstone pointer.
- `AccountMergePlanner::inventory($sourceUserId, $targetUserId)` is
  **read-only**. For every row in the table above, it counts what would move,
  be kept, be dropped as a duplicate or be revoked. It is the "what will be
  combined" screen's data and the executor's checklist. It is tested against
  the schema, so a new user-owned table that the plan does not cover is noticed.

## Still to build

1. `AccountMergeService::merge(target, source, proof)`, following the table
   above in one transaction, recording `iam_account_merges` and turning the
   source into a tombstone.
2. Offering the merge at the two refusals: the account page (phone in use)
   and the bot's link step (chat linked elsewhere). Each shows the planner's
   inventory and a confirmation.
3. A bot-side notice after a merge, so the chat knows it now belongs to the
   website account.
