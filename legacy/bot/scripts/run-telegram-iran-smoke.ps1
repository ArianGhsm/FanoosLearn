[CmdletBinding()]
param([string]$ServerConfig = ".codex-local/iran-server.json")

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-telegram-smoke-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$remoteScript = "/tmp/integrated-dent-telegram-smoke-$([Guid]::NewGuid().ToString('N')).py"
try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath (Join-Path $root ([string]$server.knownHostsFile)) -Destination $runtimeKnownHosts -Force
    $sshOptions = @("-i",$identityFile,"-p",[string]$server.port,"-o","BatchMode=yes","-o","StrictHostKeyChecking=yes","-o","UserKnownHostsFile=$runtimeKnownHosts")
    $scpOptions = @("-q","-i",$identityFile,"-P",[string]$server.port,"-o","BatchMode=yes","-o","StrictHostKeyChecking=yes","-o","UserKnownHostsFile=$runtimeKnownHosts")
    & scp @scpOptions (Join-Path $PSScriptRoot "send-telegram-iran-smoke.py") "${target}:$remoteScript"
    if ($LASTEXITCODE -ne 0) { throw "Telegram smoke transfer failed." }
    $command = "sudo bash -lc 'set -a; source /etc/integrated-dent/dent-bot.env; export PYTHONPATH=/opt/integrated-dent/telegram/current; set +a; runuser -u dentbot -- python3 $remoteScript'"
    & ssh @sshOptions $target $command
    if ($LASTEXITCODE -ne 0) { throw "Live Telegram proxy smoke failed." }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteScript'" 2>$null }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
