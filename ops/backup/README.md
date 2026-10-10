# Daily database backup to the owner on Bale

`fanoos-db-to-bale.sh` runs daily at 04:00 Tehran time from the
`fanoos-db-to-bale.timer`. It works like this:

- It dumps the FANOOS database (`mysqldump --single-transaction`, gzip) and
  refuses a dump that lacks mysqldump's completion footer.
- The FANOOS Bale bot sends the dump to the owner in parts of up to 19 MB,
  after a message that gives the size, the part count and the SHA-256.
- A failed run sends a warning message instead of the file.
- The dump and send parts live in a private `/var/tmp` work directory. A
  successful delivery, failure, or normal process exit removes that directory.

This covers the **database only**. Non-reference uploaded objects and question
images are in the full backup (`scripts/ops/backup.php`), which stays on the
server. Reference-only PDF objects are deliberately excluded from full
backups and remain in live private storage.

## Local backup retention

FANOOS keeps at most five completed, verified server-local backup sets across
`/var/backups/fanoos/` and `/var/backups/fanoos/research/`. A verified full
backup triggers retention immediately; the hourly
`fanoos-backup-retention.timer` enforces the same cap for research recovery
archives. A research `.tar.gz` counts only with a valid adjacent `.sha256`
receipt. Incomplete archives and publication receipts are left in place and do
not count as recovery sets. The temporary database transfer remains outside
this inventory because it is removed when the send job exits.

Install the timer after deploying the reviewed units through the updater:

```sh
sudo install -o root -g root -m 0644 /srv/fanoos/updater-checkout/ops/backup/fanoos-backup-retention.service.example /etc/systemd/system/fanoos-backup-retention.service
sudo install -o root -g root -m 0644 /srv/fanoos/updater-checkout/ops/backup/fanoos-backup-retention.timer.example /etc/systemd/system/fanoos-backup-retention.timer
sudo systemd-analyze verify /etc/systemd/system/fanoos-backup-retention.service /etc/systemd/system/fanoos-backup-retention.timer
sudo systemctl daemon-reload
sudo systemctl enable --now fanoos-backup-retention.timer
```

## Restoring

```
cat fanoos-db-*.sql.gz.part-* > fanoos-db.sql.gz
sha256sum fanoos-db.sql.gz          # compare with the first message
gunzip -c fanoos-db.sql.gz | mysql <database>
```

## Installing

The file `/etc/fanoos/backup-bale.env` holds the bot token. It must be
`root:fanoosupd`, mode `0640`, and contain:

```
FANOOS_BACKUP_BALE_TOKEN=<the FANOOS Bale bot token>
FANOOS_BACKUP_BALE_CHAT_ID=<the owner's Bale user id>
FANOOS_BACKUP_DB_NAME=fanoos_prod
```

The owner must have started the FANOOS Bale bot once, or Bale refuses to
deliver. Copy the two `.example` units to `/etc/systemd/system/` without the
suffix, then run:

```
systemctl daemon-reload
systemctl enable --now fanoos-db-to-bale.timer
```
