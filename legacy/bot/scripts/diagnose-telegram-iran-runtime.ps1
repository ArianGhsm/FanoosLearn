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
$temporaryRoot = Join-Path $env:TEMP ('dent-telegram-diagnose-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$localCheck = Join-Path $temporaryRoot 'diagnose.sh'
$remoteCheck = "/tmp/dent-telegram-diagnose-$([Guid]::NewGuid().ToString('N')).sh"

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
set -u
safe_show() {
  local unit="$1"
  local prefix="$2"
  printf '%s_ACTIVE=%s\n' "$prefix" "$(systemctl is-active "$unit" 2>/dev/null || true)"
  printf '%s_ENABLED=%s\n' "$prefix" "$(systemctl is-enabled "$unit" 2>/dev/null || true)"
  systemctl show "$unit" --no-pager \
    -p Result -p NRestarts -p ExecMainStatus -p ActiveEnterTimestamp -p InactiveEnterTimestamp 2>/dev/null \
    | sed "s/^/${prefix}_/"
}

safe_show integrated-dent-bot.service BOT
safe_show integrated-dent-telegram-egress.service EGRESS
safe_show integrated-dent-telegram-egress-refresh.service REFRESH
safe_show integrated-dent-telegram-egress-refresh.timer TIMER
bot_current="$(readlink -f /opt/integrated-dent/telegram/current 2>/dev/null || true)"
bot_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value 2>/dev/null || true)"
bot_cwd=""
if test "${bot_pid:-0}" -gt 0; then
  bot_cwd="$(readlink -f "/proc/$bot_pid/cwd" 2>/dev/null || true)"
fi
printf 'BOT_CURRENT_RELEASE=%s\n' "$(basename "$bot_current")"
if test -n "$bot_current" && test "$bot_cwd" = "$bot_current"; then
  echo BOT_CWD_MATCH=yes
else
  echo BOT_CWD_MATCH=no
fi
systemctl show integrated-dent-telegram-egress-refresh.timer --no-pager \
  -p TimersCalendar -p LastTriggerUSec -p NextElapseUSecRealtime 2>/dev/null \
  | sed 's/^/TIMER_/'
if ss -H -lnt 'sport = :11080' | grep -q '127.0.0.1:11080'; then
  echo PROXY_LISTENER=ready
else
  echo PROXY_LISTENER=missing
fi

python3 - <<'PY'
import json
import os
import stat

path = "/etc/integrated-dent/telegram-egress.json"
try:
    info = os.stat(path)
    print("CONFIG_EXISTS=yes")
    print(f"CONFIG_SIZE={info.st_size}")
    print("CONFIG_MODE=" + oct(stat.S_IMODE(info.st_mode)))
    with open(path, "r", encoding="utf-8") as handle:
        config = json.load(handle)
    inbound = (config.get("inbounds") or [{}])[0]
    outbound = (config.get("outbounds") or [{}])[0]
    print("CONFIG_JSON=valid")
    print("CONFIG_LISTEN_SAFE=" + ("yes" if inbound.get("listen") == "127.0.0.1" and inbound.get("port") == 11080 else "no"))
    print("CONFIG_OUTBOUND_PROTOCOL=" + str(outbound.get("protocol") or "missing")[:24])
except FileNotFoundError:
    print("CONFIG_EXISTS=no")
except Exception as error:
    print("CONFIG_JSON=invalid-" + type(error).__name__)
PY
printf 'CONFIG_OWNER_GROUP=%s\n' "$(stat -c '%U:%G' /etc/integrated-dent/telegram-egress.json 2>/dev/null || echo missing)"
printf 'CONFIG_DIR_MODE_OWNER=%s\n' "$(stat -c '%a:%U:%G' /etc/integrated-dent 2>/dev/null || echo missing)"
printf 'XRAY_BINARY_MODE_OWNER=%s\n' "$(stat -c '%a:%U:%G' /usr/local/lib/integrated-dent/xray/xray 2>/dev/null || echo missing)"
if runuser -u dentegress -- test -r /etc/integrated-dent/telegram-egress.json; then
  echo CONFIG_READABLE_BY_SERVICE=yes
else
  echo CONFIG_READABLE_BY_SERVICE=no
