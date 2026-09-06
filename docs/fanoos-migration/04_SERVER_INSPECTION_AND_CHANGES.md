# Fanoos server inspection and change record

Inspection date: 2026-09-06

Scope: candidate website cPanel account only

Method: read-only HTTPS/cPanel API and network reachability checks

Secrets and account identifiers: deliberately omitted

## Executive result

The inspected account is a plausible shared-hosting target for the Fanoos PHP web/API and a local MySQL database. It is not an approved production target yet and is not a bot/worker server. Prompt 4 made no server-side changes.

The immediate infrastructure blocker is the server root filesystem reporting 89% use. Exact PHP runtime/limits and a restore path also remain unverified. Do not enable `.cpanel.yml` or create a production database until the provider and operator close the gates below.

## Observed inventory

| Area | Read-only observation | Consequence |
|---|---|---|
| Access | Website/cPanel HTTPS reachable with valid TLS; standard account SSH unavailable | Operate through reviewed cPanel features; do not assume shell access |
| Services | HTTP, PHP-FPM service, MySQL, cron and server-wide SSH reported up | Service-up status is not account-level capability proof |
| Database | Local MySQL `8.0.46-cll-lve` | Compatible in principle; Fanoos migrations still require account-level staging tests |
| PHP | Domain handler reported CGI; exact PHP version/directives API queries did not yield reliable values | PHP version, extensions and upload/runtime limits are blocking unknowns |
| Git | Git Version Control feature enabled; zero managed repositories observed | A new Fanoos-only cPanel repository would need explicit later setup |
| Cron | Cron feature enabled; reliable job inventory/execution test not completed | Scheduled backups/maintenance remain unproven |
| Storage quota | Account quota about 2 GB; current account usage under 1 MB at inspection | Capacity appears available but backup/object growth must be modeled |
| Server disks | Root filesystem 89%; another reported data filesystem 49%; temp 11% | Provider must remediate/confirm headroom before deploy |
| Backup | Account backup flag and provider backup feature enabled; generic restoration UI unavailable | Export and isolated restore must be demonstrated; status alone is insufficient |
| Web security | ModSecurity feature unavailable | Application/proxy protections and monitoring become more important |
| App runtimes | Passenger, Node and Python selector features unavailable | No long-running Fanoos bot/worker on this account |
| Account limits | Database count reported unlimited; CGI enabled; shell disabled | Logical capacity does not remove least-privilege/cPanel limitations |

Server load at the inspection moment was roughly 20.7 across 32 reported CPUs, memory about 28%, and swap about 7%. These are point-in-time values, not a capacity baseline.

## Read-only calls performed

The inspection queried sanitized account/server information, service status, MySQL server information, quota, enabled features, PHP handler, and managed Git repositories. It also tested HTTPS/cPanel and standard SSH reachability. Responses were summarized without persisting hostnames, usernames, home paths, ports, domain names, passwords, tokens, or raw API output in Git.

No legacy application server or directory was accessed for deployment. Earlier legacy behavior was considered only through the committed forensic inventory.

## Changes made on the server

None. Specifically, Prompt 4 did not:

- upload, edit, delete, or chmod a server file;
- create/change a Git repository, branch, hook, or `.cpanel.yml`;
- create/change a database, user, grant, table, or migration ledger;
- add/change a cron job, service, process, symlink, document root, DNS record, TLS setting, or PHP directive;
- deploy web code or start Telegram/Bale bots;
- create, rotate, reveal, or transmit a credential.

All new operational artifacts exist only in the local Fanoos repository. Examples contain placeholders only.

## Required changes before staging/production

| Priority | Owner | Evidence required |
|---|---|---|
| Blocker | Hosting provider | Root filesystem has safe sustained headroom and backup jobs will not exhaust it |
| Blocker | Operator/provider | Exact web and CLI PHP versions, `fileinfo`, `json`, `pdo_mysql`, `upload_max_filesize`, `post_max_size`, memory and timeout values |
| Blocker | Operator | Dedicated staging DB/user, non-public object/backup roots, least-privilege grants and filesystem modes |
| Blocker | Operator | Successful cPanel Git fast-forward deploy of an exact staging SHA, atomic symlinks and PHP CLI/web parity |
| Blocker | Operator | Successful verified backup plus isolated DB/object restore and tenant smoke tests |
| High | Operator/provider | HTTPS security headers, error log routing/rotation, cron job execution and overlap locking |
| High | Product/engineering | Separate supervised worker host for Telegram/Bale/outbox jobs |
| High | Security/engineering | Environment-specific random secrets and rotation/recovery process, never stored in Git |
| Medium | Product/engineering | Payment, email/SMS, object delivery and observability provider decisions |

## Activation record template

Any later manual cPanel action must record date/time, operator, environment, ticket, before/after values with secrets redacted, exact Git commit, CI result, backup ID/verification, commands or API action, health/smoke result, rollback target, and reviewer. The tracked counterpart must be updated in the same change (for example the cPanel template or this runbook); live-only undocumented configuration is not acceptable.
