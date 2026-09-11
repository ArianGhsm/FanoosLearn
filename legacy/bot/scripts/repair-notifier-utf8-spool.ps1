[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidatePattern('^[A-Za-z0-9_.-]{1,120}$')][string]$EventId,
    [Parameter(Mandatory)][string]$ReplacementSummary,
    [string]$ServerConfig = '.codex-local/iran-server.json'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
$sshUser = if ($server.user) { $server.user } else { $server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$stateRoot = '/var/lib/integrated-dent/deploy-notifier'
$remoteLookup = "for d in pending delivered deferred; do p=$stateRoot/`$d/$EventId.json; if test -f `"`$p`"; then printf '%s' `"`$p`"; exit 0; fi; done; exit 4"
$remotePath = (& ssh -i $identityFile -p $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes $target $remoteLookup).Trim()
if ($LASTEXITCODE -ne 0 -or $remotePath -notmatch '^/var/lib/integrated-dent/deploy-notifier/(pending|delivered|deferred)/[A-Za-z0-9_.-]+\.json$') {
    throw 'The requested notifier spool record was not found in the expected boundary.'
}

$backupRoot = Join-Path $root ('.codex-local\utf8-spool-repair\' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
New-Item -ItemType Directory -Force -Path $backupRoot | Out-Null
$originalPath = Join-Path $backupRoot "$EventId.json"
$repairedPath = Join-Path $backupRoot "$EventId.repaired.json"
$remoteTemporary = "$remotePath.repair-$([Guid]::NewGuid().ToString('N'))"

try {
    & scp -q -i $identityFile -P $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes `
        "${target}:$remotePath" $originalPath
    if ($LASTEXITCODE -ne 0) { throw 'The original spool record could not be copied to the laptop.' }

    Add-Type -AssemblyName System.Security
    $protected = [Security.Cryptography.ProtectedData]::Protect(
        [IO.File]::ReadAllBytes($originalPath),
        $null,
        [Security.Cryptography.DataProtectionScope]::CurrentUser
    )
    [IO.File]::WriteAllBytes("$originalPath.dpapi", $protected)

    $record = Get-Content -Raw -Encoding UTF8 -LiteralPath $originalPath | ConvertFrom-Json
    if ($null -eq $record.event -or [string]$record.event.event_id -ne $EventId) {
        throw 'The downloaded spool record identity did not match the requested event.'
    }
    $record.event.summary = $ReplacementSummary
    $json = $record | ConvertTo-Json -Depth 20 -Compress
    $utf8 = [Text.UTF8Encoding]::new($false, $true)
    [IO.File]::WriteAllText($repairedPath, $json, $utf8)
    [void](Get-Content -Raw -Encoding UTF8 -LiteralPath $repairedPath | ConvertFrom-Json)

    & scp -q -i $identityFile -P $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes `
        $repairedPath "${target}:$remoteTemporary"
    if ($LASTEXITCODE -ne 0) { throw 'The repaired spool record could not be transferred.' }
    $activate = "set -e; test -f '$remotePath'; test -f '$remoteTemporary'; chmod --reference='$remotePath' '$remoteTemporary'; chown --reference='$remotePath' '$remoteTemporary'; mv -f -- '$remoteTemporary' '$remotePath'"
    & ssh -i $identityFile -p $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes $target $activate
    if ($LASTEXITCODE -ne 0) { throw 'The repaired spool record could not be activated atomically.' }

    Write-Output 'ENCRYPTED_LAPTOP_BACKUP=true'
    Write-Output 'REPAIRED_RECORDS=1'
}
finally {
    Remove-Item -LiteralPath $originalPath, $repairedPath -Force -ErrorAction SilentlyContinue
    & ssh -i $identityFile -p $server.port -o BatchMode=yes -o StrictHostKeyChecking=yes $target "rm -f -- '$remoteTemporary'" 2>$null
}
