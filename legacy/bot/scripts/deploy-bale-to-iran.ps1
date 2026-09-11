[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [string]$KnownHostsFile = ".codex-local/iran_known_hosts",
    [string]$Snapshot = ""
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$serverPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $root $ServerConfig }
$serverPath = (Resolve-Path -LiteralPath $serverPath).Path
$serverStateRoot = if ((Split-Path $serverPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverPath -Parent) -Leaf) -eq '.codex-local') { Split-Path (Split-Path $serverPath -Parent) -Parent } else { $root }
$knownHostsPath = if ([IO.Path]::IsPathRooted($KnownHostsFile)) { $KnownHostsFile } else { Join-Path $serverStateRoot $KnownHostsFile }
$knownHostsPath = [IO.Path]::GetFullPath($knownHostsPath)
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath $serverPath | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) {
    throw "ConfirmTargetHost does not match the configured Iran server."
}
if (-not (Test-Path -LiteralPath $knownHostsPath -PathType Leaf)) {
    throw "The pinned Iran known-hosts file is missing."
}

$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$releaseId = Get-Date -Format "yyyyMMdd-HHmmss"
$lifecycleBaseId = "bale-bot-$releaseId"
$lifecycleStarted = $false
. (Join-Path $PSScriptRoot "deploy-lifecycle.ps1")
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-bale-deploy-" + [Guid]::NewGuid().ToString("N"))
$restoreRoot = Join-Path $temporaryRoot "restore"
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$codeBundle = Join-Path $temporaryRoot "bale-code.tar.gz"
$remotePrefix = "/tmp/integrated-dent-bale-$([Guid]::NewGuid().ToString('N'))"
$remoteCode = "$remotePrefix-code.tar.gz"
$remoteEnv = "$remotePrefix.env"
$remoteSharedEnv = "$remotePrefix-shared.env"
$remoteState = "$remotePrefix.sqlite3"
$remoteUnit = "$remotePrefix.service"
$remoteInstaller = "$remotePrefix-install.sh"

New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
Copy-Item -LiteralPath $knownHostsPath -Destination $runtimeKnownHosts -Force

$sshOptions = @(
    "-i", $identityFile,
    "-p", [string]$server.port,
    "-o", "BatchMode=yes",
    "-o", "StrictHostKeyChecking=yes",
    "-o", "UserKnownHostsFile=$runtimeKnownHosts"
)
$scpOptions = @(
    "-q",
    "-i", $identityFile,
    "-P", [string]$server.port,
    "-o", "BatchMode=yes",
    "-o", "StrictHostKeyChecking=yes",
    "-o", "UserKnownHostsFile=$runtimeKnownHosts"
)

