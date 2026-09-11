[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$ConfirmTargetHost,
    [string]$ServerConfig = '.codex-local/iran-server.json'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$serverConfigPath = if ([IO.Path]::IsPathRooted($ServerConfig)) { $ServerConfig } else { Join-Path $root $ServerConfig }
$serverConfigPath = (Resolve-Path -LiteralPath $serverConfigPath).Path
$serverStateRoot = if ((Split-Path $serverConfigPath -Leaf) -eq 'iran-server.json' -and (Split-Path (Split-Path $serverConfigPath -Parent) -Leaf) -eq '.codex-local') { Split-Path (Split-Path $serverConfigPath -Parent) -Parent } else { $root }
$server = Get-Content -Raw -Encoding UTF8 -LiteralPath $serverConfigPath | ConvertFrom-Json
if ([string]$server.host -ne $ConfirmTargetHost) { throw 'ConfirmTargetHost does not match the Iran server.' }
$sshUser = if ($server.user) { [string]$server.user } else { [string]$server.bootstrapUser }
$identityFile = [Environment]::ExpandEnvironmentVariables([string]$server.identityFile)
$target = "$sshUser@$($server.host)"
$temporaryRoot = Join-Path $env:TEMP ('dent-runtime-check-' + [Guid]::NewGuid().ToString('N'))
$runtimeKnownHosts = Join-Path $temporaryRoot 'known_hosts'
$localCheck = Join-Path $temporaryRoot 'check.sh'
$remoteCheck = "/tmp/integrated-dent-runtime-check-$([Guid]::NewGuid().ToString('N')).sh"

New-Item -ItemType Directory -Force -Path $temporaryRoot | Out-Null
$knownHostsPath = [string]$server.knownHostsFile
if (-not [IO.Path]::IsPathRooted($knownHostsPath)) { $knownHostsPath = Join-Path $serverStateRoot $knownHostsPath }
Copy-Item -LiteralPath ([IO.Path]::GetFullPath($knownHostsPath)) -Destination $runtimeKnownHosts
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
systemctl is-active --quiet integrated-dent-bot.service
systemctl is-active --quiet integrated-dent-bale-bot.service
systemctl is-active --quiet integrated-dent-telegram-egress.service
telegram_pid="$(systemctl show integrated-dent-bot.service -p MainPID --value)"
telegram_current="$(readlink -f /opt/integrated-dent/telegram/current)"
telegram_process_cwd="$(readlink -f "/proc/$telegram_pid/cwd")"
test "$telegram_process_cwd" = "$telegram_current"
bale_pid="$(systemctl show integrated-dent-bale-bot.service -p MainPID --value)"
bale_current="$(readlink -f /opt/integrated-dent/bale/current)"
bale_process_cwd="$(readlink -f "/proc/$bale_pid/cwd")"
test "$bale_process_cwd" = "$bale_current"
test "$(systemctl show integrated-dent-bot.service -p NRestarts --value)" = 0
test "$(systemctl show integrated-dent-bale-bot.service -p NRestarts --value)" = 0
test "$(systemctl show integrated-dent-telegram-egress.service -p NRestarts --value)" = 0
ss -lnt | grep -q '127.0.0.1:11080'
set -a
source /etc/integrated-dent/dent-bot.env
set +a
cd /opt/integrated-dent/telegram/current
test -x /opt/integrated-dent/telegram-venv/bin/python
command -v qpdf >/dev/null
test "$DENT_BOT_BOOKLET_WATERMARK_FONT" = "$telegram_current/dent_bot/assets/fonts/B_Nazanin_Bold.ttf"
test -r "$DENT_BOT_BOOKLET_WATERMARK_FONT"
grep -Eq '^DENT_BOT_BOOKLET_FINGERPRINT_KEY=.{43,}$|^DENT_BOT_BOOKLET_FINGERPRINT_KEY=[0-9A-Fa-f]{64}$' /etc/integrated-dent/dent-bot.env
test -d /var/lib/integrated-dent/dent-bot/tmp/booklets
test ! -e /opt/integrated-dent/private-reader
test ! -e /var/lib/integrated-dent/private-reader
test ! -e /etc/integrated-dent/private-reader.env
test ! -e /etc/nginx/sites-enabled/private-reader.conf
runuser -u dentbot -- /opt/integrated-dent/telegram-venv/bin/python -c 'import pymupdf' >/dev/null
runuser -u dentbot -- /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.forensic_detector --help >/dev/null
runuser -u dentbot -- /opt/integrated-dent/telegram-venv/bin/python -m dent_bot.health >/dev/null
runuser -u dentbot -- /opt/integrated-dent/telegram-venv/bin/python - <<'PY'
import json
import inspect
import tempfile
from pathlib import Path

from dent_bot.config import load_settings
from dent_bot.api import TelegramBotApi
from dent_bot.app import DentBotApp
from dent_bot.onboarding import CLASS_SITE, prompt_screen
from dent_bot.site_api import SiteApiClient, SiteApiError
from dent_bot.state import BotState
from dent_bot.ui import account_screen, notification_detail_screen

settings = load_settings()
telegram = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
cleanup_source = inspect.getsource(TelegramBotApi.remove_reply_keyboard)
assert 'deleteMessage' in cleanup_source, "keyboard_cleanup_carrier_not_deleted"
me = dict(telegram.call("getMe") or {})
required_channel = str(getattr(settings, "required_channel_username", "Dent1402Booklets") or "Dent1402Booklets").lstrip("@")
bot_membership = dict(telegram.call("getChatMember", {
    "chat_id": f"@{required_channel}", "user_id": int(me.get("id") or 0),
}) or {})
assert bot_membership.get("status") in {"creator", "administrator"}, ("required_channel_admin", bot_membership.get("status"))
assert telegram.is_chat_member(f"@{required_channel}", settings.owner_id), "owner_required_channel_membership"
telegram.close()
client = SiteApiClient(
    settings.site_api_url,
    settings.site_service_secret,
    platform="telegram",
    timeout=settings.site_timeout_seconds,
    relay_secret=settings.site_relay_secret,
)
try:
    catalog = client.onboarding_catalog(settings.owner_id)
except SiteApiError as error:
    # The public message intentionally stays generic.  The release gate needs
    # only the non-sensitive HTTP/reason-code pair to distinguish a service
    # signature problem from fail-closed storage availability.
    print(f"ONBOARDING_CATALOG_ERROR status={error.status} code={error.code}")
    raise
assert catalog.get("contractVersion") == "bot-onboarding-v1", ("catalog_contract", catalog.get("contractVersion"))
institutions = list(catalog.get("institutions") or [])
assert len(institutions) == 106, ("institutions", len(institutions))
assert sum(1 for item in institutions if item.get("system") == "azad") == 31, ("azad", sum(1 for item in institutions if item.get("system") == "azad"))
entry_years = list(catalog.get("entryYears") or [])
expected_entry_years = [
    "\u06f1\u06f3\u06f9\u06f9", "\u06f1\u06f4\u06f0\u06f0",
    "\u06f1\u06f4\u06f0\u06f1", "\u06f1\u06f4\u06f0\u06f2",
    "\u06f1\u06f4\u06f0\u06f3", "\u06f1\u06f4\u06f0\u06f4",
    "\u06f1\u06f4\u06f0\u06f5",
]
assert entry_years == expected_entry_years, ("entry_years", entry_years)
entry_year_screen = prompt_screen("entry-year", {}, catalog)
assert all(year in str(entry_year_screen.keyboard) for year in entry_years), "entry_year_keyboard_incomplete"
tehran = "\u062a\u0647\u0631\u0627\u0646"
azad = "\u0622\u0632\u0627\u062f"
tehran_institution_screen = prompt_screen("institution", {"province": tehran}, catalog)
assert azad in str(tehran_institution_screen.keyboard), "tehran_azad_missing_from_keyboard"
azad_types = list(catalog.get("azadAdmissionTypes") or [])
assert len(azad_types) == 2 and all(isinstance(item, str) and item for item in azad_types), ("azad_types", len(azad_types))
assert len(catalog.get("provinces") or []) == 31, ("provinces", len(catalog.get("provinces") or []))
status = client.onboarding_status(settings.owner_id)
assert status.get("contractVersion") == "bot-onboarding-v1", ("status_contract", status.get("contractVersion"))
owner_account = client.account(settings.owner_id)
term7_status = client.request("academicTerm7StatusV1", settings.owner_id)
assert term7_status.get("contractVersion") == "academic-term7-v1", ("term7_contract", term7_status.get("contractVersion"))
assert term7_status.get("scheduleVersion") == "1405-1406.1", ("term7_schedule", term7_status.get("scheduleVersion"))
assert term7_status.get("cohortKey") == "dentistry-1402", ("term7_cohort", term7_status.get("cohortKey"))
assert term7_status.get("timezone") == "Asia/Tehran", ("term7_timezone", term7_status.get("timezone"))
term7_endo = dict(term7_status.get("thursdayEndo") or {})
assert term7_endo.get("start") == "08:30" and term7_endo.get("end") == "10:30" and bool(term7_endo.get("title")), ("term7_endo", term7_endo)
term7_sample = dict(term7_status.get("sample") or {})
assert term7_sample.get("rotation") == "A" and len(term7_sample.get("morningTitles") or []) == 1 and len(term7_sample.get("afternoonTitles") or []) == 1, ("term7_rtl_sample", term7_sample)
assert term7_status.get("foodUrl") == "http://foodstu.tums.ac.ir", ("term7_food_url", term7_status.get("foodUrl"))
assert "disNumber" in dict(owner_account.get("user") or {}), "account_dis_contract_missing"
owner_account_screen = account_screen(
    settings.site_url,
    platform="telegram",
    linked_user=dict(owner_account.get("user") or {}),
    onboarding_profile=dict(owner_account.get("onboardingProfile") or {}),
)
owner_dis = str((owner_account.get("user") or {}).get("disNumber") or "")
assert ("111" in owner_account_screen.text) is bool(owner_dis), "account_dis_default_password_visibility"
food_screen = notification_detail_screen(
    {
        "title": "food",
        "body": "body",
        "ctaLabel": "رزرو غذا",
        "ctaUrl": "http://foodstu.tums.ac.ir",
        "actions": [{"ref": "smokeAction123", "label": "رزرو کردم", "style": "success"}],
    },
    "smokeRef123",
    platform="telegram",
    is_owner=False,
)
assert "http://foodstu.tums.ac.ir" in str(food_screen.keyboard), "food_url_button_missing"
assert "notification-action:smokeRef123:smokeAction123" in str(food_screen.keyboard), "food_callback_button_missing"
try:
    client.request(
        "performNotificationAction",
        settings.owner_id,
        notificationId="nt-smoke123456",
        actionRef="smokeAction123",
    )
    raise AssertionError("unknown notification callback unexpectedly succeeded")
except SiteApiError as error:
    assert error.status == 404 and error.code == "NOTIFICATION_NOT_FOUND", ("callback_route", error.status, error.code)
state = BotState(settings.state_db, payment_offers_path=settings.payment_offers_db)
try:
    owner_dialog = state.dialog(settings.owner_id) or {}
finally:
    state.close()

class OwnerStartProbe:
    def __init__(self):
        self.sent = []

    def is_chat_member(self, _chat_id, _user_id):
        return True

    def send(self, *args):
        self.sent.append(args)
        return {"message_id": 1}

    def edit(self, *args):
        self.sent.append((args[0], args[2], args[3]))
        return {"message_id": args[1]}

    def answer_callback(self, *_args, **_kwargs):
        return True

    def remove_reply_keyboard(self, *_args, **_kwargs):
        return {}

class GenericPurchaseSite:
    def __init__(self):
        self.created = []

    def account(self, _user_id):
        return {
            "linked": False,
            "authComplete": False,
            "user": None,
            "onboardingProfile": {
                "firstName": "Generic",
                "lastName": "Payer",
                "verifiedAt": "2026-08-29T00:00:00Z",
                "isClassMember": False,
                "studentNumber": "",
            },
        }

    def payment_product_states(self, _user_id, refs):
        return {"states": {ref: {} for ref in refs}}

    def create_bot_payment(self, _user_id, **fields):
        self.created.append(fields)
        return {
            "success": True,
            "orderToken": "o" * 24,
            "redirectUrl": "https://example.invalid/pay",
            "status": "pending",
        }

with tempfile.TemporaryDirectory() as probe_dir:
    probe_api = OwnerStartProbe()
    probe_state = BotState(Path(probe_dir) / "state.sqlite3")
    try:
        probe_app = DentBotApp(
            probe_api,
            probe_state,
            owner_id=settings.owner_id,
            site_url=settings.site_url,
            site_api=client,
            platform="telegram",
            bot_username=settings.bot_username,
            required_channel_username=required_channel,
        )
        probe_app.handle({"message": {
            "text": "/start",
            "from": {"id": settings.owner_id},
            "chat": {"id": settings.owner_id, "type": "private"},
        }})
        assert len(probe_api.sent) == 1, ("owner_start_sends", len(probe_api.sent))
        owner_start_markup = dict(probe_api.sent[0][2])
        if owner_account.get("authComplete") is True:
            assert "v1:admin" in str(owner_start_markup), "authenticated_owner_home_missing"
            owner_start_gate = "authenticated-home"
            owner_site_connect_button = False
        else:
            assert CLASS_SITE in str(owner_start_markup), "owner_site_connect_button_missing"
            owner_start_gate = "class-auth-reconnect"
            owner_site_connect_button = True
    finally:
        probe_state.close()

assert not DentBotApp._requires_canonical_link("payment-create-link:token"), "generic_checkout_gate"
assert not DentBotApp._requires_canonical_link("payment-status:" + "o" * 24), "generic_status_gate"
assert DentBotApp._requires_canonical_link("grades"), "private_grade_gate"
with tempfile.TemporaryDirectory() as generic_probe_dir:
    generic_api = OwnerStartProbe()
    generic_site = GenericPurchaseSite()
    generic_state = BotState(Path(generic_probe_dir) / "state.sqlite3")
    try:
        generic_offer = generic_state.create_payment_offer(
            "Public product",
            250000,
            audience={"mode": "open"},
        )
        generic_app = DentBotApp(
            generic_api,
            generic_state,
            owner_id=settings.owner_id,
            site_url=settings.site_url,
            site_api=generic_site,
            platform="telegram",
            bot_username=settings.bot_username,
            required_channel_username=required_channel,
        )
        generic_user_id = settings.owner_id + 1000000
        generic_app.handle({"message": {
            "text": "/start product_" + generic_offer["shareToken"],
            "from": {"id": generic_user_id},
            "chat": {"id": generic_user_id, "type": "private"},
        }})
        assert "Public product" in str(generic_api.sent[-1][1]), "generic_product_deep_link"
        generic_app.handle({"callback_query": {
            "id": "generic-product-callback",
            "from": {"id": generic_user_id},
            "data": "v1:payment-create-link:" + generic_offer["shareToken"],
            "message": {"message_id": 10, "chat": {"id": generic_user_id, "type": "private"}},
        }})
        assert len(generic_site.created) == 1, "generic_product_create"
    finally:
        generic_state.close()

assert telegram._prepare_rich_text("\u0627\u0639\u062f\u0627\u062f 123 \u0648 1402") == "\u0627\u0639\u062f\u0627\u062f \u06f1\u06f2\u06f3 \u0648 \u06f1\u06f4\u06f0\u06f2", "persian_digit_boundary"
print(json.dumps({
    "required_channel_configured": bool(required_channel),
    "catalog_institutions": len(institutions),
    "catalog_azad": sum(1 for item in institutions if item.get("system") == "azad"),
    "owner_linked": owner_account.get("linked"),
    "owner_auth_complete": owner_account.get("authComplete"),
    "owner_profile_present": bool(owner_account.get("onboardingProfile")),
    "owner_profile_is_class": (owner_account.get("onboardingProfile") or {}).get("isClassMember"),
    "owner_onboarding_completed": status.get("completed"),
    "owner_onboarding_is_class": status.get("isClassMember"),
    "owner_dialog_kind": owner_dialog.get("kind"),
    "owner_dialog_step": owner_dialog.get("step"),
    "owner_start_gate": owner_start_gate,
    "owner_site_connect_button": owner_site_connect_button,
    "generic_purchase_gate": True,
    "keyboard_cleanup_carrier_deleted": True,
    "persian_digit_boundary": True,
    "term7_schedule_version": term7_status.get("scheduleVersion"),
    "term7_assignment_count": term7_status.get("assignmentCount"),
    "term7_callback_route": True,
    "account_dis_contract": True,
}, sort_keys=True))
assert owner_account.get("linked") is True, ("owner_linked", owner_account.get("linked"))
if (owner_account.get("onboardingProfile") or {}).get("isClassMember"):
    assert (owner_account.get("onboardingProfile") or {}).get("entryYear") == "\u06f1\u06f4\u06f0\u06f2", "class_entry_year_missing"
if owner_account.get("authComplete") is True:
    auth_status = client.identity_auth_v2_status(settings.owner_id)
    assert auth_status.get("contractVersion") == "identity-auth-v2", ("identity_contract", auth_status.get("contractVersion"))
    assert int(auth_status.get("pendingManualClaims") or 0) == 0, ("pending_manual_claims", auth_status.get("pendingManualClaims"))
else:
    try:
        client.identity_auth_v2_status(settings.owner_id)
        raise AssertionError("legacy owner link unexpectedly reached a private endpoint")
    except SiteApiError as error:
        assert error.status == 403 and error.code == "ACCOUNT_AUTH_REQUIRED", ("legacy_owner_gate", error.status, error.code)
if owner_account.get("authComplete") is True:
    try:
        client.request("identityClaims", settings.owner_id)
        raise AssertionError("legacy identityClaims unexpectedly remained enabled")
    except SiteApiError as error:
        assert error.status == 410 and error.code == "MANUAL_IDENTITY_DISABLED", ("legacy_claims", error.status, error.code)
unlinked_id = 999999999999999
unlinked = client.account(unlinked_id)
assert unlinked.get("linked") is False, ("unlinked_account", unlinked.get("linked"))
try:
    client.grades(unlinked_id)
    raise AssertionError("unlinked identity unexpectedly reached a private endpoint")
except SiteApiError as error:
    assert error.status == 403 and error.code == "ACCOUNT_LINK_REQUIRED", ("private_gate", error.status, error.code)
PY
set -a
source /etc/integrated-dent/bale-bot.env
set +a
cd /opt/integrated-dent/bale/current
runuser -u dentbale -- python3 -m dent_bot.bale_health >/dev/null
cd /opt/integrated-dent/telegram/current
/opt/integrated-dent/telegram-venv/bin/python - <<'PY'
from dent_bot.ui import notification_detail_screen, notification_list_screen
items = [{"id": f"nt-{index}", "title": f"item {index}", "unread": index < 2} for index in range(9)]
screen = notification_list_screen(
    {"data": {"summary": {"unreadCount": 2}, "items": items}},
    {item["id"]: f"ref{index}" for index, item in enumerate(items)},
    platform="telegram",
    site_url="https://example.invalid",
)
assert len(screen.keyboard["inline_keyboard"]) == 8
detail = notification_detail_screen(
    {"title": "item", "body": "body"},
    "abcdefghijklmnop",
    platform="telegram",
    is_owner=False,
    show_mark_read=False,
)
assert "notification-read:" not in str(detail.keyboard)
PY
printf 'TELEGRAM_RELEASE=%s\n' "$(basename "$(readlink -f /opt/integrated-dent/telegram/current)")"
printf 'BALE_RELEASE=%s\n' "$(basename "$(readlink -f /opt/integrated-dent/bale/current)")"
printf 'BOT_RUNTIME_CHECK_OK\n'
'@
[IO.File]::WriteAllText($localCheck, $check, [Text.UTF8Encoding]::new($false))
try {
    & scp @scpOptions $localCheck "${target}:$remoteCheck"
    if ($LASTEXITCODE -ne 0) { throw 'Could not transfer runtime check.' }
    & ssh @sshOptions $target "sudo bash '$remoteCheck'"
    if ($LASTEXITCODE -ne 0) { throw 'Iran bot runtime check failed.' }
}
finally {
    & ssh @sshOptions $target "sudo rm -f -- '$remoteCheck'" 2>$null | Out-Null
    Remove-Item -LiteralPath $temporaryRoot -Recurse -Force -ErrorAction SilentlyContinue
}
