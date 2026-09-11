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
$temporaryRoot = Join-Path $env:TEMP ('dent-student-assistant-live-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$localCheck = Join-Path $temporaryRoot 'check.sh'
$remoteCheck = "/tmp/dent-student-assistant-live-$([Guid]::NewGuid().ToString('N')).sh"

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
$check = @'
#!/usr/bin/env bash
set -euo pipefail
set -a
source /etc/integrated-dent/dent-bot.env
set +a
cd /opt/integrated-dent/telegram/current
runuser -u dentbot -- python3 - <<'PY'
import json
from dent_bot.config import load_settings
from dent_bot.site_api import SiteApiClient, SiteApiError

settings = load_settings()
client = SiteApiClient(
    settings.site_api_url,
    settings.site_service_secret,
    platform="telegram",
    timeout=settings.site_timeout_seconds,
    relay_secret=settings.site_relay_secret,
)
payload = client.request(
    "studentAssistantSummaryV1",
    settings.owner_id,
    contractVersion="student-assistant-v1",
)
view = payload.get("view") or {}
connectors = view.get("connectors") or []
assert payload.get("contractVersion") == "student-assistant-v1"
assert len(connectors) == 3
assert all(item.get("connector") in {"navid", "food", "saba"} for item in connectors)
assert not (view.get("actions") or [])
assert "imageDataUri" not in json.dumps(payload)
try:
    client.request(
        "performIntegrationActionV1",
        settings.owner_id,
        contractVersion="student-assistant-v1",
        actionRef="missingActionRef123",
        requestId="1" * 64,
    )
    raise AssertionError("missing action unexpectedly succeeded")
except SiteApiError as error:
    assert error.status == 404 and error.code == "INTEGRATION_ACTION_NOT_FOUND"
try:
    client.request(
        "integrationChallengeAnswerV1",
        settings.owner_id,
        contractVersion="student-assistant-v1",
        challengeRef="missingChallengeRef123456",
        jobRef="missingJobRef123456789012",
        answer="A7K2P",
        requestId="2" * 64,
    )
    raise AssertionError("missing challenge unexpectedly succeeded")
except SiteApiError as error:
    assert error.status == 409 and error.code == "INTEGRATION_CHALLENGE_NOT_ACTIVE"
print("LIVE_STUDENT_ASSISTANT_SUMMARY_OK")
print("LIVE_STUDENT_ASSISTANT_FAIL_CLOSED_OK")
print("LIVE_CONNECTORS=3")
print("LIVE_ACTIONS=0")
PY
'@
[IO.File]::WriteAllText($localCheck, $check, [Text.UTF8Encoding]::new($false))
try {
    & scp @scpOptions $localCheck "${target}:$remoteCheck"
    if ($LASTEXITCODE -ne 0) { throw 'Could not transfer the live student-assistant check.' }
    & ssh @sshOptions $target "sudo bash '$remoteCheck'"
    if ($LASTEXITCODE -ne 0) { throw 'Live signed student-assistant check failed.' }
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteCheck'" 2>$null | Out-Null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
