[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [string]$KnownHostsFile = ".codex-local/iran_known_hosts"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$releaseId = Get-Date -Format "yyyyMMdd-HHmmss"
$lifecycleBaseId = "payment-offer-sync-$releaseId"
$lifecycleStarted = $false
$terminalPublished = $false
. (Join-Path $PSScriptRoot "deploy-lifecycle.ps1")

$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-payment-sync-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$bundle = Join-Path $temporaryRoot "payment-sync-code.tar.gz"
$installerPath = Join-Path $temporaryRoot "install.sh"
$remotePrefix = "/tmp/integrated-dent-payment-sync-$([Guid]::NewGuid().ToString('N'))"
$remoteBundle = "$remotePrefix.tar.gz"
$remoteTelegramUnit = "$remotePrefix-telegram.service"
$remoteBaleUnit = "$remotePrefix-bale.service"
$remoteInstaller = "$remotePrefix.sh"

$knownHostsSource = [IO.Path]::GetFullPath((Join-Path $root $KnownHostsFile))
if (-not (Test-Path -LiteralPath $knownHostsSource -PathType Leaf)) {
    throw "The pinned Iran known-hosts file is missing."
}
New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
Copy-Item -LiteralPath $knownHostsSource -Destination $runtimeKnownHosts -Force
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

try {
    Publish-DentDeployLifecycle -Service integrated-ops -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Shared Telegram and Bale payment-offer migration started."
    $lifecycleStarted = $true

    # The migration is not allowed to start without a fresh, laptop-held,
    # DPAPI-encrypted and restore-verified production snapshot.
    & (Join-Path $PSScriptRoot "backup-vps-state.ps1") `
        -ServerConfig $ServerConfig `
        -BackupRoot "backups/vps-state-iran"
    if ($LASTEXITCODE -ne 0) { throw "The pre-migration laptop backup failed." }

    & tar -C $root -czf $bundle dent_bot scripts/migrate-payment-offers-to-shared.py
    if ($LASTEXITCODE -ne 0) { throw "The shared bot code bundle could not be created." }

    $transfers = @(
        @($bundle, $remoteBundle),
        @((Join-Path $root "ops\systemd\integrated-dent-bot.service"), $remoteTelegramUnit),
        @((Join-Path $root "ops\systemd\integrated-dent-bale-bot.service"), $remoteBaleUnit)
    )
    foreach ($transfer in $transfers) {
        & scp @scpOptions $transfer[0] "${target}:$($transfer[1])"
        if ($LASTEXITCODE -ne 0) { throw "The payment synchronization deployment transfer failed." }
    }

    $installer = @'
#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
bundle='__REMOTE_BUNDLE__'
telegram_unit='__TELEGRAM_UNIT__'
bale_unit='__BALE_UNIT__'
release_root='/opt/integrated-dent/releases/bots-__RELEASE_ID__'
telegram_current='/opt/integrated-dent/telegram/current'
bale_current='/opt/integrated-dent/bale/current'
shared_dir='/var/lib/integrated-dent/shared'
shared_db="$shared_dir/payment-offers.sqlite3"
telegram_db='/var/lib/integrated-dent/dent-bot/state.sqlite3'
bale_db='/var/lib/integrated-dent/bale-bot/state.sqlite3'
rollback_root="$(mktemp -d /tmp/integrated-dent-payment-rollback.XXXXXX)"
previous_telegram="$(readlink -f "$telegram_current")"
previous_bale="$(readlink -f "$bale_current")"
had_shared=0
if test -s "$shared_db"; then
  had_shared=1
fi
shared_snapshot_ready=0

cleanup() {
  rm -f -- "$bundle" "$telegram_unit" "$bale_unit"
  rm -rf -- "$rollback_root"
}

rollback() {
  status=$?
  trap - ERR
  set +e
  systemctl stop integrated-dent-bot.service integrated-dent-bale-bot.service
  if test "$shared_snapshot_ready" -eq 1; then
    rm -f -- "$shared_db" "$shared_db-wal" "$shared_db-shm"
    if test "$had_shared" -eq 1 && test -s "$rollback_root/payment-offers.sqlite3"; then
      install -d -o root -g dentcommerce -m 2770 "$shared_dir"
      install -o root -g dentcommerce -m 0660 "$rollback_root/payment-offers.sqlite3" "$shared_db"
    fi
  fi
  if test -s "$rollback_root/dent-bot.env"; then
    cp -a "$rollback_root/dent-bot.env" /etc/integrated-dent/dent-bot.env
  fi
  if test -s "$rollback_root/bale-bot.env"; then
    cp -a "$rollback_root/bale-bot.env" /etc/integrated-dent/bale-bot.env
  fi
  if test -s "$rollback_root/telegram.service"; then
    cp -a "$rollback_root/telegram.service" /etc/systemd/system/integrated-dent-bot.service
  fi
  if test -s "$rollback_root/bale.service"; then
    cp -a "$rollback_root/bale.service" /etc/systemd/system/integrated-dent-bale-bot.service
  fi
  if test -n "$previous_telegram"; then
    ln -sfn "$previous_telegram" "$telegram_current"
  fi
  if test -n "$previous_bale"; then
    ln -sfn "$previous_bale" "$bale_current"
  fi
  systemctl daemon-reload
  systemctl start integrated-dent-bot.service integrated-dent-bale-bot.service
  telegram_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
  bale_pid="$(systemctl show integrated-dent-bale-bot.service -p MainPID --value)"
  if systemctl is-active --quiet integrated-dent-bot.service && \
     systemctl is-active --quiet integrated-dent-bale-bot.service && \
     test "$telegram_pid" -gt 0 && \
     test "$bale_pid" -gt 0 && \
     test "$(readlink -f "/proc/$telegram_pid/cwd")" = "$previous_telegram" && \
     test "$(readlink -f "/proc/$bale_pid/cwd")" = "$previous_bale"; then
    echo ROLLBACK_OK
  else
    echo ROLLBACK_FAILED
  fi
  cleanup
  exit "$status"
}
trap rollback ERR

test -s "$bundle"
test -s "$telegram_unit"
test -s "$bale_unit"
test -n "$previous_telegram"
test -n "$previous_bale"
test -s "$telegram_db"
test -s "$bale_db"
test -s /etc/integrated-dent/dent-bot.env
test -s /etc/integrated-dent/bale-bot.env
sqlite3 "$telegram_db" 'PRAGMA integrity_check;' | grep -qx ok
sqlite3 "$bale_db" 'PRAGMA integrity_check;' | grep -qx ok
if test "$had_shared" -eq 1; then
  sqlite3 "$shared_db" 'PRAGMA integrity_check;' | grep -qx ok
fi
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service

cp -a /etc/integrated-dent/dent-bot.env "$rollback_root/dent-bot.env"
cp -a /etc/integrated-dent/bale-bot.env "$rollback_root/bale-bot.env"
cp -a /etc/systemd/system/integrated-dent-bot.service "$rollback_root/telegram.service"
cp -a /etc/systemd/system/integrated-dent-bale-bot.service "$rollback_root/bale.service"

install -d -m 0755 "$release_root"
tar -xzf "$bundle" -C "$release_root"
chown -R root:root "$release_root"
find "$release_root" -type d -exec chmod 0755 {} +
find "$release_root" -type f -exec chmod 0644 {} +
python3 -m compileall -q "$release_root/dent_bot"

systemctl stop integrated-dent-bot.service integrated-dent-bale-bot.service
if test "$had_shared" -eq 1; then
  sqlite3 "$shared_db" ".backup '$rollback_root/payment-offers.sqlite3'"
  sqlite3 "$rollback_root/payment-offers.sqlite3" 'PRAGMA integrity_check;' | grep -qx ok
  shared_snapshot_ready=1
else
  shared_snapshot_ready=1
  python3 "$release_root/scripts/migrate-payment-offers-to-shared.py" \
    --telegram "$telegram_db" \
    --bale "$bale_db" \
    --target "$rollback_root/payment-offers.sqlite3"
fi

getent group dentcommerce >/dev/null 2>&1 || groupadd --system dentcommerce
usermod -a -G dentcommerce dentbot
usermod -a -G dentcommerce dentbale
install -d -o root -g dentcommerce -m 2770 "$shared_dir"
if test "$had_shared" -eq 0; then
  install -o root -g dentcommerce -m 0660 "$rollback_root/payment-offers.sqlite3" "$shared_db"
else
  chown root:dentcommerce "$shared_db"
  chmod 0660 "$shared_db"
fi

sed -i '/^DENT_BOT_PAYMENT_OFFERS_DB=/d' /etc/integrated-dent/dent-bot.env
printf '%s\n' 'DENT_BOT_PAYMENT_OFFERS_DB=/var/lib/integrated-dent/shared/payment-offers.sqlite3' >> /etc/integrated-dent/dent-bot.env
sed -i '/^DENT_BALE_PAYMENT_OFFERS_DB=/d' /etc/integrated-dent/bale-bot.env
printf '%s\n' 'DENT_BALE_PAYMENT_OFFERS_DB=/var/lib/integrated-dent/shared/payment-offers.sqlite3' >> /etc/integrated-dent/bale-bot.env
chmod 0600 /etc/integrated-dent/dent-bot.env /etc/integrated-dent/bale-bot.env

sed \
  -e 's#After=network-online.target#After=network-online.target integrated-dent-telegram-egress.service#' \
  -e '/^Wants=network-online.target$/a Requires=integrated-dent-telegram-egress.service' \
  -e 's#WorkingDirectory=/opt/integrated-dent/current#WorkingDirectory=/opt/integrated-dent/telegram/current#' \
  "$telegram_unit" > /etc/systemd/system/integrated-dent-bot.service
sed 's#WorkingDirectory=/opt/integrated-dent/current#WorkingDirectory=/opt/integrated-dent/bale/current#' \
  "$bale_unit" > /etc/systemd/system/integrated-dent-bale-bot.service
chmod 0644 /etc/systemd/system/integrated-dent-bot.service /etc/systemd/system/integrated-dent-bale-bot.service
ln -sfn "$release_root" "$telegram_current"
ln -sfn "$release_root" "$bale_current"
systemctl daemon-reload
systemctl start integrated-dent-bot.service integrated-dent-bale-bot.service
sleep 4
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
test "$(systemctl show integrated-dent-bot.service -p NRestarts --value)" = 0
test "$(systemctl show integrated-dent-bale-bot.service -p NRestarts --value)" = 0
telegram_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
bale_pid="$(systemctl show integrated-dent-bale-bot.service -p MainPID --value)"
test "$telegram_pid" -gt 0
test "$bale_pid" -gt 0
test "$(readlink -f "/proc/$telegram_pid/cwd")" = "$release_root"
test "$(readlink -f "/proc/$bale_pid/cwd")" = "$release_root"

set -a
source /etc/integrated-dent/dent-bot.env
set +a
runuser -u dentbot -- bash -c 'cd /opt/integrated-dent/telegram/current && python3 -m dent_bot.health'
set +a
set -a
source /etc/integrated-dent/bale-bot.env
set +a
runuser -u dentbale -- bash -c 'cd /opt/integrated-dent/bale/current && python3 -m dent_bot.bale_health'
sqlite3 "$shared_db" 'PRAGMA integrity_check;' | grep -qx ok
test "$(stat -c %a "$shared_dir")" = 2770
test "$(stat -c %a "$shared_db")" = 660
test "$(stat -c %G "$shared_db")" = dentcommerce
echo SHARED_PAYMENT_OFFERS="$(sqlite3 "$shared_db" 'SELECT COUNT(*) FROM payment_offers;')"
echo PAYMENT_SYNC_DEPLOY_OK
echo PAYMENT_SYNC_RELEASE=__RELEASE_ID__

trap - ERR
cleanup
'@
    $installer = $installer.Replace('__REMOTE_BUNDLE__', $remoteBundle).
        Replace('__TELEGRAM_UNIT__', $remoteTelegramUnit).
        Replace('__BALE_UNIT__', $remoteBaleUnit).
        Replace('__RELEASE_ID__', $releaseId)
    [IO.File]::WriteAllText($installerPath, $installer, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $installerPath "${target}:$remoteInstaller"
    if ($LASTEXITCODE -ne 0) { throw "The payment synchronization installer could not be transferred." }

    $activationOutput = @(& ssh @sshOptions $target "sudo bash '$remoteInstaller'" 2>&1)
    $activationExit = $LASTEXITCODE
    $activationOutput | ForEach-Object { Write-Output $_ }
    if ($activationExit -ne 0) {
        if ($activationOutput -contains "ROLLBACK_OK") {
            Publish-DentDeployLifecycle -Service integrated-ops -Status rolled_back -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Payment-offer migration failed and both bot runtimes were restored."
            $terminalPublished = $true
        }
        throw "The payment synchronization deployment failed."
    }

    # Capture the new shared store immediately and require an independent local
    # restore verification before reporting the rollout as successful.
    & (Join-Path $PSScriptRoot "backup-vps-state.ps1") `
        -ServerConfig $ServerConfig `
        -BackupRoot "backups/vps-state-iran"
    if ($LASTEXITCODE -ne 0) { throw "The post-migration laptop backup failed." }

    Publish-DentDeployLifecycle -Service integrated-ops -Status succeeded -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Telegram and Bale now share one restore-verified payment-offer store."
    $terminalPublished = $true
    $lifecycleStarted = $false
}
catch {
    if ($lifecycleStarted -and -not $terminalPublished) {
        Publish-DentDeployLifecycle -Service integrated-ops -Status failed -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Payment-offer synchronization failed; inspect the deployment report."
        $terminalPublished = $true
    }
    throw
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteBundle' '$remoteTelegramUnit' '$remoteBaleUnit' '$remoteInstaller'" 2>$null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
