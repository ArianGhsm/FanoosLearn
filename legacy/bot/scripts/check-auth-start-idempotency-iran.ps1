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
$temporaryRoot = Join-Path $env:TEMP ('dent-auth-start-check-' + [Guid]::NewGuid().ToString('N'))
$knownHosts = Join-Path $temporaryRoot 'known_hosts'
$localCheck = Join-Path $temporaryRoot 'check.sh'
$remoteCheck = "/tmp/dent-auth-start-check-$([Guid]::NewGuid().ToString('N')).sh"

try {
    New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
    Copy-Item -LiteralPath ([IO.Path]::GetFullPath((Join-Path $root ([string]$server.knownHostsFile)))) -Destination $knownHosts
    $sshOptions = @(
        '-i', $identityFile, '-p', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$knownHosts", '-o', 'ConnectTimeout=10'
    )
    $scpOptions = @(
        '-q', '-i', $identityFile, '-P', [string]$server.port,
        '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=yes',
        '-o', "UserKnownHostsFile=$knownHosts", '-o', 'ConnectTimeout=10'
    )
    $check = @'
#!/usr/bin/env bash
set -euo pipefail
telegram_current="$(readlink -f /opt/integrated-dent/telegram/current)"
bale_current="$(readlink -f /opt/integrated-dent/bale/current)"
telegram_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
bale_pid="$(systemctl show integrated-dent-bale-bot.service -p MainPID --value)"
test "$(readlink -f "/proc/$telegram_pid/cwd")" = "$telegram_current"
test "$(readlink -f "/proc/$bale_pid/cwd")" = "$bale_current"

for item in "telegram:$telegram_current:/opt/integrated-dent/telegram-venv/bin/python" "bale:$bale_current:/usr/bin/python3"; do
  IFS=: read -r platform current python_bin <<<"$item"
  cd "$current"
  "$python_bin" - "$platform" <<'PY'
import sys
import tempfile
from pathlib import Path

from dent_bot.app import DentBotApp
from dent_bot.state import BotState


class Api:
    def __init__(self):
        self.removed = []
        self.sent = []

    def remove_reply_keyboard(self, chat_id):
        self.removed.append(chat_id)
        return {"message_id": len(self.removed)}

    def send(self, chat_id, text, keyboard):
        self.sent.append((chat_id, text, keyboard))
        return {"message_id": len(self.sent)}


class Site:
    def account(self, _user_id):
        return {
            "success": True,
            "linked": True,
            "authComplete": True,
            "user": {"name": "verified"},
            "identity": {"recognized": True},
        }


platform = sys.argv[1]
with tempfile.TemporaryDirectory(prefix=f"auth-start-{platform}-") as directory:
    state = BotState(Path(directory) / "state.sqlite3")
    api = Api()
    try:
        app = DentBotApp(
            api, state, owner_id=10, site_url="https://dentistry1402tums.ir",
            site_api=Site(), platform=platform,
        )
        state.mark_reply_keyboard_active(20)
        update = {
            "message": {
                "message_id": 1,
                "text": "/start",
                "chat": {"id": 20, "type": "private"},
                "from": {"id": 20, "first_name": "Test"},
            }
        }
        app.handle(update)
        app.handle(update)
        app.handle(update)
        assert api.removed == [20], api.removed
        assert len(api.sent) == 3, len(api.sent)
        assert all("ورود کامل شد" not in text for _chat, text, _keyboard in api.sent)
    finally:
        state.close()
print(f"{platform.upper()}_REPEATED_START_QUIET=true")
PY
done

echo AUTH_START_IDEMPOTENCY_CHECK_OK
'@
    [IO.File]::WriteAllText($localCheck, $check, [Text.UTF8Encoding]::new($false))
    & scp @scpOptions $localCheck "${target}:$remoteCheck"
    if ($LASTEXITCODE -ne 0) { throw 'Auth/start smoke-check transfer failed.' }
    & ssh @sshOptions $target "sudo bash '$remoteCheck'"
    if ($LASTEXITCODE -ne 0) { throw 'Auth/start idempotency smoke-check failed.' }
}
finally {
    if ($sshOptions) { & ssh @sshOptions $target "rm -f -- '$remoteCheck'" 2>$null }
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
