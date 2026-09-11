# Homogeneous Student Assistant v1

## Outcome

Telegram, Bale, and the website are three views of one student-assistant
system. The website remains authoritative for identity, grades, calendar,
notifications, preferences, and durable acknowledgement state. Both bots use
the same application screens, action names, permissions, and signed website
contract. The Iran VPS integration worker owns only bounded upstream sessions,
jobs, receipts, and encrypted connector material.

No bot database may become a second copy of grades, assignments, reminder
acknowledgements, reservation state, or website identity.

## Feature contract

| Feature | Canonical source | Telegram/Bale behavior | Website behavior |
| --- | --- | --- | --- |
| Grades | Website grade store | Same rich table, refreshed on demand | Existing grade view |
| Section/class/exam/quiz | Website calendar | Previous-night personal reminder | Calendar and preferences |
| Navid assignments | Navid snapshot in website | Personal reminders and `submitted` action | Snapshot, durable acknowledgement, undo |
| Meal reservation | Food system receipt + website acknowledgement | Remind, self-attest, or start confirmed reservation | Credentials, history, receipt, preferences |
| Service reservation | Saba receipt + website acknowledgement | Remind, self-attest, or start confirmed reservation | Credentials/OTP state, history, receipt |
| Assignment upload | Navid upload receipt | Collect file, show preview, require final confirmation | Job status, audit, receipt |

## Reminder policy (Asia/Tehran)

- Section, class, exam, and quiz: default at 20:00 on the previous calendar
  day. Users may disable domains or select a different hour on the website.
- Meal reservation for the following week: Sunday 20:00, Monday 20:00,
  Tuesday 16:00, and a final reminder Tuesday 22:00. Deadline is Tuesday
  23:59.
- Service reservation for the following week: Wednesday 20:00, Thursday
  20:00, Friday 17:30, and a final reminder Friday 22:00. Deadline is Friday
  23:59.
- The extra final-day reminder follows the established class-channel style.
  All times are configurable centrally; they are never separately configured
  in Telegram and Bale.
- A scheduler restart must recover missed slots without duplicating a slot.
  The pure policy lives in `dent_bot/reminder_policy.py`; durable slot and user
  state live on the website.

Every reminder has a stable `objectKey` and, for weekly reservations, a stable
`cycleKey` such as `meal:2026-08-25`. A durable acknowledgement is scoped to
the linked website account, domain, object key, and cycle. It records:

- `user_attested`: the student pressed “I submitted/reserved; stop reminding”; or
- `upstream_verified`: the integration received and stored a real upstream
  receipt.

The UI must not present `user_attested` as proof that the university system
accepted a reservation or upload. Users can undo an attestation until the
deadline.

## Notification actions

The website may attach up to four bounded actions to a notification:

```json
{
  "actions": [
    {"ref": "submitted", "label": "تحویل دادم؛ دیگه نگو", "style": "success"}
  ]
}
```

`ref` is a website-issued opaque token matching `[A-Za-z0-9_-]{1,20}`. Bots
render only the label/style and send the token back through the signed
`performNotificationAction` operation. The website re-resolves the linked
account, recipient membership, current action, deadline, and idempotency before
writing canonical state. Bot SQLite stores neither acknowledgement nor domain
meaning.

## External connector facts verified 2026-08-25

- Food: `https://foodstu.tums.ac.ir/`; username, password, and image CAPTCHA;
  central-authentication login is also linked.
- Service: `https://sabaapp.tums.ac.ir/`; mobile flow and an alternate
  username/email flow. The later OTP/password step must be recorded after a
  user-authorized test account is used.
- Navid/Rira: `https://navid.tums.ac.ir/`; username, password, and image
  CAPTCHA.
- The exact class announcement channel was inspected read-only. Its current
  reminder links point to the Food and Saba origins above. No message, reaction,
  membership change, or personal-account send was performed.

## Credentials and CAPTCHA

Students never paste Navid, Food, Saba, OTP, or CAPTCHA credentials into a bot
chat. The authenticated website provides the credential screen, requires a
recent account session, supports revoke/replace, and encrypts every secret at
rest with an environment-held key. APIs never return stored plaintext to a bot
process.

If a connector needs a browser/runtime on the VPS, the website creates a
short-lived job grant encrypted for the dedicated integration worker. The
worker runs one heavy browser job at a time on the 2 GB server, uses the direct
Iran route, and exposes no public administration endpoint.

Automatic CAPTCHA solving or anti-bot bypass is not part of this system. The
supported flow is user-in-the-loop:

1. The connector opens an upstream session and returns a short-lived opaque
   challenge plus image bytes.
2. The same linked student receives the image privately on Telegram, Bale, or
   the website.
3. The answer is bound to that user, job, platform message, and expiry; it is
   rate-limited, used once, and not logged.
4. The connector resumes the existing session and returns a verified receipt
   or a bounded failure.

The shared bot implementation uses `/assistant` and opaque site-issued action
references. When an action returns `status=challenge`, it accepts only bounded
PNG/JPEG image data, sends it to that student's private chat, and stores only
the opaque challenge/job refs, platform message binding and expiry. A valid
answer is 4-12 alphanumeric characters, is atomically consumed only as a reply
to the exact message, is never stored, and resumes through
`integrationChallengeAnswerV1`. Telegram and Bale run the same code against
separate platform identities. The production capability flags remain disabled
until the website and worker contract passes the release gates.

If an official API or university-approved no-CAPTCHA integration becomes
available, it replaces this interaction without changing the product workflow.

## Reservation transaction

“Reserve” is never a silent background mutation. The student sees the exact
week, meal/route, date, cost if any, and selected options, then explicitly
confirms the final upstream submission. The result is successful only when the
upstream system returns a verifiable receipt or a post-submit read confirms the
new state. Retries use one idempotency key and must not double-reserve.

“Reserved; stop reminding me” is a separate action. It suppresses future
reminders for that cycle but is labeled self-reported until verified upstream.

## Assignment upload transaction

Telegram and Bale use the same steps: choose assignment, receive one file,
validate extension/MIME/signature/size, hash it, show filename/course/deadline,
require final confirmation, upload once, and show the Navid receipt. Executable
files, path-bearing names, zip bombs, unsupported archives, and over-limit files
fail closed. Temporary bytes have bounded encrypted retention and are removed
after a verified upload or expiry. A retry reuses the content hash and job key.

## Backup and recovery

Every server is replaceable. Before any production rollout, create a fresh,
DPAPI-encrypted, checksum-verified laptop backup and prove restore. Backups must
include canonical databases, reminder/receipt/audit state, encrypted connector
vault material, worker private configuration/keys, in-flight authoritative
uploads, systemd/Nginx configuration, manifests, and schema versions. They
exclude browser caches, dependencies, logs beyond policy, and completed
temporary uploads.

Recovery on a new VPS must resume pending idempotent jobs and reminders from
their last durable state. A job may be retried; a reservation/upload may never
be declared successful merely because its old worker disappeared.

## Release gates

1. Website implements the versioned contract and migration with rollback.
2. Sanitized connector fixtures and one user-authorized live account validate
   each upstream flow without creating a real reservation/upload.
3. Telegram and Bale contract tests pass from the same payloads.
4. Website, Telegram, and Bale show the same acknowledgement/preferences.
5. A staging reservation/upload requires explicit final confirmation and
   returns an upstream receipt.
6. Restore test recreates the integration worker and durable state on a clean
   host.
7. Fresh laptop backup, phased deploy, health checks, then user-authorized live
   smoke tests.
