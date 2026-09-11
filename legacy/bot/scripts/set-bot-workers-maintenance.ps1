[CmdletBinding()]
param(
    [Parameter(Mandatory)][ValidateSet('Pause', 'Resume')][string]$Mode,
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = '.codex-local/iran-server.json'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw 'ConfirmTargetHost does not match the Iran server.' }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ('dent-worker-maintenance-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$localScript = Join-Path $temporaryRoot 'maintenance.sh'
$remoteScript = "/tmp/integrated-dent-worker-maintenance-$([Guid]::NewGuid().ToString('N')).sh"

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath ([IO.Path]::GetFullPath((Join-Path $root ([string]$server.knownHostsFile)))) -Destination $runtimeKnownHosts
    $sshOptions = @(
        '-i', $identityFile, '-p', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$runtimeKnownHosts", '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1'
    )
    $scpOptions = @(
        '-q', '-i', $identityFile, '-P', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$runtimeKnownHosts", '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1'
    )
    $action = if ($Mode -eq 'Pause') { 'stop' } else { 'start' }
    $expected = if ($Mode -eq 'Pause') { 'inactive' } else { 'active' }
    $body = @'
#!/usr/bin/env bash
set -euo pipefail
services=(integrated-dent-bot.service integrated-dent-bale-bot.service)
emit_state() {
  local phase="$1"
  for service in "${services[@]}"; do
    active="$(systemctl show "$service" -p ActiveState --value)"
    sub="$(systemctl show "$service" -p SubState --value)"
    pid="$(systemctl show "$service" -p MainPID --value)"
    restarts="$(systemctl show "$service" -p NRestarts --value)"
    cwd=""
    if test "$pid" != "0"; then cwd="$(readlink -f "/proc/$pid/cwd")"; fi
    printf '%s service=%s active=%s sub=%s pid=%s restarts=%s cwd=%s\n' "$phase" "$service" "$active" "$sub" "$pid" "$restarts" "$cwd"
  done
}
emit_state PRE
sudo systemctl __ACTION__ "${services[@]}"
for service in "${services[@]}"; do
  test "$(systemctl show "$service" -p ActiveState --value)" = "__EXPECTED__"
done
emit_state POST
'@
    $body = $body.Replace('__ACTION__', $action).Replace('__EXPECTED__', $expected)
    [IO.File]::WriteAllText($localScript, $body, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $localScript "${target}:$remoteScript"
    if ($LASTEXITCODE -ne 0) { throw 'Worker maintenance script transfer failed.' }
    & ssh @sshOptions $target "sudo bash '$remoteScript'"
    if ($LASTEXITCODE -ne 0) { throw "Worker maintenance action $Mode failed." }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteScript'" 2>$null | Out-Null }
    if (Test-Path -LiteralPath $temporaryRoot) { Remove-Item -LiteralPath $temporaryRoot -Recurse -Force }
}
