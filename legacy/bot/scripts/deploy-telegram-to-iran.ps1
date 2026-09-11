[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [string]$Snapshot = ""
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$serverConfigPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $root $ServerConfig }
$serverConfigPath = (Resolve-Path -LiteralPath $serverConfigPath).Path
$serverStateRoot = if ((Split-Path $serverConfigPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverConfigPath -Parent) -Leaf) -eq '.codex-local') { Split-Path (Split-Path $serverConfigPath -Parent) -Parent } else { $root }
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath $serverConfigPath | ConvertFrom-Json
$knownHostsPath = [string]$server.knownHostsFile
if (-not [IO.Path]::IsPathRooted($knownHostsPath)) { $knownHostsPath = Join-Path $serverStateRoot $knownHostsPath }
$knownHostsPath = [IO.Path]::GetFullPath($knownHostsPath)
if (-not (Test-Path -LiteralPath $knownHostsPath -PathType Leaf)) { throw "The pinned Iran known-hosts file is missing." }
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$releaseId = Get-Date -Format "yyyyMMdd-HHmmss"
$lifecycleBaseId = "telegram-bot-$releaseId"
$lifecycleStarted = $false
. (Join-Path $PSScriptRoot "deploy-lifecycle.ps1")
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-telegram-iran-" + [Guid]::NewGuid().ToString("N"))
$restoreRoot = Join-Path $temporaryRoot "restore"
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$codeBundle = Join-Path $temporaryRoot "telegram-code.tar.gz"
$remotePrefix = "/tmp/integrated-dent-telegram-$([Guid]::NewGuid().ToString('N'))"

