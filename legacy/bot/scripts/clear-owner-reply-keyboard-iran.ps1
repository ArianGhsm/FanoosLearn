[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [ValidateSet("bale", "telegram")][string]$Platform = "bale",
    [string]$ServerConfig = ".codex-local/iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-keyboard-cleanup-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath (Join-Path $root ([string]$server.knownHostsFile)) -Destination $runtimeKnownHosts -Force
    $environmentFile = if ($Platform -eq "bale") {
        "/etc/integrated-dent/bale-bot.env"
    } else {
        "/etc/integrated-dent/dent-bot.env"
    }
    $serviceUser = if ($Platform -eq "bale") { "dentbale" } else { "dentbot" }
    $currentRoot = if ($Platform -eq "bale") {
        "/opt/integrated-dent/bale/current"
    } else {
        "/opt/integrated-dent/telegram/current"
    }
    $remoteScript = @"
#!/usr/bin/env bash
set -euo pipefail
set -a
source '$environmentFile'
set +a
runuser -u '$serviceUser' -- bash -c 'cd "$currentRoot" && python3 -m dent_bot.clear_reply_keyboard "$Platform"'
"@
    $encoded = [Convert]::ToBase64String([Text.UTF8Encoding]::new($false).GetBytes($remoteScript))
    $sshOptions = @(
        "-i", $identityFile,
        "-p", [string]$server.port,
        "-o", "BatchMode=yes",
        "-o", "StrictHostKeyChecking=yes",
        "-o", "UserKnownHostsFile=$runtimeKnownHosts"
    )
    & ssh @sshOptions $target "echo '$encoded' | base64 -d | sudo bash"
    if ($LASTEXITCODE -ne 0) { throw "The bot did not confirm reply-keyboard cleanup." }
}
finally {
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
