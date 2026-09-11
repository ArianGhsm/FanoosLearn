[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$serverConfigPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $root $ServerConfig }
$serverConfigPath = (Resolve-Path -LiteralPath $serverConfigPath).Path
$serverStateRoot = if ((Split-Path $serverConfigPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverConfigPath -Parent) -Leaf) -eq '.codex-local') { Split-Path (Split-Path $serverConfigPath -Parent) -Parent } else { $root }
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath $serverConfigPath | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) {
    throw "ConfirmTargetHost does not match the configured Iran server."
}
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$knownHostsTemp = (New-TemporaryFile).FullName

try {
    $knownHostsSource = [string]$server.knownHostsFile
    if (-not [IO.Path]::IsPathRooted($knownHostsSource)) { $knownHostsSource = Join-Path $serverStateRoot $knownHostsSource }
    $knownHostsSource = [IO.Path]::GetFullPath($knownHostsSource)
    Copy-Item -LiteralPath $knownHostsSource -Destination $knownHostsTemp -Force
    $sshOptions = @(
        "-i", $identityFile, "-p", [string]$server.port,
        "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1",
        "-o", "UserKnownHostsFile=$knownHostsTemp"
    )
    $probe = @'
set -euo pipefail
for unit in integrated-dent-bot.service integrated-dent-bale-bot.service; do
  systemctl is-active --quiet "$unit"
  pid="$(systemctl show "$unit" -p MainPID --value)"
  test "$pid" -gt 0
  echo "$unit|pid=$pid|cwd=$(readlink -f "/proc/$pid/cwd")|restarts=$(systemctl show "$unit" -p NRestarts --value)"
done
db=/var/lib/integrated-dent/shared/payment-offers.sqlite3
sqlite3 "$db" 'PRAGMA integrity_check;' | grep -qx ok
for table in payment_offers term_access_policies term_access_entitlements term_subscription_checkouts term_access_audit term_subscription_renewal_notices; do
  test "$(sqlite3 "$db" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$table';")" = 1
done
sqlite3 -separator '|' "$db" "SELECT 'POLICY',term,mode,enabled,monthly_price_rials,active_from_jalali,renewal_reminders_enabled FROM term_access_policies WHERE term=7;"
echo "ENTITLEMENTS=$(sqlite3 "$db" 'SELECT COUNT(*) FROM term_access_entitlements;')"
echo "COMPLIMENTARY=$(sqlite3 "$db" "SELECT COUNT(*) FROM term_access_entitlements WHERE access_type='complimentary';")"
echo TERM_SUBSCRIPTION_RUNTIME_OK
'@
    $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($probe))
    $output = @(& ssh @sshOptions $target "echo '$encoded' | base64 -d | sudo bash" 2>&1)
    $output | ForEach-Object { Write-Output $_ }
    if ($LASTEXITCODE -ne 0 -or $output -notcontains "TERM_SUBSCRIPTION_RUNTIME_OK") {
        throw "The live term-subscription runtime probe failed."
    }
}
finally {
    Remove-Item -LiteralPath $knownHostsTemp -Force -ErrorAction SilentlyContinue
}
