[CmdletBinding()]
param(
    [string]$Snapshot = "",
    [string]$Destination = "",
    [switch]$VerifyOnly
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$backupRoot = Join-Path $root "backups\vps-state-iran"
if ([string]::IsNullOrWhiteSpace($Snapshot)) {
    $latest = Get-ChildItem -LiteralPath $backupRoot -Filter "*.tar.gz.dpapi" -File -ErrorAction SilentlyContinue |
        Sort-Object LastWriteTime -Descending | Select-Object -First 1
    if ($null -eq $latest) { throw "No VPS state backup is available." }
    $Snapshot = $latest.FullName
}
$snapshotPath = (Resolve-Path -LiteralPath $Snapshot).Path
$manifestPath = $snapshotPath -replace '\.tar\.gz\.dpapi$', '.manifest.json'
if (-not (Test-Path -LiteralPath $manifestPath -PathType Leaf)) { throw "Backup manifest is missing." }
$manifest = Get-Content -Raw -Encoding UTF8 -LiteralPath $manifestPath | ConvertFrom-Json

Add-Type -AssemblyName System.Security
$archiveBytes = [Security.Cryptography.ProtectedData]::Unprotect(
    [IO.File]::ReadAllBytes($snapshotPath),
    $null,
    [Security.Cryptography.DataProtectionScope]::CurrentUser
)
$sha = [Security.Cryptography.SHA256]::Create()
try { $archiveHash = -join ($sha.ComputeHash($archiveBytes) | ForEach-Object { $_.ToString('x2') }) }
finally { $sha.Dispose() }
if ($archiveHash -ne [string]$manifest.archiveSha256) { throw "Decrypted backup checksum does not match its manifest." }

$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-restore-" + [Guid]::NewGuid().ToString("N"))
$archivePath = Join-Path $temporaryRoot "snapshot.tar.gz"
$extractPath = Join-Path $temporaryRoot "extracted"
New-Item -ItemType Directory -Force -Path $extractPath | Out-Null
try {
    [IO.File]::WriteAllBytes($archivePath, $archiveBytes)
    & tar -xzf $archivePath -C $extractPath
    if ($LASTEXITCODE -ne 0) { throw "Encrypted backup could not be extracted." }
    $verification = & python (Join-Path $PSScriptRoot "verify_vps_backup.py") $extractPath
    if ($LASTEXITCODE -ne 0) { throw "Extracted backup validation failed." }

    if (-not $VerifyOnly) {
        if ([string]::IsNullOrWhiteSpace($Destination)) { throw "Destination is required unless VerifyOnly is used." }
        $destinationPath = [IO.Path]::GetFullPath($Destination)
        if (Test-Path -LiteralPath $destinationPath) {
            if ((Get-ChildItem -LiteralPath $destinationPath -Force | Measure-Object).Count -gt 0) {
                throw "Restore destination must be empty."
            }
        } else {
            New-Item -ItemType Directory -Force -Path $destinationPath | Out-Null
        }
        Get-ChildItem -LiteralPath $extractPath -Force | Copy-Item -Destination $destinationPath -Recurse -Force
    }
    Write-Output $verification
    Write-Output "RESTORE_VERIFIED=true"
} finally {
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
    [Array]::Clear($archiveBytes, 0, $archiveBytes.Length)
}
