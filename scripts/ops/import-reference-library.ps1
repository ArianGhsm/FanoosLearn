param(
    [string]$Sources = '.local/reference-library/sources.json',
    [switch]$Apply
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Set-Location -LiteralPath $repoRoot

function Quote-RemoteArgument([string]$Value) {
    return "'" + $Value.Replace("'", "'\''") + "'"
}

function Invoke-FanoosSsh([string]$Command) {
    $result = & $script:sshPath @script:sshOptions $script:sshTarget $Command 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw 'The approved FANOOS SSH command failed.'
    }
    return ($result -join [Environment]::NewLine).Trim()
}

function Copy-ToFanoosStage([string]$LocalPath, [string]$RemoteFile) {
    $destination = "$($script:sshTarget):$($script:remoteStage)/$RemoteFile"
    $result = & $script:scpPath @script:sshOptions $LocalPath $destination 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Could not transfer staged file $RemoteFile."
    }
    $remote = Quote-RemoteArgument "$($script:remoteStage)/$RemoteFile"
    Invoke-FanoosSsh "sudo -n chown fanoosupd:fanoosrt -- $remote && sudo -n chmod 0640 -- $remote" | Out-Null
}

function Invoke-ReferenceDryRun {
    $stage = Quote-RemoteArgument $script:remoteStage
    $command = "sudo -n -u fanoosweb env FANOOS_CONFIG_FILE=/etc/fanoos/platform-config.php php /srv/fanoos/current/scripts/ops/import-reference-library.php --dry-run --staging=$stage"
    $raw = Invoke-FanoosSsh $command
    try {
        return $raw | ConvertFrom-Json
    } catch {
        throw 'The FANOOS dry-run did not return valid JSON.'
    }
}

function Remove-ReferenceStage {
    if ($script:remoteStage -notmatch '^/srv/fanoos/staging/reference-import/[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$') {
        throw 'The generated FANOOS staging path failed its safety check.'
    }
    $stage = Quote-RemoteArgument $script:remoteStage
    Invoke-FanoosSsh "sudo -n -u fanoosupd rm -rf -- $stage" | Out-Null
}

$sshTarget = $env:FANOOS_SSH_TARGET
$knownHosts = $env:FANOOS_KNOWN_HOSTS
if ([string]::IsNullOrWhiteSpace($sshTarget) -or [string]::IsNullOrWhiteSpace($knownHosts) -or
    -not (Test-Path -LiteralPath $knownHosts -PathType Leaf)) {
    throw 'Set FANOOS_SSH_TARGET and FANOOS_KNOWN_HOSTS for the pinned FANOOS host.'
}
$sshCommand = Get-Command ssh.exe -ErrorAction Stop
$scpCommand = Get-Command scp.exe -ErrorAction Stop
$sshPath = $sshCommand.Source
$scpPath = $scpCommand.Source
$sshOptions = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes', '-o', "UserKnownHostsFile=$knownHosts")
if (-not [string]::IsNullOrWhiteSpace($env:FANOOS_SSH_KEY)) {
    if (-not (Test-Path -LiteralPath $env:FANOOS_SSH_KEY -PathType Leaf)) {
        throw 'FANOOS_SSH_KEY does not name a readable key file.'
    }
    $sshOptions += @('-i', $env:FANOOS_SSH_KEY)
}

$sourcePath = Join-Path $repoRoot $Sources
if (-not (Test-Path -LiteralPath $sourcePath -PathType Leaf)) {
    throw 'The private reference source map is missing.'
}
$sourceMap = Get-Content -LiteralPath $sourcePath -Raw | ConvertFrom-Json
if ($sourceMap.format -ne 'fanoos.reference-library-sources.v1' -or $sourceMap.editions -isnot [array]) {
    throw 'The private reference source map format is invalid.'
}

$catalog = Get-Content -LiteralPath (Join-Path $repoRoot 'data/bank/catalog.json') -Raw | ConvertFrom-Json
$officialKeys = @(
    foreach ($reference in $catalog.references) {
        foreach ($edition in $reference.editions) {
            "$($reference.key)@$($edition.key)"
        }
    }
)
$sourcesByEdition = @{}
$manifestEntries = [System.Collections.Generic.List[object]]::new()
$batch = (Get-Date).ToUniversalTime().ToString("yyyyMMdd'T'HHmmss'Z'") + '-' + [Guid]::NewGuid().ToString('N').Substring(0, 8)
$remoteStage = "/srv/fanoos/staging/reference-import/$batch"
$script:sshTarget = $sshTarget
$script:sshPath = $sshPath
$script:scpPath = $scpPath
$script:sshOptions = $sshOptions
$script:remoteStage = $remoteStage
$localManifest = Join-Path ([IO.Path]::GetTempPath()) "fanoos-reference-import-$batch.json"
$remoteStageCreated = $false
$transferredAnyPdfs = $false

