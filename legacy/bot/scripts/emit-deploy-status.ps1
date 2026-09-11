[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Service,
    [Parameter(Mandatory)][ValidateSet("started", "succeeded", "failed", "rolled_back")][string]$Status,
    [Parameter(Mandatory)][string]$EventId,
    [string]$Environment = "production",
    [string]$Version = "",
    [string]$Summary = "",
    [string]$Actor = "deployment-script",
    [string]$ServerConfig = ".codex-local/iran-server.json"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$serverConfigPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $root $ServerConfig }
$serverConfigPath = (Resolve-Path -LiteralPath $serverConfigPath).Path
$serverStateRoot = if ((Split-Path $serverConfigPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverConfigPath -Parent) -Leaf) -eq '.codex-local') { Split-Path (Split-Path $serverConfigPath -Parent) -Parent } else { $root }
$server = Get-Content -LiteralPath $serverConfigPath -Raw | ConvertFrom-Json
$sshUser = if ($server.user) { $server.user } else { $server.bootstrapUser }
if (-not $sshUser) { throw "SSH user is missing from server config." }
$payload = @{
    service = $Service; status = $Status; event_id = $EventId; environment = $Environment
    version = $Version; summary = $Summary; actor = $Actor
} | ConvertTo-Json -Compress
$utf8 = [Text.UTF8Encoding]::new($false, $true)
$payloadBytes = $utf8.GetBytes($payload)
$payloadBase64 = [Convert]::ToBase64String($payloadBytes)
if ($utf8.GetString($payloadBytes) -cne $payload) {
    throw "The deployment event failed its UTF-8 round-trip check."
}

# Avoid the PowerShell native pipeline and Windows OpenSSH stdin entirely.
# SCP transfers an ASCII Base64 file byte-for-byte into the notifier's private
# state directory; the remote process decodes it and removes it with a trap.
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$sshOptions = @("-i", $identityFile, "-p", [string]$server.port, "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes", "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1")
$scpOptions = @("-q", "-i", $identityFile, "-P", [string]$server.port, "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes", "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1")
$runtimeKnownHosts = ""
if ($server.knownHostsFile) {
    $knownHosts = [string]$server.knownHostsFile
    if (-not [IO.Path]::IsPathRooted($knownHosts)) { $knownHosts = Join-Path $serverStateRoot $knownHosts }
    $knownHosts = [IO.Path]::GetFullPath($knownHosts)
    if (-not (Test-Path -LiteralPath $knownHosts -PathType Leaf)) { throw "The configured known-hosts file is missing." }
    $runtimeKnownHosts = Join-Path $env:TEMP ("deploy-notifier-known-hosts-" + [Guid]::NewGuid().ToString("N"))
    Copy-Item -LiteralPath $knownHosts -Destination $runtimeKnownHosts -Force
    $sshOptions += @("-o", "UserKnownHostsFile=$runtimeKnownHosts")
    $scpOptions += @("-o", "UserKnownHostsFile=$runtimeKnownHosts")
}
$temporaryPath = [IO.Path]::GetTempFileName()
$remotePath = "/var/lib/integrated-dent/deploy-notifier/.incoming-$([Guid]::NewGuid().ToString('N')).b64"
$remoteUploaded = $false
try {
    [IO.File]::WriteAllText($temporaryPath, $payloadBase64, [Text.ASCIIEncoding]::new())
    & scp @scpOptions $temporaryPath "${sshUser}@$($server.host):$remotePath"
    if ($LASTEXITCODE -ne 0) { throw "The UTF-8 deployment payload could not be transferred." }
    $remoteUploaded = $true

    $remoteCommand = "sudo bash -c 'set -euo pipefail; chmod 600 $remotePath; set -a; . /etc/integrated-dent/deploy-notifier.env; set +a; cd /opt/integrated-dent/notifier/current; base64 --decode < $remotePath | runuser -u dentops --preserve-environment -- python3 -m deploy_notifier.cli publish-json'"
    $output = & ssh @sshOptions "$sshUser@$($server.host)" $remoteCommand
    if ($LASTEXITCODE -ne 0) { throw "The deployment event was not queued on the VPS." }
    if ($output) { Write-Output ($output -join "`n") }
}
finally {
    if ($remoteUploaded) {
        & ssh @sshOptions "$sshUser@$($server.host)" "sudo rm -f -- '$remotePath'" 2>$null
    }
    Remove-Item -LiteralPath $temporaryPath -Force -ErrorAction SilentlyContinue
    if ($runtimeKnownHosts) { Remove-Item -LiteralPath $runtimeKnownHosts -Force -ErrorAction SilentlyContinue }
}
