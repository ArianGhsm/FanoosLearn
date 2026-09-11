[CmdletBinding()]
param(
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [string]$BackupRoot = "backups/vps-state-iran",
    [int]$KeepDaily = 14,
    [int]$KeepWeekly = 8,
    [switch]$Quiet
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$serverConfigPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $root $ServerConfig }
$serverConfigPath = (Resolve-Path -LiteralPath $serverConfigPath).Path
$serverStateRoot = if ((Split-Path $serverConfigPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverConfigPath -Parent) -Leaf) -eq '.codex-local') { Split-Path (Split-Path $serverConfigPath -Parent) -Parent } else { $root }
$server = Get-Content -Raw -LiteralPath $serverConfigPath | ConvertFrom-Json
$sshUser = if ($server.user) { $server.user } else { $server.bootstrapUser }
if ([string]$sshUser -notmatch '^[a-z_][a-z0-9_-]*$') { throw "The configured SSH user is invalid." }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$snapshotId = "vps-state-$timestamp"
$destinationRoot = [IO.Path]::GetFullPath((Join-Path $root $BackupRoot))
New-Item -ItemType Directory -Force -Path $destinationRoot | Out-Null
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-backup-" + [Guid]::NewGuid().ToString("N"))
$plainArchive = Join-Path $temporaryRoot "$snapshotId.tar.gz"
$localRemoteScript = Join-Path $temporaryRoot "$snapshotId.sh"
$remoteStage = "/tmp/$snapshotId-$([Guid]::NewGuid().ToString('N'))"
$remoteArchive = "$remoteStage.tar.gz"
$remoteScriptPath = "$remoteStage.sh"
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$sshOptions = @("-i", $identityFile, "-p", [string]$server.port, "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes", "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1")
$scpOptions = @("-q", "-i", $identityFile, "-P", [string]$server.port, "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes", "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1")

function Get-Sha256([byte[]]$Bytes) {
    $sha = [Security.Cryptography.SHA256]::Create()
    try { return -join ($sha.ComputeHash($Bytes) | ForEach-Object { $_.ToString('x2') }) }
    finally { $sha.Dispose() }
}

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    if ($server.knownHostsFile) {
        $knownHostsSource = [string]$server.knownHostsFile
        if (-not [IO.Path]::IsPathRooted($knownHostsSource)) { $knownHostsSource = Join-Path $serverStateRoot $knownHostsSource }
        $knownHostsSource = [IO.Path]::GetFullPath($knownHostsSource)
        if (-not (Test-Path -LiteralPath $knownHostsSource -PathType Leaf)) {
            throw "The configured known-hosts file is missing."
        }
        Copy-Item -LiteralPath $knownHostsSource -Destination $runtimeKnownHosts -Force
        $sshOptions += @("-o", "UserKnownHostsFile=$runtimeKnownHosts")
        $scpOptions += @("-o", "UserKnownHostsFile=$runtimeKnownHosts")
    }
    $scriptTemplate = @'
#!/usr/bin/env bash
set -euo pipefail
umask 077
stage='__STAGE__'
archive='__ARCHIVE__'
trap 'rm -rf -- "$stage" "$archive"' EXIT
mkdir -p "$stage/runtime/deploy-notifier" "$stage/runtime/dent-bot" "$stage/runtime/bale-bot" "$stage/runtime/shared" "$stage/config" "$stage/metadata"
for d in pending delivered deferred; do
  if test -d "/var/lib/integrated-dent/deploy-notifier/$d"; then
    cp -a "/var/lib/integrated-dent/deploy-notifier/$d" "$stage/runtime/deploy-notifier/"
  fi
done
for bot in dent-bot bale-bot; do
  db="/var/lib/integrated-dent/$bot/state.sqlite3"
  if test -f "$db"; then
    sqlite3 -cmd '.timeout 10000' "$db" ".backup '$stage/runtime/$bot/state.sqlite3'"
  fi
done
shared_offers="/var/lib/integrated-dent/shared/payment-offers.sqlite3"
if test -f "$shared_offers"; then
  sqlite3 -cmd '.timeout 10000' "$shared_offers" ".backup '$stage/runtime/shared/payment-offers.sqlite3'"
fi
for config in deploy-notifier.env dent-bot.env bale-bot.env; do
  if test -f "/etc/integrated-dent/$config"; then
    cp -a "/etc/integrated-dent/$config" "$stage/config/"
  fi
done
if test -f /etc/integrated-dent/telegram-egress.json; then
  cp -a /etc/integrated-dent/telegram-egress.json "$stage/config/"
fi
systemctl is-enabled integrated-deploy-notifier-flush.timer integrated-dent-bot.service integrated-dent-bale-bot.service > "$stage/metadata/service-enabled.txt" 2>&1 || true
systemctl is-active integrated-deploy-notifier-flush.timer integrated-dent-bot.service integrated-dent-bale-bot.service > "$stage/metadata/service-active.txt" 2>&1 || true
printf '%s\n' '__SNAPSHOT__' > "$stage/metadata/snapshot-id.txt"
python3 - "$stage" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1])
items={}
for path in sorted(root.rglob('*')):
    if path.is_file() and path.name!='SHA256SUMS.json':
        items[path.relative_to(root).as_posix()]=hashlib.sha256(path.read_bytes()).hexdigest()
