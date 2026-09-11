[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = '.codex-local/iran-server.json'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw 'ConfirmTargetHost does not match the Iran server.' }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ('dent-booklet-check-' + [Guid]::NewGuid().ToString('N'))
$knownHosts = Join-Path $temporaryRoot 'known_hosts'
$localCheck = Join-Path $temporaryRoot 'check.sh'
$remoteCheck = "/tmp/dent-booklet-check-$([Guid]::NewGuid().ToString('N')).sh"

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath ([IO.Path]::GetFullPath((Join-Path $root ([string]$server.knownHostsFile)))) -Destination $knownHosts
    $sshOptions = @(
        '-i', $identityFile, '-p', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$knownHosts", '-o', 'ConnectTimeout=10'
    )
    $scpOptions = @(
        '-q', '-i', $identityFile, '-P', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$knownHosts", '-o', 'ConnectTimeout=10'
    )
    $check = @'
#!/usr/bin/env bash
set -euo pipefail
service=integrated-dent-bot.service
systemctl is-active --quiet "$service"
test "$(systemctl show "$service" -p NRestarts --value)" = 0
pid="$(systemctl show "$service" -p MainPID --value)"
active="$(readlink -f /opt/integrated-dent/telegram/current)"
test "$pid" -gt 0
test "$(readlink -f "/proc/$pid/cwd")" = "$active"
set -a
source /etc/integrated-dent/dent-bot.env
set +a
test "$DENT_BOT_BOOKLET_MEDIA_WORKERS" = 1
test "$DENT_BOT_BOOKLET_MEDIA_QUEUE_SIZE" = 48
test "$DENT_BOT_BOOKLET_PDF_NORMALIZER" = none
test "$DENT_BOT_BOOKLET_RASTER_DPI" = 180
test "$DENT_BOT_BOOKLET_RASTER_JPEG_QUALITY" = 88
test "$DENT_BOT_BOOKLET_MAX_OUTPUT_BYTES" = 51380224
test "$DENT_BOT_BOOKLET_PROCESSING_TIMEOUT_SECONDS" = 300
test "$DENT_BOT_BOOKLET_ORPHAN_MAX_AGE_SECONDS" = 3600
test "$DENT_BOT_BOOKLET_RATE_WINDOW_SECONDS" = 60
test "$DENT_BOT_BOOKLET_RATE_MAX_REQUESTS" = 12
test "$DENT_BOT_BOOKLET_SAME_DOCUMENT_COOLDOWN_SECONDS" = 3
test "$DENT_BOT_BOOKLET_WATERMARK_FONT" = "$active/dent_bot/assets/fonts/B_Nazanin_Bold.ttf"
test -r "$DENT_BOT_BOOKLET_WATERMARK_FONT"
command -v qpdf >/dev/null
command -v pdftotext >/dev/null
cd "$active"
runuser -u dentbot -- /opt/integrated-dent/telegram-venv/bin/python - <<'PY'
import importlib.util
from dent_bot.pdf_fingerprint import SECURE_RASTER_CHANNEL_VERSION, WATERMARK_VERSION, WatermarkIdentity
assert WATERMARK_VERSION == "recipient-pdf-v9"
assert SECURE_RASTER_CHANNEL_VERSION == "raster-constellation-repetition3-v1"
assert importlib.util.find_spec("pymupdf") is not None
assert importlib.util.find_spec("arabic_reshaper") is not None
assert importlib.util.find_spec("bidi") is not None
PY
sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 'PRAGMA integrity_check;' | grep -qx ok
sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 \
  "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='booklet_request_events';" | grep -qx 1
v9_count="$(sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 \
  "SELECT COUNT(*) FROM booklet_issuances WHERE watermark_version='recipient-pdf-v9' AND status='sent' AND length(telegram_file_id)>0;")"
test "$v9_count" -ge 1
runuser -u dentbot -- /opt/integrated-dent/telegram-venv/bin/python - <<'PY'
import sqlite3
import tempfile
from pathlib import Path

import pymupdf

from dent_bot.api import TelegramBotApi
from dent_bot.config import load_settings
from dent_bot.pdf_fingerprint import WATERMARK_VERSION, WatermarkIdentity
from dent_bot.site_api import SiteApiClient

