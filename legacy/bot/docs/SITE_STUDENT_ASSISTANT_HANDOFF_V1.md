# Website handoff: Student Assistant v1

This is the versioned website contract for Student Assistant v1.

Implementation checkpoint (2026-08-25): the website now implements the three
signed bot operations `studentAssistantSummaryV1`,
`performIntegrationActionV1`, and `integrationChallengeAnswerV1` with a
canonical website-owned store, opaque user/platform-bound actions, one-use
CAPTCHA challenges, bounded attempts, request idempotency, and an explicit
post-CAPTCHA confirmation boundary. The only executable connector is a
test-environment fixture guarded by both `DENT_APP_ENV=test|development` and
`DENT_STUDENT_ASSISTANT_TEST_CONNECTOR=1`; production returns no start action
until a reviewed encrypted credential screen and real Navid/Food/Saba worker
are connected. This avoids exposing a production button that can only simulate
success.

## Required storage changes

Extend the existing canonical notification/user state; do not add a bot-owned
database or another user directory.

- Durable `reminderAcks` keyed by canonical account + domain + `objectKey` +
  optional `cycleKey`.
- Fields: state (`active`, `undone`), evidence (`user_attested`,
  `upstream_verified`), source platform, actor, created/updated timestamps,
  upstream receipt reference, and bounded audit metadata.
- Reminder-slot ledger keyed by domain + object/cycle + scheduled instant +
  recipient, so scheduler restart is at-least-once but delivery intent is
  idempotent.
- Integration account metadata containing connector type, masked account label,
  credential version, status, last verified time, and revocation timestamp.
  Secret bytes must use the existing encrypted secret-storage pattern or a new
  reviewed AEAD vault; never JSON plaintext.
- Integration jobs and receipts with stable idempotency key, state machine,
  expiry, bounded error code, and no credential/captcha answer in logs.
- Active CAPTCHA records keyed by a random challenge reference and bound to the
  canonical account, connector job, origin platform, current credential/session
  version, expiry and one-use state. Store the upstream session only in the
  encrypted worker vault; never place cookies or the answer in the bot store.

## Signed bot API additions

### `performNotificationAction`

Input: `notificationId`, opaque `actionRef`.

The endpoint must re-resolve platform identity, linked canonical account,
recipient membership, notification/action availability, deadline, and current
state. It returns an idempotent result and a short safe Persian `message`.

Initial semantics:

- `assignment-submitted`: durable per-assignment acknowledgement.
- `meal-reserved-self`: durable per-meal-cycle self-attestation.
- `service-reserved-self`: durable per-service-cycle self-attestation.
- matching `undo-*` actions before the deadline.

The notification feed emits no action after it is no longer valid. Action refs
are opaque and at most 20 URL-safe characters; the bot does not send semantic
names or object IDs chosen by the client.

### Student-assistant query/workflow operations

Implement behind a `student-assistant-v1` capability flag:

- `studentAssistantSummaryV1`: current preferences, connector status, next
  deadlines, pending assignments, and acknowledgements.
- `integrationSetupLink`: ten-minute single-use website URL for a named
  connector. Never accept credentials through the bot API.
- `performIntegrationActionV1`: consume one opaque action reference issued in
  the current summary/job view. The website resolves whether it starts,
  confirms, refreshes, checks or cancels a job; callbacks contain no connector,
  job, assignment or reservation authority.
- `integrationChallengeAnswerV1`: consume one short-lived answer and resume
  that exact job. Both Telegram and Bale identities are supported.

Every mutation accepts a stable request/idempotency key derived from the bot
callback/update and returns the existing result on retry.

### CAPTCHA response contract

An action that reaches an upstream image challenge returns:

```json
{
  "success": true,
  "status": "challenge",
  "jobRef": "opaque-12-to-80-char-reference",
  "connector": "food",
  "challenge": {
    "ref": "opaque-12-to-80-char-reference",
    "connector": "food",
    "imageDataUri": "data:image/png;base64,...",
    "expiresAt": "2026-08-25T12:10:00+03:30"
  },
  "view": {
    "title": "رزرو غذای هفته بعد",
    "statusText": "در انتظار پاسخ تصویر"
  }
}
```

The image is bounded to PNG/JPEG and 2 MiB. The signed response is delivered
only to the requesting private bot chat. The bot persists only opaque refs,
connector, platform user binding, platform message ID and expiry; it never
persists the image or answer.

The student must Reply to the exact challenge message. The bot atomically
consumes that local binding before sending:

```json
{
  "action": "integrationChallengeAnswerV1",
  "contractVersion": "student-assistant-v1",
  "challengeRef": "opaque-reference",
  "jobRef": "opaque-reference",
  "answer": "A7K2P",
  "requestId": "stable-64-hex-idempotency-key"
}
```

The website independently verifies current platform identity, linked canonical
account, challenge/job ownership, connector session, expiry, attempt limit and
unused state. It marks the challenge used before resuming the connector. A
wrong answer may return a fresh challenge; a replay, cross-user/platform
answer, expired answer or answer for another job fails closed. Normal and
gateway/deployment logs must redact the `answer` field and image bytes.

The resumed connector returns either another challenge, a pre-submit preview,
or a verified terminal receipt. CAPTCHA completion alone must never reserve,
upload, pay, or declare success; mutations still require the explicit final
confirmation defined by the job workflow.

## Notification generation

- Reuse `notifications_store.php` and the current delivery claim/ack pipeline.
- Generate section/class/exam/quiz reminders from the canonical website
  calendar at 20:00 Tehran on the previous day.
- Generate Navid assignment reminders from a stable assignment key and due
  time. Existing 7/2/1-day thresholds may remain, but a current durable
  `assignment-submitted` acknowledgement suppresses all later thresholds.
- Generate meal/service reminders using the exact policy in
  `dent_bot/reminder_policy.py` and `docs/STUDENT_ASSISTANT_V1.md`.
- Website preferences and acknowledgement state affect website, Telegram, and
  Bale together. Platform availability affects delivery only, never business
  state.

## Website UI

Add one account-area “Student assistant” page with:

- domain toggles and reminder time;
- current Navid/Food/Saba connection status and revoke/replace controls;
- secure credential forms that warn users not to send passwords in chat;
- upcoming assignments/reservation cycles with self-attested versus verified
  badges and undo where allowed;
- job history and upstream receipts;
- a same-user CAPTCHA dialog for active challenges;
- a delete/export path consistent with the website privacy contract.

## Security and audit acceptance

- Connector credentials are AEAD-encrypted with key rotation; the encryption
  key is environment-only and included only in the encrypted recovery bundle.
- Worker grants are short-lived, audience-bound, one-use, and encrypted to the
  worker; bot processes never receive plaintext credentials.
- CAPTCHA images/answers, passwords, OTPs, cookies, submitted assignment
  content, and receipt personal data are absent from normal logs and deployment
  notifications.
- File validation, rate limits, CSRF, HMAC nonce window, authorization, and
  idempotency tests cover every new action.
- A backup migration test proves a clean host can restore acknowledgements,
  pending jobs, encrypted vault material, and receipts without copying caches.
- Contract tests cover exact-message reply binding, one-use consumption,
  expiry, same-user/same-platform enforcement, wrong-answer fresh challenge,
  idempotent network retry, and absence of CAPTCHA/image material from logs and
  bot SQLite.

## Coordination gate

The signed website state machine and HTTP fixtures are implemented. Keep the
production reservation/upload menus disabled until encrypted credential setup,
the real connector worker and receipt verification are live, and both bot
adapters pass the same live contract smoke. The bot-side generic notification
action and rich grade table are backward-compatible and may ship independently.