(root/'SHA256SUMS.json').write_text(json.dumps(items,separators=(',',':')),encoding='utf-8')
PY
tar -C "$stage" -czf "$archive" .
sha256sum "$archive" | cut -d' ' -f1
chown '__SSH_USER__' "$archive"
trap - EXIT
'@
    $remoteScript = $scriptTemplate.Replace('__STAGE__', $remoteStage).Replace('__ARCHIVE__', $remoteArchive).Replace('__SNAPSHOT__', $snapshotId).Replace('__SSH_USER__', [string]$sshUser)
    [IO.File]::WriteAllText($localRemoteScript, $remoteScript, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $localRemoteScript "${target}:$remoteScriptPath"
    if ($LASTEXITCODE -ne 0) { throw "Remote backup script transfer failed." }
    $remoteOutput = @(& ssh @sshOptions $target "sudo bash '$remoteScriptPath'")
    $remoteHash = if ($remoteOutput.Count) { ([string]$remoteOutput[-1]).Trim() } else { "" }
    if ($LASTEXITCODE -ne 0 -or $remoteHash -notmatch '^[a-f0-9]{64}$') { throw "Remote snapshot creation failed." }
    & scp @scpOptions "${target}:$remoteArchive" $plainArchive
    if ($LASTEXITCODE -ne 0) { throw "Remote snapshot transfer failed." }
    & ssh @sshOptions $target "sudo rm -rf -- '$remoteStage' '$remoteArchive' '$remoteScriptPath'"

    $plainBytes = [IO.File]::ReadAllBytes($plainArchive)
    $archiveHash = Get-Sha256 $plainBytes
    if ($archiveHash -ne $remoteHash) { throw "Transferred snapshot checksum mismatch." }
    Add-Type -AssemblyName System.Security
    $encryptedBytes = [Security.Cryptography.ProtectedData]::Protect(
        $plainBytes,
        $null,
        [Security.Cryptography.DataProtectionScope]::CurrentUser
    )
    $encryptedPath = Join-Path $destinationRoot "$snapshotId.tar.gz.dpapi"
    [IO.File]::WriteAllBytes($encryptedPath, $encryptedBytes)
    $manifestPath = Join-Path $destinationRoot "$snapshotId.manifest.json"
    $manifest = [ordered]@{
        schemaVersion = 2
        snapshotId = $snapshotId
        createdAt = [DateTimeOffset]::Now.ToString("o")
        sourceServerId = [string]$server.serverId
        archiveSha256 = $archiveHash
        encryptedSha256 = Get-Sha256 $encryptedBytes
        archiveBytes = $plainBytes.Length
        encryptedBytes = $encryptedBytes.Length
        encryption = "windows-dpapi-current-user"
        includes = @("bot-runtime-sqlite-including-booklet-issuances", "shared-bot-commerce-sqlite-when-present", "notifier-history", "runtime-env-including-fingerprint-key", "telegram-egress-config", "service-state", "checksums")
        excludes = @("python", "packages", "release-code", "source-pdf", "personalized-pdf", "watermark-cache", "logs", "temporary-files")
        restoreVerified = $false
    }
    [IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json -Depth 5), [Text.UTF8Encoding]::new($false))
    Remove-Item -LiteralPath $plainArchive -Force
    [Array]::Clear($plainBytes, 0, $plainBytes.Length)
    [Array]::Clear($encryptedBytes, 0, $encryptedBytes.Length)

    & (Join-Path $PSScriptRoot "restore-vps-state.ps1") -Snapshot $encryptedPath -VerifyOnly | Out-Null
    $manifest.restoreVerified = $true
    $manifest.verifiedAt = [DateTimeOffset]::Now.ToString("o")
    [IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json -Depth 5), [Text.UTF8Encoding]::new($false))

    $snapshots = Get-ChildItem -LiteralPath $destinationRoot -Filter "*.tar.gz.dpapi" -File | Sort-Object LastWriteTime -Descending
    $keep = [Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
    foreach ($item in ($snapshots | Select-Object -First ([Math]::Max(1, $KeepDaily)))) { [void]$keep.Add($item.FullName) }
    $weekly = @{}
    foreach ($item in $snapshots) {
        $calendar = [Globalization.CultureInfo]::InvariantCulture.Calendar
        $week = $calendar.GetWeekOfYear($item.LastWriteTime, [Globalization.CalendarWeekRule]::FirstFourDayWeek, [DayOfWeek]::Monday)
        $key = "$($item.LastWriteTime.Year)-$week"
        if (-not $weekly.ContainsKey($key) -and $weekly.Count -lt [Math]::Max(0, $KeepWeekly)) {
            $weekly[$key] = $item.FullName
            [void]$keep.Add($item.FullName)
        }
    }
    foreach ($item in $snapshots) {
        if ($keep.Contains($item.FullName)) { continue }
        Remove-Item -LiteralPath $item.FullName -Force
        Remove-Item -LiteralPath ($item.FullName -replace '\.tar\.gz\.dpapi$', '.manifest.json') -Force -ErrorAction SilentlyContinue
    }

    if (-not $Quiet) {
        Write-Output "BACKUP_CREATED=$encryptedPath"
        Write-Output "RESTORE_VERIFIED=true"
        Write-Output "ARCHIVE_BYTES=$($manifest.archiveBytes)"
    }
} finally {
    & ssh @sshOptions $target "sudo rm -rf -- '$remoteStage' '$remoteArchive' '$remoteScriptPath'" 2>$null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
