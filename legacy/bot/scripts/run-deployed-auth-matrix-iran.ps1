[CmdletBinding()]
param(
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
$temporaryBase = [IO.Path]::GetFullPath([string]$env:TEMP)
$temporaryRoot = [IO.Path]::GetFullPath((Join-Path $temporaryBase ('dent-auth-matrix-' + [Guid]::NewGuid().ToString('N'))))
if (-not $temporaryRoot.StartsWith($temporaryBase + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Temporary auth-matrix path escaped the system temporary directory.'
}
$knownHosts = Join-Path $temporaryRoot 'known_hosts'
$remoteProbe = '/tmp/probe-bot-auth-matrix.py'
$sshOptions = @(
    '-i', $identityFile, '-p', [string]$server.port,
    '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
    '-o', "UserKnownHostsFile=$knownHosts"
)
$scpOptions = @(
    '-q', '-i', $identityFile, '-P', [string]$server.port,
    '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
    '-o', "UserKnownHostsFile=$knownHosts"
)

New-Item -ItemType Directory -Path $temporaryRoot | Out-Null
try {
    Copy-Item -LiteralPath ([IO.Path]::GetFullPath((Join-Path $root ([string]$server.knownHostsFile)))) -Destination $knownHosts
    & scp @scpOptions (Join-Path $PSScriptRoot 'probe_bot_auth_matrix.py') "${target}:$remoteProbe"
    if ($LASTEXITCODE -ne 0) { throw 'Auth matrix transfer failed.' }
    $command = "PYTHONPATH=/opt/integrated-dent/telegram/current python3 '$remoteProbe'" +
        " && PYTHONPATH=/opt/integrated-dent/bale/current python3 '$remoteProbe'"
    & ssh @sshOptions $target $command
    if ($LASTEXITCODE -ne 0) { throw 'A deployed auth matrix failed.' }
}
finally {
    & ssh @sshOptions $target "rm -f -- '$remoteProbe'" 2>$null | Out-Null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
