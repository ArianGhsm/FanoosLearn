[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ("dent-telegram-diagnostic-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$localCheck = Join-Path $temporaryRoot "diagnose.sh"
$remoteCheck = "/tmp/dent-telegram-diagnostic-$([Guid]::NewGuid().ToString('N')).sh"

try {
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
    $check = @'
#!/usr/bin/env bash
set -u
echo "SERVICE_ACTIVE=$(systemctl is-active integrated-dent-bot.service || true)"
echo "SERVICE_ENABLED=$(systemctl is-enabled integrated-dent-bot.service || true)"
echo "SERVICE_RESTARTS=$(systemctl show integrated-dent-bot.service -p NRestarts --value)"
echo "CURRENT_RELEASE=$(basename "$(readlink -f /opt/integrated-dent/telegram/current 2>/dev/null || true)")"
main_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
echo "MAIN_PID_VALID=$([[ "$main_pid" =~ ^[1-9][0-9]*$ ]] && echo yes || echo no)"
if [[ "$main_pid" =~ ^[1-9][0-9]*$ ]]; then
  echo "PROCESS_RELEASE=$(basename "$(readlink -f "/proc/$main_pid/cwd" 2>/dev/null || true)")"
fi
echo "VENV_PYTHON=$([[ -x /opt/integrated-dent/telegram-venv/bin/python ]] && echo ready || echo missing)"
echo "QPDF=$(command -v qpdf >/dev/null 2>&1 && echo ready || echo missing)"
watermark_font="$(sed -n 's/^DENT_BOT_BOOKLET_WATERMARK_FONT=//p' /etc/integrated-dent/dent-bot.env | tail -n 1)"
echo "FONT=$([[ -n "$watermark_font" && -r "$watermark_font" ]] && echo ready || echo missing)"
echo "FINGERPRINT_KEY=$(sudo grep -q '^DENT_BOT_BOOKLET_FINGERPRINT_KEY=.' /etc/integrated-dent/dent-bot.env && echo ready || echo missing)"
echo "RECENT_ERRORS_BEGIN"
active_since="$(systemctl show integrated-dent-bot.service -p ActiveEnterTimestamp --value)"
if [[ -n "$active_since" && "$active_since" != n/a ]]; then
  journalctl -u integrated-dent-bot.service --since "$active_since" -n 25 --no-pager -p warning..alert 2>/dev/null || true
else
  journalctl -u integrated-dent-bot.service --since '-15 minutes' -n 25 --no-pager -p warning..alert 2>/dev/null || true
fi
echo "RECENT_ERRORS_END"
if systemctl is-active --quiet integrated-dent-bot.service; then
  set -a
  source /etc/integrated-dent/dent-bot.env
  set +a
  cd /opt/integrated-dent/telegram/current
  runuser -u dentbot -- /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.health || true
fi
'@
    [IO.File]::WriteAllText($localCheck, $check, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $localCheck "${target}:$remoteCheck"
    if ($LASTEXITCODE -ne 0) { throw "Telegram diagnostic transfer failed." }
    & ssh @sshOptions $target "sudo bash '$remoteCheck'"
    if ($LASTEXITCODE -ne 0) { throw "Telegram diagnostic command failed." }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteCheck'" 2>$null | Out-Null }
    if (Test-Path -LiteralPath $temporaryRoot) {
        $resolvedTemp = [IO.Path]::GetFullPath($temporaryRoot)
        $resolvedBase = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\') + '\'
        if ($resolvedTemp.StartsWith($resolvedBase, [StringComparison]::OrdinalIgnoreCase)) {
            Remove-Item -LiteralPath $resolvedTemp -Recurse -Force -ErrorAction SilentlyContinue
        }
    }
}
