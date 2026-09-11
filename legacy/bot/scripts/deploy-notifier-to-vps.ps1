[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [switch]$Bootstrap
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$configPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $root $ServerConfig }
$configPath = (Resolve-Path -LiteralPath $configPath).Path
$serverStateRoot = if ((Split-Path $configPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $configPath -Parent) -Leaf) -eq '.codex-local') { Split-Path (Split-Path $configPath -Parent) -Parent } else { $root }
if (-not (Test-Path -LiteralPath $configPath)) { throw "Server config is missing." }
$server = Get-Content -LiteralPath $configPath -Raw | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { $server.user } else { $server.bootstrapUser }
if (-not $sshUser) { throw "SSH user is missing from server config." }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$releaseId = "notifier-" + (Get-Date -Format "yyyyMMddHHmmss")
$lifecycleBaseId = "integrated-ops-$releaseId"
$lifecycleStarted = $false
. (Join-Path $PSScriptRoot "deploy-lifecycle.ps1")
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-notifier-" + [Guid]::NewGuid().ToString("N"))
$bundle = Join-Path $temporaryRoot "$releaseId.tar.gz"
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$remoteBundle = "/tmp/$releaseId.tar.gz"
$remoteRoot = "/tmp/$releaseId"
$target = "$sshUser@$($server.host)"

try {
    if (-not $Bootstrap) {
        Publish-DentDeployLifecycle -Service integrated-ops -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Central notifier deployment started." -ServerConfig $configPath
        $lifecycleStarted = $true
    }
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    $knownHostsPath = [string]$server.knownHostsFile
    if (-not [IO.Path]::IsPathRooted($knownHostsPath)) { $knownHostsPath = Join-Path $serverStateRoot $knownHostsPath }
    Copy-Item -LiteralPath ([IO.Path]::GetFullPath($knownHostsPath)) -Destination $runtimeKnownHosts -Force
    $sshOptions = @(
        "-i", $identityFile, "-p", [string]$server.port,
        "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1",
        "-o", "UserKnownHostsFile=$runtimeKnownHosts"
    )
    $scpOptions = @(
        "-q", "-i", $identityFile, "-P", [string]$server.port,
        "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
        "-o", "ConnectTimeout=10", "-o", "ConnectionAttempts=1",
        "-o", "UserKnownHostsFile=$runtimeKnownHosts"
    )
    & python (Join-Path $root "scripts/check_utf8_integrity.py")
    if ($LASTEXITCODE -ne 0) { throw "UTF-8 integrity validation failed." }
    & python -m unittest discover -s (Join-Path $root "tests") -p "test_deploy_notifier.py"
    if ($LASTEXITCODE -ne 0) { throw "Deploy notifier tests failed." }
    & tar -czf $bundle -C $root deploy_notifier dent_bot config docs ops
    if ($LASTEXITCODE -ne 0) { throw "Could not build notifier bundle." }
    & scp @scpOptions $bundle "${target}:$remoteBundle"
    if ($LASTEXITCODE -ne 0) { throw "Could not upload notifier bundle." }
    $remote = "sudo install -d -m 0700 '$remoteRoot' && sudo tar -xzf '$remoteBundle' -C '$remoteRoot' && sudo bash '$remoteRoot/ops/install-deploy-notifier.sh' '$remoteRoot' '$releaseId'"
    & ssh @sshOptions $target $remote
    if ($LASTEXITCODE -ne 0) { throw "Remote notifier installation failed." }
    if ($Bootstrap) {
        Publish-DentDeployLifecycle -Service integrated-ops -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Central notifier bootstrap delivery validation started." -ServerConfig $configPath
        $lifecycleStarted = $true
    }
    Publish-DentDeployLifecycle -Service integrated-ops -Status succeeded -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Central notifier deployment passed three-channel health checks." -ServerConfig $configPath
    $lifecycleStarted = $false
}
catch {
    if ($lifecycleStarted) {
        Publish-DentDeployLifecycle -Service integrated-ops -Status failed -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Central notifier deployment failed; inspect the deploy report." -ServerConfig $configPath
        $lifecycleStarted = $false
    }
    throw
}
finally {
    if ($sshOptions) {
        & ssh @sshOptions $target "sudo rm -rf -- '$remoteRoot' '$remoteBundle'" 2>$null
    }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
