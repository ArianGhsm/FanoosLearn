[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$Snapshot = "",
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [switch]$SkipCodeDeploy
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the configured server." }
$sshUser = if ($server.user) { $server.user } else { $server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"

if (-not $SkipCodeDeploy) {
    & (Join-Path $PSScriptRoot "deploy-notifier-to-vps.ps1") -ServerConfig $ServerConfig
    if ($LASTEXITCODE -ne 0) { throw "Code deployment failed before state restore." }
}

$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-server-restore-" + [Guid]::NewGuid().ToString("N"))
$extractPath = Join-Path $temporaryRoot "state"
$bundlePath = Join-Path $temporaryRoot "state.tar.gz"
$remoteBundle = "/tmp/integrated-dent-state-$([Guid]::NewGuid().ToString('N')).tar.gz"
$remoteScript = "$remoteBundle.restore.sh"
try {
    New-Item -ItemType Directory -Force -Path $extractPath | Out-Null
    $restoreArgs = @{ Destination = $extractPath }
    if (-not [string]::IsNullOrWhiteSpace($Snapshot)) { $restoreArgs.Snapshot = $Snapshot }
    & (Join-Path $PSScriptRoot "restore-vps-state.ps1") @restoreArgs | Out-Null
    & tar -C $extractPath -czf $bundlePath .
    if ($LASTEXITCODE -ne 0) { throw "Verified state could not be packaged for restore." }
    & scp -q -i $identityFile -P $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes $bundlePath "${target}:$remoteBundle"
    if ($LASTEXITCODE -ne 0) { throw "Verified state could not be transferred to the target server." }

    $template = @'
#!/usr/bin/env bash
set -euo pipefail
umask 077
bundle='__BUNDLE__'
stage="$(mktemp -d /tmp/integrated-dent-restore.XXXXXX)"
trap 'rm -rf -- "$stage" "$bundle"' EXIT
tar -xzf "$bundle" -C "$stage"
test -f "$stage/SHA256SUMS.json"
test -f "$stage/config/deploy-notifier.env"
test -f "$stage/config/dent-bot.env"
test -f "$stage/config/bale-bot.env"
systemctl stop integrated-dent-bot.service integrated-dent-bale-bot.service integrated-deploy-notifier-flush.timer 2>/dev/null || true
id dentbot >/dev/null 2>&1 || useradd --system --home-dir /nonexistent --shell /usr/sbin/nologin dentbot
id dentbale >/dev/null 2>&1 || useradd --system --home-dir /nonexistent --shell /usr/sbin/nologin dentbale
getent group dentcommerce >/dev/null 2>&1 || groupadd --system dentcommerce
usermod -a -G dentcommerce dentbot
usermod -a -G dentcommerce dentbale
install -d -o root -g root -m 0711 /etc/integrated-dent
install -m 0600 "$stage/config/deploy-notifier.env" /etc/integrated-dent/deploy-notifier.env
install -m 0600 "$stage/config/dent-bot.env" /etc/integrated-dent/dent-bot.env
install -m 0600 "$stage/config/bale-bot.env" /etc/integrated-dent/bale-bot.env
install -d -o dentops -g dentops -m 0700 /var/lib/integrated-dent/deploy-notifier
for d in pending delivered deferred; do
  rm -rf -- "/var/lib/integrated-dent/deploy-notifier/$d"
  if test -d "$stage/runtime/deploy-notifier/$d"; then
    cp -a "$stage/runtime/deploy-notifier/$d" /var/lib/integrated-dent/deploy-notifier/
  else
    install -d -o dentops -g dentops -m 0700 "/var/lib/integrated-dent/deploy-notifier/$d"
  fi
done
chown -R dentops:dentops /var/lib/integrated-dent/deploy-notifier
chmod -R u=rwX,go= /var/lib/integrated-dent/deploy-notifier
for bot in dent-bot bale-bot; do
  owner=dentbot
  test "$bot" = bale-bot && owner=dentbale
  install -d -o "$owner" -g "$owner" -m 0700 "/var/lib/integrated-dent/$bot"
  if test -f "$stage/runtime/$bot/state.sqlite3"; then
    install -o "$owner" -g "$owner" -m 0600 "$stage/runtime/$bot/state.sqlite3" "/var/lib/integrated-dent/$bot/state.sqlite3"
  fi
done
install -d -o root -g dentcommerce -m 2770 /var/lib/integrated-dent/shared
if test -f "$stage/runtime/shared/payment-offers.sqlite3"; then
  install -o root -g dentcommerce -m 0660 "$stage/runtime/shared/payment-offers.sqlite3" /var/lib/integrated-dent/shared/payment-offers.sqlite3
fi
systemctl enable --now integrated-deploy-notifier-flush.timer
systemctl enable integrated-dent-bot.service
systemctl restart integrated-dent-bot.service
if grep -q '^DENT_BALE_RUNTIME_ENABLED=1$' /etc/integrated-dent/bale-bot.env; then
  systemctl enable integrated-dent-bale-bot.service
  systemctl restart integrated-dent-bale-bot.service
else
  systemctl disable --now integrated-dent-bale-bot.service >/dev/null 2>&1 || true
fi
sleep 3
telegram_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
test "$telegram_pid" -gt 0
test "$(readlink -f "/proc/$telegram_pid/cwd")" = "$(readlink -f /opt/integrated-dent/telegram/current)"
if systemctl is-active --quiet integrated-dent-bale-bot.service; then
  bale_pid="$(systemctl show integrated-dent-bale-bot.service -p MainPID --value)"
  test "$bale_pid" -gt 0
  test "$(readlink -f "/proc/$bale_pid/cwd")" = "$(readlink -f /opt/integrated-dent/bale/current)"
fi
cd /opt/integrated-dent/current
set -a
source /etc/integrated-dent/deploy-notifier.env
set +a
python3 -m deploy_notifier.cli health
echo RESTORE_APPLIED=true
'@
    $scriptPath = Join-Path $temporaryRoot "restore.sh"
    [IO.File]::WriteAllText($scriptPath, $template.Replace('__BUNDLE__', $remoteBundle), [Text.UTF8Encoding]::new($false))
    & scp -q -i $identityFile -P $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes $scriptPath "${target}:$remoteScript"
    if ($LASTEXITCODE -ne 0) { throw "Restore activation script transfer failed." }
    & ssh -i $identityFile -p $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes $target "bash '$remoteScript'"
    if ($LASTEXITCODE -ne 0) { throw "Target server state restore failed." }
} finally {
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
    & ssh -i $identityFile -p $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes $target "rm -f -- '$remoteBundle' '$remoteScript'" 2>$null
}
