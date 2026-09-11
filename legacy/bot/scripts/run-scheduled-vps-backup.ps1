$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$logRoot = Join-Path $root ".codex-local\backup-status"
New-Item -ItemType Directory -Force -Path $logRoot | Out-Null
$statusPath = Join-Path $logRoot "latest.json"
$jobs = @(
    [ordered]@{
        role = "iran"
        required = $true
        serverConfig = ".codex-local/iran-server.json"
        backupRoot = "backups/vps-state-iran"
    }
)

$results = [ordered]@{}
foreach ($job in $jobs) {
    try {
        & (Join-Path $PSScriptRoot "backup-vps-state.ps1") `
            -ServerConfig $job.serverConfig -BackupRoot $job.backupRoot -Quiet
        $latest = Get-ChildItem -LiteralPath (Join-Path $root $job.backupRoot) -Filter "*.manifest.json" -File |
            Sort-Object LastWriteTime -Descending | Select-Object -First 1
        if ($null -eq $latest) { throw "Scheduled backup produced no manifest." }
        $manifest = Get-Content -Raw -Encoding UTF8 -LiteralPath $latest.FullName | ConvertFrom-Json
        if (-not [bool]$manifest.restoreVerified) { throw "Scheduled backup was not restore-verified." }
        $results[$job.role] = [ordered]@{
            success = $true
            required = [bool]$job.required
            snapshotId = [string]$manifest.snapshotId
            restoreVerified = $true
        }
    } catch {
        $results[$job.role] = [ordered]@{
            success = $false
            required = [bool]$job.required
            errorType = $_.Exception.GetType().Name
        }
    }
}

$requiredFailed = @($results.GetEnumerator() | Where-Object { $_.Value.required -and -not $_.Value.success }).Count -gt 0
$anyFailed = @($results.GetEnumerator() | Where-Object { -not $_.Value.success }).Count -gt 0
$status = [ordered]@{
    schemaVersion = 2
    success = -not $requiredFailed
    degraded = $anyFailed
    finishedAt = [DateTimeOffset]::Now.ToString("o")
    servers = $results
}
[IO.File]::WriteAllText($statusPath, ($status | ConvertTo-Json -Depth 5 -Compress), [Text.UTF8Encoding]::new($false))
if ($requiredFailed) { exit 1 }
