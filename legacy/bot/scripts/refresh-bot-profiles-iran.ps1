[CmdletBinding()]
param([string]$ServerConfig = ".codex-local/iran-server.json")

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ("dent-profile-refresh-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$remoteScript = "/tmp/dent-profile-refresh-$([Guid]::NewGuid().ToString('N')).py"
try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath (Join-Path $root ([string]$server.knownHostsFile)) -Destination $runtimeKnownHosts -Force
    $sshOptions = @("-i",$identityFile,"-p",[string]$server.port,"-o","BatchMode=yes","-o","StrictHostKeyChecking=yes","-o","UserKnownHostsFile=$runtimeKnownHosts")
    $scpOptions = @("-q","-i",$identityFile,"-P",[string]$server.port,"-o","BatchMode=yes","-o","StrictHostKeyChecking=yes","-o","UserKnownHostsFile=$runtimeKnownHosts")
    & scp @scpOptions (Join-Path $PSScriptRoot "refresh-bot-profiles-iran.py") "${target}:$remoteScript"
    if ($LASTEXITCODE -ne 0) { throw "Profile refresh transfer failed." }
    $telegram = "sudo bash -lc 'set -a; source /etc/integrated-dent/dent-bot.env; export PYTHONPATH=/opt/integrated-dent/telegram/current; set +a; runuser -u dentbot --preserve-environment -- python3 $remoteScript telegram'"
    & ssh @sshOptions $target $telegram
    if ($LASTEXITCODE -ne 0) { throw "Telegram profile refresh failed." }
    $bale = "sudo bash -lc 'set -a; source /etc/integrated-dent/bale-bot.env; export PYTHONPATH=/opt/integrated-dent/bale/current; set +a; runuser -u dentbale --preserve-environment -- python3 $remoteScript bale'"
    & ssh @sshOptions $target $bale
    if ($LASTEXITCODE -ne 0) { throw "Bale profile refresh failed." }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteScript'" 2>$null }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
