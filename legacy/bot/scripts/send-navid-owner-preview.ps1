[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ImagePath,
    [Parameter(Mandatory)][string]$AssignmentJsonPath,
    [string]$ServerConfig = ".codex-local/iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$image = [IO.Path]::GetFullPath((Join-Path $root $ImagePath))
$assignment = [IO.Path]::GetFullPath((Join-Path $root $AssignmentJsonPath))
if (-not (Test-Path -LiteralPath $image -PathType Leaf)) { throw "Preview image is missing." }
if (-not (Test-Path -LiteralPath $assignment -PathType Leaf)) { throw "Assignment JSON is missing." }
[void](Get-Content -Raw -Encoding UTF8 -LiteralPath $assignment | ConvertFrom-Json)

$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ("dent-navid-preview-" + [Guid]::NewGuid().ToString("N"))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$remoteBase = "/tmp/dent-navid-preview-$([Guid]::NewGuid().ToString('N'))"
$remoteImage = "$remoteBase.png"
$remoteAssignment = "$remoteBase.json"
$remoteScript = "$remoteBase.py"

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath (Join-Path $root ([string]$server.knownHostsFile)) -Destination $runtimeKnownHosts -Force
    $sshOptions = @("-i",$identityFile,"-p",[string]$server.port,"-o","BatchMode=yes","-o","StrictHostKeyChecking=yes","-o","UserKnownHostsFile=$runtimeKnownHosts")
    $scpOptions = @("-q","-i",$identityFile,"-P",[string]$server.port,"-o","BatchMode=yes","-o","StrictHostKeyChecking=yes","-o","UserKnownHostsFile=$runtimeKnownHosts")
    foreach ($transfer in @(
        @($image, $remoteImage),
        @($assignment, $remoteAssignment),
        @((Join-Path $PSScriptRoot "send-navid-owner-preview.py"), $remoteScript)
    )) {
        & scp @scpOptions $transfer[0] "${target}:$($transfer[1])"
        if ($LASTEXITCODE -ne 0) { throw "Owner preview transfer failed." }
    }
    $command = "sudo bash -lc 'set -a; source /etc/integrated-dent/dent-bot.env; export PYTHONPATH=/opt/integrated-dent/telegram/current; set +a; runuser -u dentbot -- python3 $remoteScript $remoteImage $remoteAssignment'"
    & ssh @sshOptions $target $command
    if ($LASTEXITCODE -ne 0) { throw "Owner preview send failed." }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteImage' '$remoteAssignment' '$remoteScript'" 2>$null }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
