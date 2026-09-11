# Navid Daily Assignment Automation

Status: deployed and enabled for Telegram through the verified Vercel relay.

Last verified: 2026-08-14

## Purpose

Once per Tehran calendar day, Dent1402Bot asks the website for a fresh Navid
captcha. The Telegram bot sends the image only to the configured owner using a
Force Reply prompt. The owner replies to that exact message with the captcha
code. After successful verification, the website syncs Navid assignments and
creates canonical website notifications only for assignments not present in
the prior successful snapshot.

The existing notification pipeline then distributes those records to linked
Telegram and Bale users. Bot delivery receipts remain transport state; the
website notification store remains the content, audience and read-state source
of truth.

The owner Navid screen renders assignments as a bounded escaped Rich Text
table plus an expandable nearest-assignment card. Telegram HTML and Bale
Markdown come from the same shared UI and transport conversion.

## Class-group screenshot reminders

The bot adapter contains a disabled shared Telegram/Bale claim/send/ack worker
for cropped assignment-card screenshots. The canonical website must create the
schedule and screenshot before either runtime flag is enabled:

- `new`: once when a genuinely new assignment is discovered after the silent
  baseline;
- `week`: once after crossing into seven days or less before the deadline,
  while more than one day remains;
- `day`: once after crossing into one day or less, before the deadline.

The website keys delivery by assignment, threshold, platform and delivery
profile, uses an expiring lease, and returns a cropped PNG containing only that
assignment card. Navigation, account data, credentials, cookies, other
assignments and diagnostics must not appear. A late poll may send the first
useful threshold after crossing but never backfills expired history or sends
all thresholds at once.

Signed actions are `claimNavidGroupDeliveriesV1` and
`ackNavidGroupDeliveryV1`. A claim contains `deliveryId`, `eventType`, bounded
canonical `assignment`, and `screenshotDataUri`. The bot records a local
receipt after platform send and before ACK. The group identifier stays in the
root-only bot environment and is not sent to or inferred by the website.

The first preview goes only to the configured owner private chat. Enabling the
real class group is a separate action after owner acceptance and a fresh
baseline.

## Security And Idempotency

- Only the linked existing-site owner may start or complete the automation.
- Captcha automation actions are accepted only from the Telegram platform.
- The challenge is bound to the current Tehran date and expires under the
  existing ten-minute Navid challenge policy.
- The bot accepts a captcha only from the owner and only as a reply to the exact
  challenge message ID.
- Captcha codes, Navid credentials, cookies and challenge state are not logged.
- Repeated starts return the active challenge or `already-completed`; they do
  not run duplicate daily synchronization.
- The first successful snapshot is a baseline and does not broadcast all
  historical assignments. Later new assignment keys create one deduplicated
  `navid-created:<assignmentKey>` website notification.
- Users receive bot notifications only if they started and linked that bot;
  Telegram/Bale cannot message arbitrary unlinked accounts.

## Runtime Flow

1. The bot loop checks the configured Tehran hour and durable SQLite state.
2. It calls signed action `navidDailyStart` through the existing site service
   client.
3. The website creates/reuses the current private Navid challenge and returns
   the captcha over the signed owner-only response.
4. Telegram sends the image to the owner with native HTML rich text and Force
   Reply.
5. The owner replies to that message. `/navid` requests a fresh challenge if
   the image expired.
6. The bot calls signed action `navidDailyComplete` with the current date and
   sanitized code.
7. The website validates the challenge, syncs assignments and creates canonical
   notifications for genuinely new assignments.
8. Existing notification claim/ack logic sends those notifications to linked
   platform users without creating a second feed or read state.

## Configuration

Telegram runtime:

```text
DENT_BOT_NAVID_DAILY_ENABLED=0
DENT_BOT_NAVID_DAILY_HOUR=9
DENT_BOT_NAVID_DAILY_TIMEZONE=Asia/Tehran
DENT_BOT_NAVID_RETRY_SECONDS=3600
DENT_BOT_NAVID_GROUP_ENABLED=0
DENT_BOT_NAVID_GROUP_CHAT_ID=0
DENT_BOT_NAVID_GROUP_POLL_SECONDS=60
```

Website/runtime timezone override:

```text
DENT_NAVID_DAILY_TIMEZONE=Asia/Tehran
```

The checked-in default remains disabled. Production may set
`DENT_BOT_NAVID_DAILY_ENABLED=1` only after the signed site health check passes.
The current Iran VPS reaches the signed website API directly; the laptop must
never be a relay.

## Current Availability

- Telegram and Bale run on the sole Iran VPS and both reach the signed website
  API directly; only Telegram Bot API traffic uses the isolated local egress.
- Interactive `/navid` challenge/reply code is shared and available on both
  platforms.
- Exactly one runtime may own the automatic daily trigger, preventing duplicate
  captcha challenges when both bots are online. This coordinator choice does
  not remove the interactive Navid option from either bot.
- Group screenshot delivery remains disabled until the website lease/screenshot
  actions are deployed and the exact destination is explicitly approved.

A real owner captcha completion remains the final end-to-end workflow check.
Notification fan-out continues to track Telegram and Bale deliveries
independently.

## Tests

```powershell
python -m unittest tests.test_dent_bot
python ..\scripts\test_navid_bot_automation_contracts.py
php -l ..\public_html\api\navid_service.php
php -l ..\public_html\api\bot_navid.php
```

Before activation, run a real owner challenge, add a synthetic new assignment
in an isolated test, verify exactly one website notification, then verify one
Telegram and one Bale delivery from reachable runtimes. Do not use a real
student broadcast as the first test.
