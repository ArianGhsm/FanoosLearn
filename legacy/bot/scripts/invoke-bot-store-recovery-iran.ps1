[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Candidate,
    [Parameter(Mandatory)][ValidatePattern('^[a-f0-9]{64}$')][string]$ExpectedActiveSha256,
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = '.codex-local/iran-server.json'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$candidatePath = [IO.Path]::GetFullPath($Candidate)
if (-not (Test-Path -LiteralPath $candidatePath -PathType Leaf)) { throw 'Recovery candidate does not exist.' }
$candidateObject = Get-Content -Raw -Encoding UTF8 -LiteralPath $candidatePath | ConvertFrom-Json
if (-not $candidateObject -or -not $candidateObject.schemaVersion) { throw 'Recovery candidate is not a bot-store object.' }

$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw 'ConfirmTargetHost does not match the Iran server.' }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ('dent-recovery-invoke-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$remotePrefix = "/tmp/integrated-dent-recovery-$([Guid]::NewGuid().ToString('N'))"
$remoteCandidate = "$remotePrefix.candidate.json"
$remoteClient = "$remotePrefix.py"

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
    & scp @scpOptions $candidatePath "${target}:$remoteCandidate"
    if ($LASTEXITCODE -ne 0) { throw 'Recovery candidate transfer failed.' }
    & scp @scpOptions (Join-Path $root '..\scripts\invoke_bot_store_recovery.py') "${target}:$remoteClient"
    if ($LASTEXITCODE -ne 0) { throw 'Recovery client transfer failed.' }
    $remoteCommand = "set -a; source /etc/integrated-dent/dent-bot.env; set +a; export DENT_BOT_SERVICE_SECRET=`"`$DENT_BOT_SITE_SERVICE_SECRET`"; python3 $remoteClient --candidate $remoteCandidate --expected-active-sha256 $ExpectedActiveSha256"
    & ssh @sshOptions $target "sudo bash -c '$remoteCommand'"
    if ($LASTEXITCODE -ne 0) { throw 'Signed recovery commit failed.' }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "sudo rm -f -- '$remoteCandidate' '$remoteClient'" 2>$null | Out-Null }
    if (Test-Path -LiteralPath $temporaryRoot) { Remove-Item -LiteralPath $temporaryRoot -Recurse -Force }
}
