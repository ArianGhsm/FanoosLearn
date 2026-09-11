[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = '.codex-local/iran-server.json',
    [ValidateSet('audit-v2', 'normalize-v2')][string]$Action = 'normalize-v2',
    [ValidateSet('telegram', 'bale')][string]$Platform = 'telegram'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw 'ConfirmTargetHost does not match the Iran server.' }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ('dent-identity-auth-v2-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$remoteScript = "/tmp/dent-identity-auth-v2-$([Guid]::NewGuid().ToString('N')).py"

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

try {
    & scp @scpOptions (Join-Path $PSScriptRoot 'identity_auth_v2_site_ops.py') "${target}:$remoteScript"
    if ($LASTEXITCODE -ne 0) { throw 'Could not transfer the identity migration helper.' }
    $envFile = if ($Platform -eq 'bale') { '/etc/integrated-dent/bale-bot.env' } else { '/etc/integrated-dent/dent-bot.env' }
    $releaseRoot = if ($Platform -eq 'bale') { '/opt/integrated-dent/bale/current' } else { '/opt/integrated-dent/telegram/current' }
    $command = "sudo bash -lc 'set -a; source $envFile; set +a; PYTHONPATH=$releaseRoot python3 $remoteScript $Action --platform $Platform'"
    & ssh @sshOptions $target $command
    if ($LASTEXITCODE -ne 0) { throw 'Identity auth v2 migration failed.' }
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteScript'" 2>$null | Out-Null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