try {
    Publish-DentDeployLifecycle -Service bale-bot -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Bale bot deployment started." -ServerConfig $serverPath
    $lifecycleStarted = $true
    New-Item -ItemType Directory -Force -Path $restoreRoot | Out-Null
    $restoreArgs = @{ Destination = $restoreRoot }
    if (-not [string]::IsNullOrWhiteSpace($Snapshot)) {
        $restoreArgs.Snapshot = $Snapshot
    }
    & (Join-Path $PSScriptRoot "restore-vps-state.ps1") @restoreArgs | Out-Null

    $baleEnv = Join-Path $restoreRoot "config\bale-bot.env"
    $sharedEnv = Join-Path $restoreRoot "config\dent-bot.env"
    $baleState = Join-Path $restoreRoot "runtime\bale-bot\state.sqlite3"
    if (-not (Test-Path -LiteralPath $baleEnv -PathType Leaf)) {
        throw "The verified backup does not contain Bale configuration."
    }
    if (-not (Test-Path -LiteralPath $baleState -PathType Leaf)) {
        throw "The verified backup does not contain Bale state."
    }
    if (-not (Test-Path -LiteralPath $sharedEnv -PathType Leaf)) {
        throw "The verified backup does not contain the shared bot configuration."
    }

    & tar -C $root -czf $codeBundle dent_bot
    if ($LASTEXITCODE -ne 0) { throw "The Bale code bundle could not be created." }

    & scp @scpOptions $codeBundle "${target}:$remoteCode"
    if ($LASTEXITCODE -ne 0) { throw "The Bale code bundle could not be transferred." }
    & scp @scpOptions $baleEnv "${target}:$remoteEnv"
    if ($LASTEXITCODE -ne 0) { throw "The Bale configuration could not be transferred." }
    & scp @scpOptions $sharedEnv "${target}:$remoteSharedEnv"
    if ($LASTEXITCODE -ne 0) { throw "The shared bot configuration could not be transferred." }
    & scp @scpOptions $baleState "${target}:$remoteState"
    if ($LASTEXITCODE -ne 0) { throw "The Bale state could not be transferred." }
    & scp @scpOptions (Join-Path $root "ops\systemd\integrated-dent-bale-bot.service") "${target}:$remoteUnit"
    if ($LASTEXITCODE -ne 0) { throw "The Bale systemd unit could not be transferred." }

    $installerTemplate = @'
#!/usr/bin/env bash
set -euo pipefail
umask 077
code_bundle='__REMOTE_CODE__'
env_source='__REMOTE_ENV__'
shared_env_source='__REMOTE_SHARED_ENV__'
state_source='__REMOTE_STATE__'
unit_source='__REMOTE_UNIT__'
release_root='/opt/integrated-dent/releases/bale-__RELEASE_ID__'
current_link='/opt/integrated-dent/bale/current'
previous_target="$(readlink -f "$current_link" 2>/dev/null || true)"
rollback_root="$(mktemp -d /tmp/integrated-dent-bale-rollback.XXXXXX)"
had_env=0
had_unit=0
activated=0
rollback_activation() {
  if test "$had_env" = 1; then
    cp -a "$rollback_root/bale-bot.env" /etc/integrated-dent/bale-bot.env
  else
    rm -f -- /etc/integrated-dent/bale-bot.env
  fi
  if test "$had_unit" = 1; then
    cp -a "$rollback_root/integrated-dent-bale-bot.service" /etc/systemd/system/integrated-dent-bale-bot.service
  else
    rm -f -- /etc/systemd/system/integrated-dent-bale-bot.service
  fi
  systemctl daemon-reload
  if test -n "$previous_target" && test -d "$previous_target"; then
    ln -sfn "$previous_target" "$current_link"
    systemctl restart integrated-dent-bale-bot.service
  else
    systemctl stop integrated-dent-bale-bot.service || true
  fi
}
cleanup() {
  rm -f -- "$code_bundle" "$env_source" "$shared_env_source" "$state_source" "$unit_source"
  rm -rf -- "$rollback_root"
}
trap 'status=$?; if test "$status" -ne 0 && test "$activated" = 1; then rollback_activation || true; fi; cleanup; exit "$status"' EXIT

test -s "$code_bundle"
test -s "$env_source"
test -s "$shared_env_source"
test -s "$state_source"
test -s "$unit_source"
sqlite3 "$state_source" 'PRAGMA integrity_check;' | grep -qx ok
if test -s /etc/integrated-dent/bale-bot.env; then
  cp -a /etc/integrated-dent/bale-bot.env "$rollback_root/bale-bot.env"
  had_env=1
fi
if test -s /etc/systemd/system/integrated-dent-bale-bot.service; then
  cp -a /etc/systemd/system/integrated-dent-bale-bot.service "$rollback_root/integrated-dent-bale-bot.service"
  had_unit=1
fi

if ! id dentbale >/dev/null 2>&1; then
  useradd --system --home-dir /nonexistent --shell /usr/sbin/nologin dentbale
fi
getent group dentcommerce >/dev/null 2>&1 || groupadd --system dentcommerce
usermod -a -G dentcommerce dentbale
test -s /var/lib/integrated-dent/shared/payment-offers.sqlite3
install -d -m 0755 /opt/integrated-dent/releases /opt/integrated-dent/bale
install -d -o dentbale -g dentbale -m 0700 /var/lib/integrated-dent/bale-bot
install -d -o root -g root -m 0711 /etc/integrated-dent
install -d -m 0755 "$release_root"
tar -xzf "$code_bundle" -C "$release_root"
chown -R root:root "$release_root"
find "$release_root" -type d -exec chmod 0755 {} +
find "$release_root" -type f -exec chmod 0644 {} +
python3 -m compileall -q "$release_root/dent_bot"

activated=1
if ! test -s /etc/integrated-dent/bale-bot.env; then
  install -o root -g root -m 0600 "$env_source" /etc/integrated-dent/bale-bot.env
fi
if ! grep -q '^DENT_BOT_SITE_SERVICE_SECRET=' /etc/integrated-dent/bale-bot.env; then
  grep '^DENT_BOT_SITE_SERVICE_SECRET=' "$shared_env_source" | tail -n 1 >> /etc/integrated-dent/bale-bot.env
fi
sed -i \
  -e '/^DENT_BALE_PAYMENT_OFFERS_DB=/d' \
  -e '/^DENT_BALE_RUNTIME_ENABLED=/d' \
  -e '/^DENT_BALE_SITE_URL=/d' \
  -e '/^DENT_BALE_SITE_API_URL=/d' \
  -e '/^DENT_BALE_UPDATE_WORKERS=/d' \
  -e '/^DENT_BALE_PAYMENT_RESULT_PUSH_ENABLED=/d' \
  -e '/^DENT_BALE_PAYMENT_RESULT_POLL_SECONDS=/d' \
  -e '/^DENT_BALE_PAYMENT_RESULT_BATCH_SIZE=/d' \
  /etc/integrated-dent/bale-bot.env
printf '\n%s\n' \
  'DENT_BALE_PAYMENT_OFFERS_DB=/var/lib/integrated-dent/shared/payment-offers.sqlite3' \
  'DENT_BALE_RUNTIME_ENABLED=1' \
  'DENT_BALE_SITE_URL=https://dentistry1402tums.ir' \
  'DENT_BALE_SITE_API_URL=https://dentistry1402tums.ir/api/bot_api.php?action=service' \
  'DENT_BALE_UPDATE_WORKERS=2' \
  'DENT_BALE_PAYMENT_RESULT_PUSH_ENABLED=1' \
  'DENT_BALE_PAYMENT_RESULT_POLL_SECONDS=30' \
  'DENT_BALE_PAYMENT_RESULT_BATCH_SIZE=10' \
  >> /etc/integrated-dent/bale-bot.env
if ! test -s /var/lib/integrated-dent/bale-bot/state.sqlite3; then
  install -o dentbale -g dentbale -m 0600 "$state_source" /var/lib/integrated-dent/bale-bot/state.sqlite3
fi

sed 's#WorkingDirectory=/opt/integrated-dent/current#WorkingDirectory=/opt/integrated-dent/bale/current#' "$unit_source" > /etc/systemd/system/integrated-dent-bale-bot.service
chmod 0644 /etc/systemd/system/integrated-dent-bale-bot.service
systemctl daemon-reload
ln -sfn "$release_root" "$current_link"
systemctl enable integrated-dent-bale-bot.service
systemctl restart integrated-dent-bale-bot.service
sleep 3
systemctl is-active --quiet integrated-dent-bale-bot.service
systemctl is-enabled --quiet integrated-dent-bale-bot.service
main_pid="$(systemctl show integrated-dent-bale-bot.service -p MainPID --value)"
test "$main_pid" -gt 0
test "$(readlink -f "/proc/$main_pid/cwd")" = "$release_root"
set -a
source /etc/integrated-dent/bale-bot.env
set +a
runuser -u dentbale -- bash -c 'cd /opt/integrated-dent/bale/current && python3 -m dent_bot.bale_health'
sqlite3 /var/lib/integrated-dent/bale-bot/state.sqlite3 'PRAGMA integrity_check;' | grep -qx ok
activated=0
echo BALE_DEPLOY_OK
echo BALE_RELEASE=__RELEASE_ID__
'@
    $installer = $installerTemplate.Replace('__REMOTE_CODE__', $remoteCode).
        Replace('__REMOTE_ENV__', $remoteEnv).
        Replace('__REMOTE_SHARED_ENV__', $remoteSharedEnv).
        Replace('__REMOTE_STATE__', $remoteState).
        Replace('__REMOTE_UNIT__', $remoteUnit).
        Replace('__RELEASE_ID__', $releaseId)
    $installerPath = Join-Path $temporaryRoot "install-bale.sh"
    [IO.File]::WriteAllText($installerPath, $installer, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $installerPath "${target}:$remoteInstaller"
    if ($LASTEXITCODE -ne 0) { throw "The Bale activation script could not be transferred." }

    & ssh @sshOptions $target "sudo bash '$remoteInstaller'"
    if ($LASTEXITCODE -ne 0) { throw "The Bale deployment or health check failed." }
    Publish-DentDeployLifecycle -Service bale-bot -Status succeeded -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Bale bot and its direct network route passed health checks." -ServerConfig $serverPath
    $lifecycleStarted = $false
}
catch {
    if ($lifecycleStarted) {
        Publish-DentDeployLifecycle -Service bale-bot -Status failed -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Bale bot deployment failed; inspect the deploy report." -ServerConfig $serverPath
        $lifecycleStarted = $false
    }
    throw
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteCode' '$remoteEnv' '$remoteSharedEnv' '$remoteState' '$remoteUnit' '$remoteInstaller'" 2>$null
    if (Test-Path -LiteralPath $temporaryRoot) {
        Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}