try {
    foreach ($source in $sourceMap.editions) {
        $key = [string]$source.edition_key
        $kind = [string]$source.kind
        if ($key -notin $officialKeys -or $sourcesByEdition.ContainsKey($key) -or
            $key -notmatch '^[a-z0-9@-]+$' -or $kind -notin @('local', 'drive_mount')) {
            throw 'The private reference source map has an unknown or duplicate edition.'
        }
        $file = "$key.pdf"
        if ($kind -eq 'local') {
            $resolved = Resolve-Path -LiteralPath ([string]$source.path)
            $localPath = $resolved.Path
            if (-not (Test-Path -LiteralPath $localPath -PathType Leaf)) {
                throw "A local reference source is missing for $key."
            }
            $stream = [IO.File]::OpenRead($localPath)
            try {
                $prefix = New-Object byte[] 5
                $read = $stream.Read($prefix, 0, $prefix.Length)
                $signature = [Text.Encoding]::ASCII.GetString($prefix, 0, $read)
            } finally {
                $stream.Dispose()
            }
            if ($signature -ne '%PDF-') {
                throw "A local reference source is not a PDF for $key."
            }
            $fileInfo = Get-Item -LiteralPath $localPath
            $digest = (Get-FileHash -LiteralPath $localPath -Algorithm SHA256).Hash.ToLowerInvariant()
            $bytes = [long]$fileInfo.Length
            $sourcesByEdition[$key] = @{ kind = 'local'; path = $localPath; file = $file }
        } else {
            $drivePath = [string]$source.path
            if ([string]::IsNullOrWhiteSpace($drivePath)) {
                throw "A mounted Drive path is missing for $key."
            }
            $quotedDrivePath = Quote-RemoteArgument $drivePath
            $sourceCommand = "sudo -n -u fanoosweb env FANOOS_CONFIG_FILE=/etc/fanoos/platform-config.php php /srv/fanoos/current/scripts/ops/reference-library-source-info.php --drive-path=$quotedDrivePath"
            $remoteInfo = Invoke-FanoosSsh $sourceCommand | ConvertFrom-Json
            $bytes = [long]$remoteInfo.bytes
            $digest = [string]$remoteInfo.sha256
            $sourcesByEdition[$key] = @{ kind = 'drive_mount'; path = $drivePath; file = $file }
        }
        if ($bytes -lt 1 -or $digest -notmatch '^[a-f0-9]{64}$') {
            throw "A reference source size or checksum is invalid for $key."
        }
        $entry = [ordered]@{ edition_key = $key; file = $file; bytes = $bytes; sha256 = $digest }
        if ($kind -eq 'drive_mount') {
            $entry.drive_path = [string]$source.path
        }
        $manifestEntries.Add($entry)
    }

    $manifest = [ordered]@{ format = 'fanoos.reference-library.v1'; editions = $manifestEntries }
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($localManifest, (ConvertTo-Json -InputObject $manifest -Depth 8), $utf8)

    $stage = Quote-RemoteArgument $remoteStage
    Invoke-FanoosSsh "sudo -n -u fanoosupd install -d -m 2770 -g fanoosrt -- $stage" | Out-Null
    $remoteStageCreated = $true
    Copy-ToFanoosStage $localManifest 'manifest.json'

    $dryRun = Invoke-ReferenceDryRun
    if ($dryRun.mode -ne 'dry_run') {
        throw 'FANOOS did not confirm reference-library dry-run mode.'
    }
    if (@($dryRun.missing_source_editions).Count -gt 0) {
        $missing = @($dryRun.missing_source_editions) -join ', '
        Write-Output ($dryRun | ConvertTo-Json -Depth 8)
        throw "FANOOS is missing verified PDF sources for: $missing"
    }

    if ($Apply) {
        $uploads = @($dryRun.items | Where-Object { $_.action -in @('upload_new_resource', 'upload_new_version') })
        foreach ($item in $uploads) {
            $source = $sourcesByEdition[[string]$item.edition_key]
            if ($null -eq $source) {
                throw "No staged source is mapped for $($item.edition_key)."
            }
            if ($source.kind -eq 'local') {
                Write-Host "Transferring $($item.edition_key) to FANOOS staging."
                Copy-ToFanoosStage ([string]$source.path) ([string]$source.file)
                $transferredAnyPdfs = $true
            }
        }

        $dryRun = Invoke-ReferenceDryRun
        if (@($dryRun.missing_source_editions).Count -gt 0 -or
            @($dryRun.items | Where-Object { $_.action -in @('upload_new_resource', 'upload_new_version') -and -not $_.source_available }).Count -gt 0) {
            Write-Output ($dryRun | ConvertTo-Json -Depth 8)
            throw 'FANOOS dry-run still has unresolved or unstaged reference PDFs.'
        }

        $actionsToApply = @($dryRun.items | Where-Object { $_.action -in @('publish_existing_reference_pdf', 'reuse_existing_private_pdf', 'upload_new_resource', 'upload_new_version') })
        if ($actionsToApply.Count -gt 0) {
            $backupCommand = 'sudo -n -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php /srv/fanoos/current/scripts/ops/backup.php'
            $backupOutput = Invoke-FanoosSsh $backupCommand
            $backupPath = ($backupOutput -split "`r?`n" | Where-Object { $_ -match '^/var/backups/fanoos/[A-Za-z0-9TZ-]+$' } | Select-Object -Last 1)
            if ([string]::IsNullOrWhiteSpace($backupPath)) {
                throw 'FANOOS did not return a verified backup directory.'
            }
            $backupArg = Quote-RemoteArgument $backupPath
            Invoke-FanoosSsh "sudo -n -u fanoosupd env FANOOS_CONFIG_FILE=/etc/fanoos/updater-config.php php /srv/fanoos/current/scripts/ops/verify-backup.php $backupArg" | Out-Null

            $stageArg = Quote-RemoteArgument $remoteStage
            $applyCommand = "sudo -n -u fanoosweb env FANOOS_CONFIG_FILE=/etc/fanoos/platform-config.php php /srv/fanoos/current/scripts/ops/import-reference-library.php --apply --verified-backup=$backupArg --staging=$stageArg"
            $applyOutput = Invoke-FanoosSsh $applyCommand | ConvertFrom-Json
            if ($applyOutput.mode -ne 'applied') {
                throw 'FANOOS did not confirm reference-library apply mode.'
            }
        }

        $inventoryCommand = 'sudo -n -u fanoosweb env FANOOS_CONFIG_FILE=/etc/fanoos/platform-config.php php /srv/fanoos/current/scripts/ops/reference-library-inventory.php'
        $inventory = Invoke-FanoosSsh $inventoryCommand | ConvertFrom-Json
        $finalByKey = @{}
        foreach ($row in $inventory.pdf_resources) {
            $key = [string]$row.edition_key
            if ($key -in $officialKeys) {
                if ($finalByKey.ContainsKey($key)) {
                    throw "FANOOS inventory has duplicate resources for $key."
                }
                if (-not $row.ready) {
                    throw "FANOOS inventory did not confirm a published, approved private PDF for $key."
                }
                $finalByKey[$key] = $row
            }
        }
        $missingAfterImport = @($officialKeys | Where-Object { -not $finalByKey.ContainsKey($_) })
        if ($missingAfterImport.Count -gt 0) {
            throw ('FANOOS post-import inventory is incomplete: ' + ($missingAfterImport -join ', '))
        }
        Remove-ReferenceStage
        $remoteStageCreated = $false
        Write-Output ($inventory | ConvertTo-Json -Depth 8)
    } else {
        Write-Output ($dryRun | ConvertTo-Json -Depth 8)
        Remove-ReferenceStage
        $remoteStageCreated = $false
    }
} finally {
    if ($remoteStageCreated -and -not $transferredAnyPdfs) {
        try {
            Remove-ReferenceStage
        } catch {
            Write-Warning 'A temporary FANOOS reference-import manifest directory needs operator cleanup.'
        }
    }
    if (Test-Path -LiteralPath $localManifest -PathType Leaf) {
        Remove-Item -LiteralPath $localManifest -Force
    }
}
