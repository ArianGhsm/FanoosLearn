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
$temporaryRoot = Join-Path $env:TEMP ('dent-rich-probe-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$localProbe = Join-Path $temporaryRoot 'probe.py'
$remoteProbe = "/tmp/dent-rich-probe-$([Guid]::NewGuid().ToString('N')).py"

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
$probe = @'
from dent_bot.api import TelegramBotApi, _persianize_reply_markup, _persianize_visible_rich_text
from dent_bot.config import load_settings
from dent_bot.site_api import SiteApiClient
from dent_bot.ui import grades_screen

settings = load_settings()
bot = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
site = SiteApiClient(
    settings.site_api_url,
    settings.site_service_secret,
    platform="telegram",
    timeout=settings.site_timeout_seconds,
    relay_secret=settings.site_relay_secret,
)
screen = grades_screen(settings.site_url, site.grades(settings.owner_id), platform="telegram")
rich_html = str(getattr(screen.text, "rich_html", "") or "")
assert rich_html and "<table" in rich_html
result = dict(bot.call("sendRichMessage", {
    "chat_id": settings.owner_id,
    "rich_message": {"html": _persianize_visible_rich_text(rich_html), "is_rtl": True},
    "reply_markup": _persianize_reply_markup(screen.keyboard),
}) or {})
print("RICH_PROBE_OK=true")
print("RETURN_HAS_RICH_MESSAGE=" + ("true" if isinstance(result.get("rich_message"), dict) else "false"))
print("RETURN_HAS_REGULAR_TEXT=" + ("true" if bool(result.get("text")) else "false"))
'@
[IO.File]::WriteAllText($localProbe, $probe, [Text.UTF8Encoding]::new($false))
try {
    & scp @scpOptions $localProbe "${target}:$remoteProbe"
    if ($LASTEXITCODE -ne 0) { throw 'Could not transfer the Telegram rich-message probe.' }
    $command = "sudo bash -lc 'set -a; source /etc/integrated-dent/dent-bot.env; set +a; runuser -u dentbot -- env PYTHONPATH=/opt/integrated-dent/telegram/current python3 $remoteProbe'"
    & ssh @sshOptions $target $command
    if ($LASTEXITCODE -ne 0) { throw 'Telegram rejected the live rich-message probe.' }
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteProbe'" 2>$null | Out-Null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
