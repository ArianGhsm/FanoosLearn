# Telegram Egress on the Iran VPS

Telegram and Bale run on the same Iran VPS, but only Telegram uses the local
Xray egress. The server default route is unchanged.

## Security boundary

- Xray listens only on `127.0.0.1:11080` as an HTTP CONNECT proxy.
- `integrated-dent-bot.service` receives
  `DENT_BOT_HTTPS_PROXY=http://127.0.0.1:11080`.
- Bale, signed website requests, SSH and backups do not use the proxy.
- UFW must never expose port `11080` or the probe port `11081`.
- Subscription URLs, node endpoints and credentials are secrets. The laptop
  stores each URL only as a separate ignored DPAPI ciphertext; the server stores
  Base64 representations only in root-readable `/etc/integrated-dent/telegram-egress.env`.
  The environment and last working generated Xray configuration are included
  only inside encrypted laptop snapshots.
- `/etc/integrated-dent` is a shared root-owned secret container and stays mode
  `0711`: service users may traverse to their explicitly group-readable files
  but cannot list the directory. Individual environment files remain `0600` or
  `0640` with their dedicated service group. No installer may reset this shared
  parent to `0700` or to a single service group, because that prevents Xray and
  the other isolated runtimes from reading their own files.

## Installation and selection

```powershell
.\scripts\install-telegram-egress-iran.ps1 -ConfirmTargetHost <iran-ip>
```

The installer discovers every ignored `telegram-egress-subscription-url*.dpapi`
file, verifies the pinned Xray release SHA-256, combines and deduplicates the
supported nodes from both configured subscriptions, tests them from the Iran
host against the real Telegram HTTPS API, and
requires two extra consecutive probes before accepting the fastest candidate.
It does not print node addresses or credentials. Failed selection leaves the
previous working configuration intact.

The refresh timer runs every ten minutes in Tehran with up to 30 seconds of
randomized delay. A non-blocking process lock prevents overlapping selectors.
Each run downloads both subscriptions again, tolerates one temporarily failed
source, deduplicates the combined nodes, validates every supported entry, probes
all valid candidates against the real Telegram API, and confirms the fastest
reachable candidate twice before accepting it. If the subscription fetch or
all probes fail, the last working configuration stays in place. Xray restarts
only when the selected configuration actually changes. After activation, the
main loopback port is probed twice again; a failure atomically restores and
restarts the previous configuration. A transient profile-metadata request also
cannot terminate the bot polling process during the short switch. Xray and the
selector are reinstallable; the subscription environment is authoritative.

## Telegram deployment and smoke test

```powershell
.\scripts\deploy-telegram-to-iran.ps1 -ConfirmTargetHost <iran-ip>
.\scripts\run-telegram-iran-smoke.ps1
```

Only one Telegram long-polling service may use the bot token. Before enabling a
replacement, disable every previous poller. Verify `getWebhookInfo.url` is empty,
then verify bot identity, commands, signed website health, state SQLite, one real
send/edit, zero restarts and the loopback-only listener.

If egress fails, Telegram may stop while Bale, the website and administration
remain healthy. A complete international-network shutdown also prevents the
subscription tunnel from working; this design isolates ordinary filtering, not
a total physical disconnection from international networks.