settings = load_settings()
connection = sqlite3.connect(settings.state_db)
try:
    row = connection.execute(
        "SELECT telegram_file_id FROM booklet_issuances "
        "WHERE user_id=? AND watermark_version=? AND status='sent' "
        "AND length(telegram_file_id)>0 ORDER BY issued_at DESC LIMIT 1",
        (settings.owner_id, WATERMARK_VERSION),
    ).fetchone()
finally:
    connection.close()
assert row and row[0]
api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
site = SiteApiClient(
    settings.site_api_url,
    settings.site_service_secret,
    platform="telegram",
    timeout=settings.site_timeout_seconds,
    relay_secret=settings.site_relay_secret,
)
try:
    identity = dict(site.booklet_watermark_identity(settings.owner_id).get("identity") or {})
    normalized_identity = WatermarkIdentity(
        str(identity.get("fullName") or ""),
        str(identity.get("nationalCode") or ""),
        str(identity.get("phoneNumber") or ""),
    ).validated()
    national_code = normalized_identity.national_code
    phone_number = normalized_identity.phone_number
    assert national_code and phone_number
    with tempfile.TemporaryDirectory(prefix="font-check-", dir=str(settings.booklet_temp_root)) as directory:
        pdf_path = Path(directory) / "telegram-cache.pdf"
        api.download_file(str(row[0]), pdf_path, max_bytes=settings.booklet_max_download_bytes)
        with pymupdf.open(pdf_path) as document:
            assert document.page_count > 0
            assert all(len(page.get_contents()) == 1 for page in document)
            assert all(len(page.get_images(full=True)) == 1 for page in document)
            assert all(not page.get_text("text").strip() for page in document)
            assert sum(1 for page in document for _annotation in (page.annots() or ())) == 0
            assert document.embfile_count() == 0
            metadata = " ".join(str(value or "") for value in document.metadata.values())
            assert national_code not in metadata
            assert phone_number not in metadata
            assert str(document.metadata.get("keywords") or "").startswith("DFP-")
        raw = pdf_path.read_bytes()
        assert national_code.encode("ascii") not in raw
        assert phone_number.encode("ascii") not in raw
        assert b"TRC-" not in raw
        extracted = Path(directory) / "pdftotext.txt"
        import subprocess
        subprocess.run(["pdftotext", "-enc", "UTF-8", str(pdf_path), str(extracted)], check=True)
        assert not extracted.read_text(encoding="utf-8", errors="ignore").strip()
        subprocess.run(["qpdf", "--check", str(pdf_path)], check=True, stdout=subprocess.DEVNULL)
finally:
    api.close()
print("BOOKLET_LIVE_SECURE_RASTER=true")
print("BOOKLET_LIVE_PDFTEXT_EMPTY=true")
print("BOOKLET_LIVE_VISIBLE_PII_BURNED_IN=true")
PY
test -z "$(find /var/lib/integrated-dent/dent-bot/tmp/booklets -mindepth 1 -maxdepth 1 -name 'job-*' -print -quit)"
recent="$(journalctl -u "$service" --since '30 minutes ago' --no-pager)"
! grep -Eq 'TRC-[A-Z2-7]{5}-[A-Z2-7]{5}|(^|[^0-9])[0-9]{10,12}([^0-9]|$)' <<<"$recent"
rss_kib="$(ps -o rss= -p "$pid" | tr -d ' ')"
printf 'BOOKLET_RELEASE=%s\n' "$(basename "$active")"
printf 'BOOKLET_WORKERS=%s\n' "$DENT_BOT_BOOKLET_MEDIA_WORKERS"
printf 'BOOKLET_QUEUE=%s\n' "$DENT_BOT_BOOKLET_MEDIA_QUEUE_SIZE"
printf 'BOOKLET_PROCESS_RSS_KIB=%s\n' "$rss_kib"
printf 'BOOKLET_V9_ISSUANCES_PRESENT=true\n'
printf 'BOOKLET_HARDENING_CHECK_OK\n'
'@
    [IO.File]::WriteAllText($localCheck, $check, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $localCheck "${target}:$remoteCheck"
    if ($LASTEXITCODE -ne 0) { throw 'Could not transfer booklet hardening check.' }
    & ssh @sshOptions $target "sudo bash '$remoteCheck'"
    if ($LASTEXITCODE -ne 0) { throw 'Booklet hardening production check failed.' }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteCheck'" 2>$null | Out-Null }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
