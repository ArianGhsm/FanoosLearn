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
$temporaryRoot = Join-Path $env:TEMP ('dent-telegram-repair-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$localRepair = Join-Path $temporaryRoot 'repair.sh'
$remoteRepair = "/tmp/dent-telegram-repair-$([Guid]::NewGuid().ToString('N')).sh"

New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
Copy-Item -LiteralPath ([IO.Path]::GetFullPath((Join-Path $root ([string]$server.knownHostsFile)))) -Destination $runtimeKnownHosts
$sshOptions = @(
    '-i', $identityFile, '-p', [string]$server.port,
    '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
    '-o', "UserKnownHostsFile=$runtimeKnownHosts", '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1'
)
$scpOptions = @(
    '-q', '-i', $identityFile, '-P', [string]$server.port,
    '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
    '-o', "UserKnownHostsFile=$runtimeKnownHosts", '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1'
)
$repair = @'
#!/usr/bin/env bash
set -euo pipefail

install -d -o root -g root -m 0711 /etc/integrated-dent
runuser -u dentegress -- test -r /etc/integrated-dent/telegram-egress.json
runuser -u dentegress -- /usr/local/lib/integrated-dent/xray/xray run -test -c /etc/integrated-dent/telegram-egress.json >/dev/null

systemctl reset-failed integrated-dent-telegram-egress.service integrated-dent-telegram-egress-refresh.service || true
systemctl restart integrated-dent-telegram-egress.service
listener_ready=0
for attempt in $(seq 1 60); do
  if ss -H -lnt 'sport = :11080' | grep -q '127.0.0.1:11080'; then
    listener_ready=1
    break
  fi
  sleep 0.2
done
test "$listener_ready" = 1

refresh_result=ok
if ! systemctl start integrated-dent-telegram-egress-refresh.service; then
  refresh_result=failed
fi

systemctl restart integrated-dent-bot.service
sleep 3
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-telegram-egress.service
systemctl is-active --quiet integrated-dent-telegram-egress-refresh.timer
ss -H -lnt 'sport = :11080' | grep -q '127.0.0.1:11080'

set -a
source /etc/integrated-dent/dent-bot.env
set +a
cd /opt/integrated-dent/telegram/current
runuser -u dentbot -- python3 -m dent_bot.health >/dev/null
sqlite3 /var/lib/integrated-dent/dent-bot/state.sqlite3 'PRAGMA integrity_check;' | grep -qx ok
systemctl is-active --quiet integrated-dent-bale-bot.service

printf 'SHARED_ENV_DIR_MODE=%s\n' "$(stat -c '%a:%U:%G' /etc/integrated-dent)"
echo XRAY_CONFIG_TEST=ok
echo PROXY_LISTENER=ready
echo TELEGRAM_BOT=active
echo TELEGRAM_HEALTH=ready
echo TELEGRAM_STATE_SQLITE=ok
echo BALE_SERVICE=active
echo REFRESH_RESULT="$refresh_result"
if test "$refresh_result" != ok; then
  exit 2
fi
'@
[IO.File]::WriteAllText($localRepair, $repair, [Text.UTF8Encoding]::new($false))
try {
    & scp @scpOptions $localRepair "${target}:$remoteRepair"
    if ($LASTEXITCODE -ne 0) { throw 'Could not transfer Telegram egress repair.' }
    & ssh @sshOptions $target "sudo bash '$remoteRepair'"
    if ($LASTEXITCODE -ne 0) { throw 'Telegram egress repair or forced refresh failed.' }
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteRepair'" 2>$null | Out-Null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
