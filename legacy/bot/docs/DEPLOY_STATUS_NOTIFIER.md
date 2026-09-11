# Deploy Status Notifier Contract

## Scope

The central notifier sends operational deployment states for these service
identifiers:

- `website`
- `archive-worker`
- `telegram-bot`
- `bale-bot`
- `integrated-ops`

Every deployment emits `started` and exactly one terminal event:
`succeeded`, `failed`, or `rolled_back`.

Telegram and Bale reports follow the same UX language as Dent1402Bot: concise Persian
copy, semantic status indicators, a readable service label, Tehran time,
bounded context, and a trace ID. Dynamic values are HTML-escaped for Telegram.
At the Bale boundary the same shared rich text is converted to Bale-native
Markdown and `parse_mode` is omitted; raw Telegram HTML must never reach Bale.

## Reliability model

`publish` writes the event to a private local spool before network delivery.
Telegram, Bale, and the owner-only website notification delivery states are
tracked independently. A systemd timer
retries pending channels with bounded exponential backoff. The spool contains
no bot token, chat ID, cookie, password, or signed URL.

The website channel calls the existing signed HMAC service endpoint as the
already-linked owner. It stores one canonical owner-only record per event ID.
Those records set `disablePush=true`, because the notifier itself already sends
the same event to Telegram and Bale; notification-feed workers must not deliver
them a second time.

Channels known to be unreachable must not remain in the active channel list.
Preserve an undelivered record without retry churn by moving it to the private
deferred spool:

```bash
python3 -m deploy_notifier.cli defer \
  --event-id <event-id> \
  --reason platform-network-unreachable
```

Deferral is an operational state, not successful delivery.

Running the notifier on the target VPS cannot report a total outage of that
same VPS. A later external monitor or laptop fallback is required for true
out-of-band outage notification.

## CLI contract

```bash
python3 -m deploy_notifier.cli publish \
  --service website \
  --status started \
  --environment production \
  --version <release-id> \
  --actor deployment-script \
  --event-id <stable-id>-started

python3 -m deploy_notifier.cli publish \
  --service website \
  --status succeeded \
  --environment production \
  --version <release-id> \
  --summary "health checks passed" \
  --actor deployment-script \
  --event-id <stable-id>-succeeded
```

The normal command exits successfully after durable queueing, even if a remote
channel is temporarily unavailable. Use `--require-delivery` only for an
explicit synchronous check; it must not cause a successful application deploy
to be repeated.

`event-id` must be stable for retries. Reusing it is idempotent and does not
create a second spool event.

## Integration ownership

- This repository owns notifier implementation, secrets, server units, queue,
  transport health, and retry behavior.
- The website canonical deploy script owns emitting website lifecycle events.
- `archive-worker` owns emitting its own deployment/worker-release events.
- Other projects must not implement direct Telegram/Bale transport or keep a
  duplicate copy of bot credentials.

Until the code is installed on the VPS, other projects should only document the
contract and must not claim notifications are active.

## Installation and remote emission

After SSH key access is confirmed, deploy the notifier from Windows:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-notifier-to-vps.ps1 `
  -ConfirmTargetHost <iran-vps-ip> -Bootstrap
```

`-Bootstrap` is only for a replacement host where no notifier exists yet. It
installs first, then emits the bootstrap `started`/`succeeded` validation pair.
Normal upgrades omit the switch and emit `started` before changing the active
notifier release.

On first installation the root-only notifier environment is derived on-host
from the already active Telegram and Bale environments. It adds the direct
signed website route and the Telegram-only loopback proxy. The installer never
restarts or disables either bot. Verify readiness with:

```bash
cd /opt/integrated-dent/notifier/current
set -a; source /etc/integrated-dent/deploy-notifier.env; set +a
python3 -m deploy_notifier.cli health --require-ready
```

Deployment scripts can queue an event without receiving any bot secret:

```powershell
.\scripts\emit-deploy-status.ps1 -Service website -Status started `
  -EventId <stable-release-id>-started -Version <release-id>
```

Use a separate status-specific ID for the terminal event, such as
`<stable-release-id>-succeeded`. Never reuse the `-started` ID for a terminal
status because durable idempotency will preserve the first event by design.

The emitter encodes the bounded JSON object as strict UTF-8 and Base64, writes
an ASCII temporary file, and transfers it byte-for-byte with SCP into the
notifier's private state directory. The VPS restores the original bytes before
`publish-json` and removes the incoming file with a shell trap. Do not replace
this with a raw `$payload | ssh` pipeline: Windows code-page conversion can
irreversibly turn Persian text into question marks, and Windows OpenSSH stdin
is not reliable in every non-interactive host. No token or chat ID is sent by
the calling project.

## Secrets

Telegram and Bale tokens, owner chat IDs, and the website signing secret are read from
`/etc/integrated-dent/deploy-notifier.env`, mode `0600`, owned by root. The
service reads it through systemd. Values must never appear in Git, deployment
arguments, process listings, event summaries, logs, or health output.

Only Telegram may use `http://127.0.0.1:11080`. The notifier rejects a proxy
URL that is not an unauthenticated loopback HTTP endpoint. Bale, website,
backups, and SSH keep the normal Iran route.

Any token pasted into a chat or log must be rotated before production use.
