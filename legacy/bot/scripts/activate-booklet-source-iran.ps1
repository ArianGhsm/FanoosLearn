[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ("dent-booklet-source-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$localScript = Join-Path $temporaryRoot "activate.sh"
$remoteScript = "/tmp/dent-booklet-source-$([Guid]::NewGuid().ToString('N')).sh"

$caption = @'
📓 جزوه جلسه چهارم ورودی ۱۳۹۹ - تومور های سینوس

📚 گوش و حلق و بینی
👨‍🏫 استاد ایرانی

#گوش_حلق_بینی #ایرانی #ترم۷ #ورودی_۱۳۹۹

▫️ @Dent1402Booklets
'@
$captionBase64 = [Convert]::ToBase64String([Text.UTF8Encoding]::new($false).GetBytes($caption.Trim()))

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath ([IO.Path]::GetFullPath((Join-Path $root ([string]$server.knownHostsFile)))) -Destination $runtimeKnownHosts
    $sshOptions = @(
        "-i", $identityFile, "-p", [string]$server.port,
        "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1",
        "-o", "UserKnownHostsFile=$runtimeKnownHosts"
    )
    $scpOptions = @(
        "-q", "-i", $identityFile, "-P", [string]$server.port,
        "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1",
        "-o", "UserKnownHostsFile=$runtimeKnownHosts"
    )
    $script = @'
#!/usr/bin/env bash
set -euo pipefail
current="$(readlink -f /opt/integrated-dent/telegram/current)"
main_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
test "$main_pid" -gt 0
test "$(readlink -f "/proc/$main_pid/cwd")" = "$current"
set -a
source /etc/integrated-dent/dent-bot.env
set +a
runuser -u dentbot -- bash -c '
  set -euo pipefail
  cd /opt/integrated-dent/telegram/current
  /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.health
  /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.booklet_source_admin probe
  route_count="$(sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 \
    "SELECT COUNT(*) FROM protected_media_sources WHERE source_chat_id=$DENT_BOT_BOOKLET_SOURCE_CHANNEL_ID AND source_message_id=4 AND course_code=char(69,78,84) AND term=7 AND session_no=4 AND content_kind=char(98,111,111,107,108,101,116) AND active=1;")"
  if test "$route_count" = 0; then
    /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.booklet_source_admin register-existing \
      --message-id 4 \
      --caption-base64 "__CAPTION_BASE64__" \
      --file-name "tumors ent.pdf" \
      --mime-type application/pdf
  fi
  /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.booklet_source_admin hydrate-existing --message-id 4
  /opt/integrated-dent/telegram-venv/bin/python - <<'PY'
import json
from dent_bot.config import load_settings
from dent_bot.site_api import SiteApiClient, SiteApiError

settings = load_settings()
client = SiteApiClient(
    settings.site_api_url,
    settings.site_service_secret,
    platform="telegram",
    timeout=settings.site_timeout_seconds,
    relay_secret=settings.site_relay_secret,
)
try:
    result = client.booklet_watermark_identity(settings.owner_id)
except SiteApiError as error:
    print(json.dumps({"identityReady": False, "status": error.status, "code": error.code}, separators=(",", ":")))
    raise
identity = dict(result.get("identity") or {})
print(json.dumps({
    "identityReady": all(bool(identity.get(field)) for field in ("fullName", "nationalCode", "phoneNumber")),
    "fullNamePresent": bool(identity.get("fullName")),
    "nationalCodePresent": bool(identity.get("nationalCode")),
    "phonePresent": bool(identity.get("phoneNumber")),
}, separators=(",", ":")))
PY
  issuance_before="$(sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 "SELECT COUNT(*) FROM booklet_issuances WHERE user_id=$DENT_BOT_OWNER_TELEGRAM_ID AND watermark_version=char(114,101,99,105,112,105,101,110,116,45,112,100,102,45,118,57) AND status=char(115,101,110,116) AND length(telegram_file_id)>0;")"
  /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.booklet_source_admin send-owner-test
  issuance_after_first="$(sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 "SELECT COUNT(*) FROM booklet_issuances WHERE user_id=$DENT_BOT_OWNER_TELEGRAM_ID AND watermark_version=char(114,101,99,105,112,105,101,110,116,45,112,100,102,45,118,57) AND status=char(115,101,110,116) AND length(telegram_file_id)>0;")"
  test "$issuance_after_first" -ge 1
  test "$issuance_after_first" -le "$((issuance_before + 1))"
  sleep 4
  /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.booklet_source_admin send-owner-test
  issuance_after_second="$(sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 "SELECT COUNT(*) FROM booklet_issuances WHERE user_id=$DENT_BOT_OWNER_TELEGRAM_ID AND watermark_version=char(114,101,99,105,112,105,101,110,116,45,112,100,102,45,118,57) AND status=char(115,101,110,116) AND length(telegram_file_id)>0;")"
  test "$issuance_after_second" = "$issuance_after_first"
  test -z "$(find /var/lib/integrated-dent/dent-bot/tmp/booklets -mindepth 1 -maxdepth 1 -print -quit)"
  echo BOOKLET_CACHE_REUSE_OK
'
echo BOOKLET_SOURCE_ACTIVATION_OK
echo ACTIVE_RELEASE="$(basename "$current")"
'@
    $script = $script.Replace("__CAPTION_BASE64__", $captionBase64)
    [IO.File]::WriteAllText($localScript, $script, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $localScript "${target}:$remoteScript"
    if ($LASTEXITCODE -ne 0) { throw "Booklet activation script transfer failed." }
    & ssh @sshOptions $target "sudo bash '$remoteScript'"
    if ($LASTEXITCODE -ne 0) { throw "Booklet source activation failed." }
}
finally {
    if ($sshOptions) {
        & ssh @sshOptions $target "sudo rm -f -- '$remoteScript'" 2>$null
    }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
