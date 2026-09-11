# VPS Disaster Recovery

The ParsPack Iran VPS is replaceable. The laptop source tree and encrypted
Iran runtime snapshots are the recovery source; the VPS is never the only copy
of irrecoverable state.

## What Is Backed Up

- consistent SQLite snapshots for Telegram and Bale bot runtime state;
- the shared Telegram/Bale payment-offer SQLite store;
- durable deployment-notifier pending, delivered, and deferred history;
- the three runtime environment files, including the server-only booklet HMAC
  key inside the encrypted archive only;
- enabled/active service state and per-file SHA-256 checksums.
- the root-only Telegram subscription environment and last working generated
  Xray egress configuration, inside the encrypted archive only;
- Telegram booklet source metadata, issuance/trace records, delivery receipts
  and reusable personalized Telegram `file_id` values inside bot runtime SQLite.

The backup intentionally excludes Python, OS packages, deployed releases,
`__pycache__`, logs, locks, temporary files, and other reinstallable output.
Application source and infrastructure definitions already live in this folder.

Source PDFs remain in the private Telegram management channel and personalized
PDF bytes are transient. The independent laptop snapshot therefore restores the
catalog, attribution records, key and cached Telegram IDs, while Telegram keeps
the source and issued file objects. A same-disk VPS archive or provider snapshot
does not satisfy the independent laptop-backup requirement.

All current Iran snapshots are stored in ignored `backups/vps-state-iran/`.
Each archive is encrypted with Windows DPAPI for the current Windows account;
plaintext staging is removed.
Keep the Windows account/profile recoverable because DPAPI data cannot be
decrypted after losing that profile.

## Normal Backup

```powershell
.\scripts\backup-vps-state.ps1
```

Every backup is checksum-verified, extracted in a temporary directory, and its
SQLite databases receive `PRAGMA integrity_check`; the shared commerce snapshot
must also contain `payment_offers`, `term_access_policies`,
`term_access_entitlements`, `term_subscription_checkouts`,
`term_access_audit` and `term_subscription_renewal_notices`. Retention defaults to 14
recent daily snapshots plus one snapshot from each of 8 recent weeks.
The verifier accepts a complete pre-feature legacy snapshot, but if any
term-access table exists it requires the whole schema and the Term 7 policy;
partial migrations are rejected as recovery sources.

Install the daily and login-triggered task after the project path is final:

```powershell
.\scripts\install-vps-backup-schedule.ps1
```

The scheduled runner processes only Iran, the sole configured role. A
required-role failure returns a failed task result without deleting prior Iran
snapshots. A fresh backup is mandatory before stateful deployment or migration.

## Local Restore Test

```powershell
.\scripts\restore-vps-state.ps1 -VerifyOnly
```

To inspect an extracted copy without touching a server:

```powershell
.\scripts\restore-vps-state.ps1 -Destination .\.codex-local\restore-inspection
```

The destination must be empty and must be removed after inspection because it
contains sensitive runtime configuration in plaintext.

## Replace A Lost VPS

1. Create an Ubuntu 24.04 VPS and update ignored
   `.codex-local/iran-server.json`
   with its host, port, expected host key, user, and laptop SSH-key path.
2. Pin and verify the new SSH host key. Never disable host-key checking.
3. Run `ops/provision-ubuntu.sh` on the fresh host. Packages are installed from
   Ubuntu repositories; no package archive is kept in backups.
4. Restore the latest Iran snapshot, reinstall the Telegram-only Xray egress
   with `scripts/install-telegram-egress-iran.ps1`, deploy the current shared
   bot code, and restore Telegram/Bale runtime plus booklet issuance/trace state
   with restrictive ownership. Do not recreate the retired website Reader.
   The restore recreates the `dentcommerce` supplementary group and restores
   the shared offer database mode `0660` inside a setgid `2770` directory, so
   neither bot user gains access to the other platform's runtime database.
   The subscription environment is inside the encrypted runtime snapshot; Xray
   binaries and generated node configuration are reinstallable artifacts.

```powershell
.\scripts\restore-vps-state-to-server.ps1 -ConfirmTargetHost <new-server-ip>
```

5. Run real Telegram health and one UTF-8 Persian notification smoke test.
6. Verify network reachability to the website and Bale independently. Do not
   enable features merely because they were enabled on the old host.
7. Update `ops/SERVER_STATE.md` only after direct verification.

The local code history, project logic, runbooks, systemd units, and installer
are part of this source tree and are not duplicated in each runtime snapshot.
