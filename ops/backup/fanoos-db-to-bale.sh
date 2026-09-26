#!/usr/bin/env bash
# Daily database-only backup, sent to the owner on Bale.
#
# The full backup (scripts/ops/backup.php) keeps the database *and* object
# storage beside the server it protects, so it does not survive the server.
# This one leaves the machine: a gzipped mysqldump of the FANOOS database,
# delivered to the owner's Bale chat by the FANOOS Bale bot every day. It is
# the database only -- uploaded objects and question images are in the full
# backup, not here -- because that is what fits in a chat and what cannot be
# rebuilt from anything else.
#
# Bale caps what a bot can send, so the dump goes in parts of at most
# PART_BYTES; `cat fanoos-db-*.part-* > dump.sql.gz` puts it back together,
# and the first message carries the SHA-256 to check the result against.
# The last KEEP_DAYS dumps are also kept locally.
#
# Environment (EnvironmentFile, root:fanoosupd 0640 -- it holds the bot token):
#   FANOOS_BACKUP_BALE_TOKEN     the FANOOS Bale bot's token
#   FANOOS_BACKUP_BALE_CHAT_ID   the owner's Bale user id (they must have
#                                started the bot once, or Bale refuses)
#   FANOOS_BACKUP_DB_NAME        default fanoos_prod
#   FANOOS_BACKUP_MYSQL_CNF      default /etc/fanoos/mysql-migrator.cnf
#   FANOOS_BACKUP_DIR            default /var/backups/fanoos/db-daily
set -euo pipefail

: "${FANOOS_BACKUP_BALE_TOKEN:?FANOOS_BACKUP_BALE_TOKEN is required}"
: "${FANOOS_BACKUP_BALE_CHAT_ID:?FANOOS_BACKUP_BALE_CHAT_ID is required}"
DB="${FANOOS_BACKUP_DB_NAME:-fanoos_prod}"
CNF="${FANOOS_BACKUP_MYSQL_CNF:-/etc/fanoos/mysql-migrator.cnf}"
DIR="${FANOOS_BACKUP_DIR:-/var/backups/fanoos/db-daily}"
KEEP_DAYS="${FANOOS_BACKUP_KEEP_DAYS:-7}"
PART_BYTES="${FANOOS_BACKUP_PART_BYTES:-19000000}"
API="https://tapi.bale.ai/bot${FANOOS_BACKUP_BALE_TOKEN}"

umask 077
mkdir -p "$DIR"
stamp="$(TZ=Asia/Tehran date +%Y%m%d-%H%M)"
dump="$DIR/fanoos-db-$stamp.sql.gz"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

send_text() {
    curl -sS --max-time 30 -o /dev/null -w '%{http_code}' \
        --data-urlencode "chat_id=$FANOOS_BACKUP_BALE_CHAT_ID" \
        --data-urlencode "text=$1" \
        "$API/sendMessage" || true
}

fail() {
    # The owner hears about a missed backup the same way they get a good one.
    send_text "⚠️ بکاپ روزانه‌ی دیتابیس فانوس انجام نشد: $1" >/dev/null
    echo "backup failed: $1" >&2
    exit 1
}

# --single-transaction: a consistent snapshot without locking the site.
if ! mysqldump --defaults-extra-file="$CNF" --single-transaction --quick --routines --triggers \
        --no-tablespaces --set-gtid-purged=OFF "$DB" 2>"$work/dump.err" | gzip -6 >"$dump.partial"; then
    fail "mysqldump: $(head -c 300 "$work/dump.err")"
fi
# A dump that ends without the footer was cut short.
if ! zcat "$dump.partial" | tail -n 1 | grep -q "Dump completed"; then
    fail "the dump is incomplete"
fi
mv "$dump.partial" "$dump"

sum="$(sha256sum "$dump" | cut -d' ' -f1)"
size="$(stat -c %s "$dump")"
split -b "$PART_BYTES" -d -a 2 "$dump" "$work/fanoos-db-$stamp.sql.gz.part-"
parts=("$work"/fanoos-db-"$stamp".sql.gz.part-*)
count="${#parts[@]}"

code="$(send_text "🗄 بکاپ روزانه‌ی دیتابیس فانوس — $stamp
حجم: $((size / 1024 / 1024)) مگابایت در $count بخش
SHA-256: $sum
برای بازسازی: همه‌ی بخش‌ها را به ترتیب پشت هم بچسبان (cat) تا فایل .sql.gz به دست بیاید.")"
[ "$code" = "200" ] || fail "Bale refused the message (HTTP $code) -- has the owner started the bot?"

index=0
for part in "${parts[@]}"; do
    index=$((index + 1))
    code="$(curl -sS --max-time 300 -o "$work/send.out" -w '%{http_code}' \
        -F "chat_id=$FANOOS_BACKUP_BALE_CHAT_ID" \
        -F "caption=بخش $index از $count" \
        -F "document=@$part" \
        "$API/sendDocument" || true)"
    [ "$code" = "200" ] || fail "sending part $index of $count failed (HTTP $code)"
done

find "$DIR" -maxdepth 1 -name 'fanoos-db-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
echo "sent $dump ($size bytes, $count parts, sha256 $sum)"