fi
if runuser -u dentegress -- test -x /usr/local/lib/integrated-dent/xray/xray; then
  echo XRAY_EXECUTABLE_BY_SERVICE=yes
else
  echo XRAY_EXECUTABLE_BY_SERVICE=no
fi

test_output="$(mktemp)"
if runuser -u dentegress -- /usr/local/lib/integrated-dent/xray/xray run -test -c /etc/integrated-dent/telegram-egress.json >"$test_output" 2>&1; then
  echo XRAY_CONFIG_TEST=ok
else
  echo XRAY_CONFIG_TEST=failed
  python3 - "$test_output" <<'PY'
import pathlib
import re
import sys

text = pathlib.Path(sys.argv[1]).read_text(encoding="utf-8", errors="replace").lower()
categories = {
    "permission": r"permission denied",
    "missing-file": r"no such file|not found",
    "invalid-json": r"invalid character|json|syntax",
    "invalid-config": r"failed to (?:load|parse|build)|unknown|invalid|unsupported",
    "bind": r"address already in use|failed to listen|bind",
}
matched = [name for name, pattern in categories.items() if re.search(pattern, text)]
print("XRAY_CONFIG_ERROR=" + (",".join(matched) if matched else "other"))
PY
fi
rm -f -- "$test_output"

journalctl -u integrated-dent-telegram-egress-refresh.service --since '-8 hours' --no-pager -o cat 2>/dev/null | python3 -c '
import re, sys
text = sys.stdin.read().lower()
patterns = {
    "subscription": r"subscription|download|http error|curl",
    "no-node": r"no .*node|zero .*node|no candidate|all .*fail",
    "activation": r"post.activation|rollback|listener|restart",
    "timeout": r"timed? out|timeout",
}
for key, pattern in patterns.items():
    print(f"REFRESH_LOG_{key.upper()}={len(re.findall(pattern, text))}")
'

set -a
source /etc/integrated-dent/dent-bot.env
set +a
cd /opt/integrated-dent/telegram/current
runuser -u dentbot -- python3 - <<'PY'
import json
import os
import urllib.error
import urllib.request

token = os.environ.get("DENT_BOT_TELEGRAM_TOKEN", "").strip()
proxy = os.environ.get("DENT_BOT_HTTPS_PROXY", "").strip()
try:
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({"https": proxy}))
    with opener.open(f"https://api.telegram.org/bot{token}/getMe", timeout=12) as response:
        payload = json.loads(response.read(65536).decode("utf-8"))
    print("TELEGRAM_PROBE=ok" if payload.get("ok") is True else "TELEGRAM_PROBE=api-rejected")
except urllib.error.HTTPError as error:
    print(f"TELEGRAM_PROBE=http-{int(error.code)}")
except Exception as error:
    name = type(error).__name__.lower()
    print("TELEGRAM_PROBE=" + ("timeout" if "timeout" in name else "network-error"))
PY

journalctl -u integrated-dent-bot.service --since '-6 hours' --no-pager -o cat 2>/dev/null | python3 -c '
import re, sys
text = sys.stdin.read().lower()
patterns = {
    "conflict409": r"\b409\b|conflict",
    "unauthorized401": r"\b401\b|unauthorized",
    "proxy": r"proxy|127\.0\.0\.1:11080|connection refused",
    "timeout": r"timed? out|timeout",
    "dns": r"name or service not known|temporary failure in name resolution|gaierror",
    "tls": r"ssl|tls|certificate",
    "traceback": r"traceback|uncaught|fatal",
}
for key, pattern in patterns.items():
    print(f"BOT_LOG_{key.upper()}={len(re.findall(pattern, text))}")
'
'@
[IO.File]::WriteAllText($localCheck, $check, [Text.UTF8Encoding]::new($false))
try {
    & scp @scpOptions $localCheck "${target}:$remoteCheck"
    if ($LASTEXITCODE -ne 0) { throw 'Could not transfer Telegram diagnostic.' }
    & ssh @sshOptions $target "sudo bash '$remoteCheck'"
    if ($LASTEXITCODE -ne 0) { throw 'Telegram runtime diagnostic failed.' }
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteCheck'" 2>$null | Out-Null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
