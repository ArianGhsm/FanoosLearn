[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = ".codex-local/iran-server.json",
    [string]$KnownHostsFile = ".codex-local/iran_known_hosts"
)

$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath (Join-Path $root $ServerConfig) | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw "ConfirmTargetHost does not match the Iran server." }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$releaseId = Get-Date -Format "yyyyMMdd-HHmmss"
$lifecycleBaseId = "bale-bot-$releaseId"
$lifecycleStarted = $false
. (Join-Path $PSScriptRoot "deploy-lifecycle.ps1")
$temporaryRoot = Join-Path $env:TEMP ("integrated-dent-bale-code-" + [Guid]::NewGuid().ToString("N"))
$knownHostsSource = [IO.Path]::GetFullPath((Join-Path $root $KnownHostsFile))
$runtimeKnownHosts = Join-Path $temporaryRoot "known_hosts"
$bundle = Join-Path $temporaryRoot "bale-code.tar.gz"
$remotePrefix = "/tmp/integrated-dent-bale-code-$([Guid]::NewGuid().ToString('N'))"
$remoteBundle = "$remotePrefix.tar.gz"
$remoteInstaller = "$remotePrefix.sh"

New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
Copy-Item -LiteralPath $knownHostsSource -Destination $runtimeKnownHosts -Force
$sshOptions = @("-i", $identityFile, "-p", [string]$server.port, "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes", "-o", "UserKnownHostsFile=$runtimeKnownHosts")
$scpOptions = @("-q", "-i", $identityFile, "-P", [string]$server.port, "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes", "-o", "UserKnownHostsFile=$runtimeKnownHosts")

try {
    Publish-DentDeployLifecycle -Service bale-bot -Status started -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Bale bot code deployment started."
    $lifecycleStarted = $true
    & tar -C $root -czf $bundle dent_bot
    if ($LASTEXITCODE -ne 0) { throw "The Bale code bundle could not be created." }
    & scp @scpOptions $bundle "${target}:$remoteBundle"
    if ($LASTEXITCODE -ne 0) { throw "The Bale code bundle could not be transferred." }

    $installerTemplate = @'
#!/usr/bin/env bash
set -euo pipefail
bundle='__REMOTE_BUNDLE__'
release_root='/opt/integrated-dent/releases/bale-__RELEASE_ID__'
current_link='/opt/integrated-dent/bale/current'
previous_target="$(readlink -f "$current_link" 2>/dev/null || true)"
trap 'rm -f -- "$bundle"' EXIT
test -s "$bundle"
install -d -m 0755 "$release_root"
tar -xzf "$bundle" -C "$release_root"
chown -R root:root "$release_root"
find "$release_root" -type d -exec chmod 0755 {} +
find "$release_root" -type f -exec chmod 0644 {} +
python3 -m compileall -q "$release_root/dent_bot"
ln -sfn "$release_root" "$current_link"
systemctl restart integrated-dent-bale-bot.service
sleep 3
if ! systemctl is-active --quiet integrated-dent-bale-bot.service; then
  if test -n "$previous_target"; then
    ln -sfn "$previous_target" "$current_link"
    systemctl restart integrated-dent-bale-bot.service
  fi
  exit 1
fi
main_pid="$(systemctl show integrated-dent-bale-bot.service -p MainPID --value)"
if ! test "$main_pid" -gt 0 || ! test "$(readlink -f "/proc/$main_pid/cwd")" = "$release_root"; then
  if test -n "$previous_target"; then
    ln -sfn "$previous_target" "$current_link"
    systemctl restart integrated-dent-bale-bot.service
  else
    systemctl stop integrated-dent-bale-bot.service || true
  fi
  exit 1
fi
set -a
source /etc/integrated-dent/bale-bot.env
set +a
if ! runuser -u dentbale -- bash -c 'cd /opt/integrated-dent/bale/current && python3 -m dent_bot.bale_health'; then
  if test -n "$previous_target"; then
    ln -sfn "$previous_target" "$current_link"
    systemctl restart integrated-dent-bale-bot.service
  fi
  exit 1
fi
echo BALE_CODE_DEPLOY_OK
echo BALE_RELEASE=__RELEASE_ID__
'@
    $installer = $installerTemplate.Replace('__REMOTE_BUNDLE__', $remoteBundle).Replace('__RELEASE_ID__', $releaseId)
    $installerPath = Join-Path $temporaryRoot "install.sh"
    [IO.File]::WriteAllText($installerPath, $installer, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $installerPath "${target}:$remoteInstaller"
    if ($LASTEXITCODE -ne 0) { throw "The Bale activation script could not be transferred." }
    & ssh @sshOptions $target "sudo bash '$remoteInstaller'"
    if ($LASTEXITCODE -ne 0) { throw "The Bale code deployment failed and rollback was attempted." }
    Publish-DentDeployLifecycle -Service bale-bot -Status succeeded -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Bale bot code deployment passed health checks."
    $lifecycleStarted = $false
}
catch {
    if ($lifecycleStarted) {
        Publish-DentDeployLifecycle -Service bale-bot -Status failed -ReleaseId $releaseId -EventBaseId $lifecycleBaseId -Summary "Bale bot code deployment failed; verify rollback."
        $lifecycleStarted = $false
    }
    throw
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteBundle' '$remoteInstaller'" 2>$null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
