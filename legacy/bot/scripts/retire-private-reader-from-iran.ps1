[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [Parameter(Mandatory)][string]$VerifiedSnapshot,
    [string]$ServerConfig = ".codex-local/iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$snapshotPath = (Resolve-Path -LiteralPath $VerifiedSnapshot).Path
if (-not $snapshotPath.EndsWith('.tar.gz.dpapi', [StringComparison]::OrdinalIgnoreCase)) {
    throw "VerifiedSnapshot must be a DPAPI-encrypted VPS snapshot."
}
$manifestPath = $snapshotPath.Substring(0, $snapshotPath.Length - '.tar.gz.dpapi'.Length) + '.manifest.json'
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) { throw "Snapshot manifest is missing." }
$manifest = Get-Content -Raw -Encoding UTF8 -LiteralPath $manifestPath | ConvertFrom-Json
if ($manifest.restoreVerified -ne $true) { throw "Snapshot has not passed restore verification." }

$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$releaseId = Get-Date -Format "yyyyMMdd-HHmmss"
$lifecycleBaseId = "integrated-ops-reader-retirement-$releaseId"
$lifecycleStarted = $false
. (Join-Path $PSScriptRoot "deploy-lifecycle.ps1")
$temporaryRoot = Join-Path $env:TEMP ("dent-reader-retirement-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$localScript = Join-Path $temporaryRoot "retire.sh"
$remoteScript = "/tmp/dent-reader-retirement-$([Guid]::NewGuid().ToString('N')).sh"

try {
    Publish-DentDeployLifecycle -Service integrated-ops -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Retirement of the obsolete private Reader runtime started."
    $lifecycleStarted = $true
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath (Join-Path $root ([string]$server.knownHostsFile)) -Destination $runtimeKnownHosts
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
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service

for unit in integrated-private-reader-worker.service integrated-private-reader-cleanup.timer integrated-private-reader-cleanup.service; do
  systemctl disable --now "$unit" >/dev/null 2>&1 || true
done

rm -f -- \
  /etc/systemd/system/integrated-private-reader-worker.service \
  /etc/systemd/system/integrated-private-reader-cleanup.timer \
  /etc/systemd/system/integrated-private-reader-cleanup.service \
  /etc/nginx/sites-enabled/private-reader.conf \
  /etc/nginx/sites-available/private-reader.conf \
  /etc/php/8.3/fpm/conf.d/99-private-reader.ini \
  /etc/integrated-dent/private-reader.env

remove_reader_tree() {
  local target="$1"
  case "$target" in
    /opt/integrated-dent/private-reader|/var/lib/integrated-dent/private-reader)
      rm -rf --one-file-system -- "$target"
      ;;
    *)
      echo "Unsafe Reader retirement target" >&2
      exit 70
      ;;
  esac
}
remove_reader_tree /opt/integrated-dent/private-reader
remove_reader_tree /var/lib/integrated-dent/private-reader

while IFS= read -r -d '' release; do
  case "$release" in
    /opt/integrated-dent/releases/private-reader-*) rm -rf --one-file-system -- "$release" ;;
    *) echo "Unsafe Reader release target" >&2; exit 71 ;;
  esac
done < <(find /opt/integrated-dent/releases -mindepth 1 -maxdepth 1 -type d -name 'private-reader-*' -print0)

if test -f /etc/letsencrypt/renewal/reader.dentistry1402tums.ir.conf && command -v certbot >/dev/null 2>&1; then
  certbot delete --non-interactive --cert-name reader.dentistry1402tums.ir >/dev/null
fi

systemctl daemon-reload
systemctl reset-failed >/dev/null 2>&1 || true
nginx -t >/dev/null
systemctl reload nginx

test ! -e /opt/integrated-dent/private-reader
test ! -e /var/lib/integrated-dent/private-reader
test ! -e /etc/integrated-dent/private-reader.env
test ! -e /etc/nginx/sites-enabled/private-reader.conf
test ! -e /etc/nginx/sites-available/private-reader.conf
test ! -e /etc/php/8.3/fpm/conf.d/99-private-reader.ini
test -z "$(find /opt/integrated-dent/releases -mindepth 1 -maxdepth 1 -type d -name 'private-reader-*' -print -quit)"
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
echo PRIVATE_READER_RETIRED_OK
'@
    [IO.File]::WriteAllText($localScript, $script, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $localScript "${target}:$remoteScript"
    if ($LASTEXITCODE -ne 0) { throw "Reader retirement script transfer failed." }
    & ssh @sshOptions $target "sudo bash '$remoteScript'"
    if ($LASTEXITCODE -ne 0) { throw "Reader retirement failed." }
    Publish-DentDeployLifecycle -Service integrated-ops -Status succeeded -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "The obsolete private Reader runtime was removed; Telegram and Bale remained healthy."
    $lifecycleStarted = $false
}
catch {
    if ($lifecycleStarted) {
        Publish-DentDeployLifecycle -Service integrated-ops -Status failed -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Private Reader retirement failed; inspect the operational report."
        $lifecycleStarted = $false
    }
    throw
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteScript'" 2>$null | Out-Null }
    if (Test-Path -LiteralPath $temporaryRoot) {
        $resolvedTemp = [IO.Path]::GetFullPath($temporaryRoot)
        $resolvedBase = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
        if ($resolvedTemp.StartsWith($resolvedBase, [StringComparison]::OrdinalIgnoreCase)) {
            Remove-Item -LiteralPath $resolvedTemp -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
}
