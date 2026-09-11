[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$AuditDirectory
)

$ErrorActionPreference = "Stop"
$resolved = (Resolve-Path -LiteralPath $AuditDirectory).Path
$tempRoot = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
$leaf = Split-Path -Leaf $resolved
if (-not $resolved.StartsWith($tempRoot, [StringComparison]::OrdinalIgnoreCase) -or
    -not $leaf.StartsWith('integrated-dent-restore-audit-', [StringComparison]::Ordinal)) {
    throw "Refusing to remove a directory outside the dedicated restore-audit path."
}

Remove-Item -LiteralPath $resolved -Recurse -Force
Write-Output "PLAINTEXT_RESTORE_CLEANED=true"
