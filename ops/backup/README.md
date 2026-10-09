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
