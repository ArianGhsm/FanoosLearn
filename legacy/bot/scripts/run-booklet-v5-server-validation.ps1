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
$temporaryRoot = Join-Path $env:TEMP ('dent-booklet-v5-validation-' + [Guid]::NewGuid().ToString('N'))
$knownHosts = Join-Path $temporaryRoot 'known_hosts'
$remoteRoot = '/tmp/dent-booklet-benchmark-v5-validation-' + [Guid]::NewGuid().ToString('N')

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath ([IO.Path]::GetFullPath((Join-Path $root ([string]$server.knownHostsFile)))) -Destination $knownHosts
    $common = @(
        '-i', $identityFile, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', 'ConnectTimeout=10', '-o', 'ConnectionAttempts=1', "-o", "UserKnownHostsFile=$knownHosts"
    )
    $ssh = @('-p', [string]$server.port) + $common
    $scp = @('-q', '-P', [string]$server.port) + $common

    & ssh @ssh $target "install -d -m 0755 '$remoteRoot/scripts'"
    if ($LASTEXITCODE -ne 0) { throw 'Could not create server validation directory.' }

    $files = @(
        'scripts/run_booklet_v5_server_validation.sh',
        'scripts/run_booklet_benchmark_suite.sh',
        'scripts/benchmark_booklet_pipeline.py',
        'scripts/forensic_attack_suite.py'
    )
    foreach ($relative in $files) {
        $name = Split-Path -Leaf $relative
        & scp @scp (Join-Path $root $relative) "${target}:$remoteRoot/scripts/$name"
        if ($LASTEXITCODE -ne 0) { throw "Could not transfer $relative." }
    }

    & ssh @ssh $target "sudo bash '$remoteRoot/scripts/run_booklet_v5_server_validation.sh' '$remoteRoot' 1048576"
    if ($LASTEXITCODE -ne 0) { throw 'Server booklet v5 validation failed.' }

    $benchmarkDestination = Join-Path $root 'ops/benchmarks/booklet-v5-server-benchmark.json'
    $attackDestination = Join-Path $root 'ops/benchmarks/booklet-forensic-v5-server-attack-suite.json'
    $benchmarkRaw = (& ssh @ssh $target "sudo cat '$remoteRoot/benchmark.json'") -join "`n"
    if ($LASTEXITCODE -ne 0 -or -not $benchmarkRaw) { throw 'Could not retrieve server benchmark report.' }
    $attackRaw = (& ssh @ssh $target "sudo cat '$remoteRoot/attack.json'") -join "`n"
    if ($LASTEXITCODE -ne 0 -or -not $attackRaw) { throw 'Could not retrieve server attack report.' }
    [IO.File]::WriteAllText($benchmarkDestination, $benchmarkRaw + "`n", [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText($attackDestination, $attackRaw + "`n", [Text.UTF8Encoding]::new($false))

    $benchmark = Get-Content -Raw -Encoding UTF8 -LiteralPath $benchmarkDestination | ConvertFrom-Json
    $attack = Get-Content -Raw -Encoding UTF8 -LiteralPath $attackDestination | ConvertFrom-Json
    Write-Output ('SERVER_BENCHMARK_ROWS=' + @($benchmark.results).Count)
    Write-Output ('SERVER_ATTACK_SUCCESS=' + [string]$attack.success)
}
finally {
    if ($ssh) {
        & ssh @ssh $target "case '$remoteRoot' in /tmp/dent-booklet-benchmark-v5-validation-*) sudo rm -rf -- '$remoteRoot' ;; *) exit 64 ;; esac" 2>$null | Out-Null
    }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