try {
    Publish-DentDeployLifecycle -Service telegram-bot -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Telegram bot deployment started." -ServerConfig $serverConfigPath
    $lifecycleStarted = $true
    New-Item -ItemType Directory -Force -Path $temporaryRoot, $restoreRoot | Out-Null
    Copy-Item -LiteralPath $knownHostsPath -Destination $runtimeKnownHosts -Force
    $restoreArgs = @{ Destination = $restoreRoot }
    if ($Snapshot) { $restoreArgs.Snapshot = $Snapshot }
    & (Join-Path $PSScriptRoot "restore-vps-state.ps1") @restoreArgs | Out-Null

    $botEnv = Join-Path $restoreRoot "config\dent-bot.env"
    $botState = Join-Path $restoreRoot "runtime\dent-bot\state.sqlite3"
    if (-not (Test-Path -LiteralPath $botEnv -PathType Leaf)) { throw "Verified backup has no Telegram configuration." }
    if (-not (Test-Path -LiteralPath $botState -PathType Leaf)) { throw "Verified backup has no Telegram state." }
    & tar -C $root -czf $codeBundle dent_bot requirements-bot-pdf.txt
    if ($LASTEXITCODE -ne 0) { throw "Telegram code bundle creation failed." }

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
    $transfers = @(
        @($codeBundle, "${remotePrefix}.code.tar.gz"),
        @($botEnv, "${remotePrefix}.env"),
        @($botState, "${remotePrefix}.sqlite3"),
        @((Join-Path $root "ops\systemd\integrated-dent-bot.service"), "${remotePrefix}.service")
    )
    foreach ($transfer in $transfers) {
        & scp @scpOptions $transfer[0] "${target}:$($transfer[1])"
        if ($LASTEXITCODE -ne 0) { throw "Telegram deployment transfer failed." }
    }

    $installerPath = Join-Path $temporaryRoot "install.sh"
    $installer = @'
#!/usr/bin/env bash
set -euo pipefail
umask 077
prefix='__REMOTE_PREFIX__'
release_root='/opt/integrated-dent/releases/telegram-__RELEASE_ID__'
current_link='/opt/integrated-dent/telegram/current'
venv_root='/opt/integrated-dent/telegram-venv'
previous_target="$(readlink -f "$current_link" 2>/dev/null || true)"
rollback_root="$(mktemp -d /tmp/integrated-dent-telegram-rollback.XXXXXX)"
had_env=0
had_unit=0
activated=0
rollback_activation() {
  if test "$had_env" = 1; then
    cp -a "$rollback_root/dent-bot.env" /etc/integrated-dent/dent-bot.env
  else
    rm -f -- /etc/integrated-dent/dent-bot.env
  fi
  if test "$had_unit" = 1; then
    cp -a "$rollback_root/integrated-dent-bot.service" /etc/systemd/system/integrated-dent-bot.service
  else
    rm -f -- /etc/systemd/system/integrated-dent-bot.service
  fi
  systemctl daemon-reload
  if test -n "$previous_target" && test -d "$previous_target"; then
    ln -sfn "$previous_target" "$current_link"
    systemctl restart integrated-dent-bot.service
  else
    systemctl stop integrated-dent-bot.service || true
  fi
}
cleanup() {
  rm -f -- "${prefix}.code.tar.gz" "${prefix}.env" "${prefix}.sqlite3" "${prefix}.service" "${prefix}.install.sh"
  rm -rf -- "$rollback_root"
}
trap 'status=$?; if test "$status" -ne 0 && test "$activated" = 1; then rollback_activation || true; fi; cleanup; exit "$status"' EXIT
test -s "${prefix}.code.tar.gz"
test -s "${prefix}.env"
test -s "${prefix}.sqlite3"
sqlite3 "${prefix}.sqlite3" 'PRAGMA integrity_check;' | grep -qx ok
systemctl is-active --quiet integrated-dent-telegram-egress.service
curl --silent --show-error --proxy http://127.0.0.1:11080 --max-time 12 \
  https://api.telegram.org/bot0:invalid/getMe | grep -q '"error_code":401'
if test -s /etc/integrated-dent/dent-bot.env; then
  cp -a /etc/integrated-dent/dent-bot.env "$rollback_root/dent-bot.env"
  had_env=1
fi
if test -s /etc/systemd/system/integrated-dent-bot.service; then
  cp -a /etc/systemd/system/integrated-dent-bot.service "$rollback_root/integrated-dent-bot.service"
  had_unit=1
fi

if ! id dentbot >/dev/null 2>&1; then
  useradd --system --home-dir /nonexistent --shell /usr/sbin/nologin dentbot
fi
if ! command -v qpdf >/dev/null 2>&1 || ! command -v pdftotext >/dev/null 2>&1 || \
   ! dpkg-query -W -f='${Status}' python3-venv 2>/dev/null | grep -q 'install ok installed'; then
  apt-get update
  apt-get install -y --no-install-recommends python3 python3-venv qpdf poppler-utils
fi
getent group dentcommerce >/dev/null 2>&1 || groupadd --system dentcommerce
usermod -a -G dentcommerce dentbot
test -s /var/lib/integrated-dent/shared/payment-offers.sqlite3
install -d -m 0755 /opt/integrated-dent/releases /opt/integrated-dent/telegram
install -d -m 0755 "$release_root"
tar -xzf "${prefix}.code.tar.gz" -C "$release_root"
test -r "$release_root/dent_bot/assets/fonts/B_Nazanin_Bold.ttf"
chown -R root:root "$release_root"
find "$release_root" -type d -exec chmod 0755 {} +
find "$release_root" -type f -exec chmod 0644 {} +
if ! test -x "$venv_root/bin/python"; then
  python3 -m venv "$venv_root"
fi
"$venv_root/bin/python" -m pip install --disable-pip-version-check --no-input --requirement "$release_root/requirements-bot-pdf.txt"
"$venv_root/bin/python" -m pip uninstall --yes pikepdf >/dev/null 2>&1 || true
chown -R root:root "$venv_root"
find "$venv_root" -type d -exec chmod 0755 {} +
find "$venv_root" -type f -exec chmod 0644 {} +
find "$venv_root/bin" -maxdepth 1 -type f -exec chmod 0755 {} +
"$venv_root/bin/python" -m compileall -q "$release_root/dent_bot"
"$venv_root/bin/python" -c 'import pymupdf'
qpdf --version >/dev/null
ln -sfn "$release_root" "$current_link"
activated=1

install -d -o dentbot -g dentbot -m 0700 /var/lib/integrated-dent/dent-bot /var/lib/integrated-dent/dent-bot/tmp /var/lib/integrated-dent/dent-bot/tmp/booklets
if ! test -s /var/lib/integrated-dent/dent-bot/state.sqlite3; then
  install -o dentbot -g dentbot -m 0600 "${prefix}.sqlite3" /var/lib/integrated-dent/dent-bot/state.sqlite3
fi
if ! test -s /etc/integrated-dent/dent-bot.env; then
  install -o root -g root -m 0600 "${prefix}.env" /etc/integrated-dent/dent-bot.env
fi
sed -i \
  -e '/^DENT_BOT_PAYMENT_OFFERS_DB=/d' \
  -e '/^DENT_BOT_HTTPS_PROXY=/d' \
  -e '/^DENT_BOT_SITE_URL=/d' \
  -e '/^DENT_BOT_SITE_API_URL=/d' \
  -e '/^DENT_BOT_UPDATE_WORKERS=/d' \
  -e '/^DENT_BOT_PAYMENT_RESULT_PUSH_ENABLED=/d' \
  -e '/^DENT_BOT_PAYMENT_RESULT_POLL_SECONDS=/d' \
  -e '/^DENT_BOT_PAYMENT_RESULT_BATCH_SIZE=/d' \
  -e '/^DENT_BOT_NAVID_DAILY_ENABLED=/d' \
  -e '/^DENT_BOT_REQUIRED_CHANNEL_USERNAME=/d' \
  -e '/^DENT_BOT_BOOKLET_SOURCE_CHANNEL_ID=/d' \
  -e '/^DENT_BOT_BOOKLET_SOURCE_CHANNEL_TITLE=/d' \
  -e '/^DENT_BOT_BOOKLET_MEDIA_WORKERS=/d' \
  -e '/^DENT_BOT_BOOKLET_MEDIA_QUEUE_SIZE=/d' \
  -e '/^DENT_BOT_BOOKLET_ACCESS_MODE=/d' \
  -e '/^DENT_BOT_BOOKLET_WATERMARK_FONT=/d' \
  -e '/^DENT_BOT_BOOKLET_TEMP_ROOT=/d' \
  -e '/^DENT_BOT_BOOKLET_QPDF_BINARY=/d' \
  -e '/^DENT_BOT_BOOKLET_MAX_DOWNLOAD_BYTES=/d' \
  -e '/^DENT_BOT_BOOKLET_PDF_NORMALIZER=/d' \
  -e '/^DENT_BOT_BOOKLET_RASTER_DPI=/d' \
  -e '/^DENT_BOT_BOOKLET_RASTER_JPEG_QUALITY=/d' \
  -e '/^DENT_BOT_BOOKLET_MAX_OUTPUT_BYTES=/d' \
  -e '/^DENT_BOT_BOOKLET_PROCESSING_TIMEOUT_SECONDS=/d' \
  -e '/^DENT_BOT_BOOKLET_ORPHAN_MAX_AGE_SECONDS=/d' \
  -e '/^DENT_BOT_BOOKLET_RATE_WINDOW_SECONDS=/d' \
  -e '/^DENT_BOT_BOOKLET_RATE_MAX_REQUESTS=/d' \
  -e '/^DENT_BOT_BOOKLET_SAME_DOCUMENT_COOLDOWN_SECONDS=/d' \
  /etc/integrated-dent/dent-bot.env
if ! grep -q '^DENT_BOT_BOOKLET_FINGERPRINT_KEY=' /etc/integrated-dent/dent-bot.env; then
  printf '\nDENT_BOT_BOOKLET_FINGERPRINT_KEY=%s\n' "$(openssl rand -hex 32)" >> /etc/integrated-dent/dent-bot.env
fi
booklet_source_title="$(printf '%s' '2KzYstmI2Ycg2K7YtdmI2LXbjCB8INiv2YbYr9in2YbigIzZvtiy2LTaqduMINiq2YfYsdin2YYg27HbtNuw27I=' | base64 -d)"
printf '\n%s\n' \
  'DENT_BOT_PAYMENT_OFFERS_DB=/var/lib/integrated-dent/shared/payment-offers.sqlite3' \
  'DENT_BOT_HTTPS_PROXY=http://127.0.0.1:11080' \
  'DENT_BOT_SITE_URL=https://dentistry1402tums.ir' \
  'DENT_BOT_SITE_API_URL=https://dentistry1402tums.ir/api/bot_api.php?action=service' \
  'DENT_BOT_UPDATE_WORKERS=2' \
  'DENT_BOT_PAYMENT_RESULT_PUSH_ENABLED=1' \
  'DENT_BOT_PAYMENT_RESULT_POLL_SECONDS=30' \
  'DENT_BOT_PAYMENT_RESULT_BATCH_SIZE=10' \
  'DENT_BOT_NAVID_DAILY_ENABLED=0' \
  'DENT_BOT_REQUIRED_CHANNEL_USERNAME=Dent1402Booklets' \
  'DENT_BOT_BOOKLET_SOURCE_CHANNEL_ID=-1003706539157' \
  "DENT_BOT_BOOKLET_SOURCE_CHANNEL_TITLE=\"$booklet_source_title\"" \
  'DENT_BOT_BOOKLET_MEDIA_WORKERS=1' \
  'DENT_BOT_BOOKLET_MEDIA_QUEUE_SIZE=48' \
  'DENT_BOT_BOOKLET_ACCESS_MODE=all-authenticated' \
  "DENT_BOT_BOOKLET_WATERMARK_FONT=$release_root/dent_bot/assets/fonts/B_Nazanin_Bold.ttf" \
  'DENT_BOT_BOOKLET_TEMP_ROOT=/var/lib/integrated-dent/dent-bot/tmp/booklets' \
  'DENT_BOT_BOOKLET_QPDF_BINARY=/usr/bin/qpdf' \
  'DENT_BOT_BOOKLET_MAX_DOWNLOAD_BYTES=20971520' \
  'DENT_BOT_BOOKLET_PDF_NORMALIZER=none' \
  'DENT_BOT_BOOKLET_RASTER_DPI=180' \
  'DENT_BOT_BOOKLET_RASTER_JPEG_QUALITY=88' \
  'DENT_BOT_BOOKLET_MAX_OUTPUT_BYTES=51380224' \
  'DENT_BOT_BOOKLET_PROCESSING_TIMEOUT_SECONDS=300' \
  'DENT_BOT_BOOKLET_ORPHAN_MAX_AGE_SECONDS=3600' \
  'DENT_BOT_BOOKLET_RATE_WINDOW_SECONDS=60' \
  'DENT_BOT_BOOKLET_RATE_MAX_REQUESTS=12' \
  'DENT_BOT_BOOKLET_SAME_DOCUMENT_COOLDOWN_SECONDS=3' \
  >> /etc/integrated-dent/dent-bot.env

sed \
  -e 's#After=network-online.target#After=network-online.target integrated-dent-telegram-egress.service#' \
  -e '/^Wants=network-online.target$/a Requires=integrated-dent-telegram-egress.service' \
  -e 's#WorkingDirectory=/opt/integrated-dent/current#WorkingDirectory=/opt/integrated-dent/telegram/current#' \
  -e 's#ExecStart=/usr/bin/python3 -m dent_bot.service#ExecStart=/opt/integrated-dent/telegram-venv/bin/python -m dent_bot.service#' \
  "${prefix}.service" > /etc/systemd/system/integrated-dent-bot.service
chmod 0644 /etc/systemd/system/integrated-dent-bot.service
systemctl daemon-reload
systemctl enable integrated-dent-bot.service
systemctl restart integrated-dent-bot.service
sleep 4
systemctl is-active --quiet integrated-dent-bot.service
systemctl reset-failed integrated-dent-bot.service
test "$(systemctl show integrated-dent-bot.service -p NRestarts --value)" = 0
main_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
test "$main_pid" -gt 0
test "$(readlink -f "/proc/$main_pid/cwd")" = "$release_root"
set -a
source /etc/integrated-dent/dent-bot.env
set +a
runuser -u dentbot -- bash -c 'cd /opt/integrated-dent/telegram/current && /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.health'
runuser -u dentbot -- bash -c 'cd /opt/integrated-dent/telegram/current && /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.site_health'
sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 'PRAGMA integrity_check;' | grep -qx ok
activated=0
echo TELEGRAM_IRAN_DEPLOY_OK
echo TELEGRAM_RELEASE=__RELEASE_ID__
'@
    $installer = $installer.Replace('__REMOTE_PREFIX__', $remotePrefix).Replace('__RELEASE_ID__', $releaseId)
    [IO.File]::WriteAllText($installerPath, $installer, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $installerPath "${target}:${remotePrefix}.install.sh"
    if ($LASTEXITCODE -ne 0) { throw "Telegram installer transfer failed." }
    & ssh @sshOptions $target "sudo bash '${remotePrefix}.install.sh'"
    if ($LASTEXITCODE -ne 0) {
        throw "Telegram deployment failed; rollback to the previous active release was attempted."
    }
    Publish-DentDeployLifecycle -Service telegram-bot -Status succeeded -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Telegram bot and its isolated egress passed health checks." -ServerConfig $serverConfigPath
    $lifecycleStarted = $false
}
catch {
    if ($lifecycleStarted) {
        Publish-DentDeployLifecycle -Service telegram-bot -Status failed -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Telegram bot deployment failed; inspect the deploy report." -ServerConfig $serverConfigPath
        $lifecycleStarted = $false
    }
    throw
}
finally {
    if ($sshOptions) {
        & ssh @sshOptions $target "sudo rm -f -- '${remotePrefix}.code.tar.gz' '${remotePrefix}.env' '${remotePrefix}.sqlite3' '${remotePrefix}.service' '${remotePrefix}.install.sh'" 2>$null
    }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
