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
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-forensic-" + [Guid]::NewGuid().ToString("N"))
$knownHosts = Join-Path $temporaryRoot "known_hosts"
$remotePrefix = "/tmp/integrated-dent-forensic-$([Guid]::NewGuid().ToString('N'))"

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath (Join-Path $root ([string]$server.knownHostsFile)) -Destination $knownHosts -Force
    $common = @(
        "-i", $identityFile, "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1", "-o", "UserKnownHostsFile=$knownHosts"
    )
    $scp = @("-q", "-P", [string]$server.port) + $common
    $ssh = @("-p", [string]$server.port) + $common
    $target = "$sshUser@$($server.host)"
    $files = @(
        @((Join-Path $root "scripts\install-forensic-runtime.sh"), "$remotePrefix.install.sh"),
        @((Join-Path $root "requirements-forensic.txt"), "$remotePrefix.forensic.txt"),
        @((Join-Path $root "requirements-bot-pdf.txt"), "$remotePrefix.pdf.txt")
    )
    foreach ($file in $files) {
        & scp @scp $file[0] "${target}:$($file[1])"
        if ($LASTEXITCODE -ne 0) { throw "Forensic runtime transfer failed." }
    }
    $remote = "chmod 700 '$remotePrefix.install.sh' && '$remotePrefix.install.sh' '$remotePrefix.forensic.txt' '$remotePrefix.pdf.txt'; rc=`$?; rm -f '$remotePrefix.install.sh' '$remotePrefix.forensic.txt' '$remotePrefix.pdf.txt'; exit `$rc"
    & ssh @ssh $target $remote
    if ($LASTEXITCODE -ne 0) { throw "Forensic runtime installation failed." }
}
finally {
    if (Test-Path -LiteralPath $temporaryRoot) { Remove-Item -LiteralPath $temporaryRoot -Recurse -Force }
}
