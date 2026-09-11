from __future__ import annotations

import base64
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


class TelegramEgressStaticTests(unittest.TestCase):
    def test_refresh_is_ten_minute_persistent_and_resource_bounded(self) -> None:
        installer = (ROOT / "scripts" / "install-telegram-egress-iran.ps1").read_text(encoding="utf-8")
        self.assertIn("install -d -o root -g root -m 0711 /etc/integrated-dent", installer)
        self.assertIn("OnCalendar=*-*-* *:0/10:00 Asia/Tehran", installer)
        self.assertIn("RandomizedDelaySec=30s", installer)
        self.assertIn("Persistent=true", installer)
        self.assertIn("TimeoutStartSec=20m", installer)
        self.assertIn("Nice=10", installer)
        self.assertIn("IOSchedulingClass=idle", installer)
        self.assertIn("flock -n 9", installer)
        self.assertIn('Publish-DentDeployLifecycle -Service integrated-ops -Status started', installer)
        self.assertIn('Publish-DentDeployLifecycle -Service integrated-ops -Status succeeded', installer)
        self.assertIn('"backup-vps-state.ps1"', installer)
        self.assertNotIn("xray version | head", installer)

    def test_shared_environment_directory_remains_traversable_to_service_groups(self) -> None:
        expected = "install -d -o root -g root -m 0711"
        targets = {
            "scripts/deploy-bale-to-iran.ps1": "/etc/integrated-dent",
            "scripts/install-telegram-egress-iran.ps1": "/etc/integrated-dent",
            "ops/install-deploy-notifier.sh": '"$ENV_ROOT"',
            "scripts/restore-vps-state-to-server.ps1": "/etc/integrated-dent",
        }
        forbidden = (
            "-m 0700 /etc/integrated-dent",
            "-m 0750 -o root -g www-data /etc/integrated-dent",
            '-m 0700 "$ENV_ROOT"',
        )
        for relative, suffix in targets.items():
            text = (ROOT / relative).read_text(encoding="utf-8")
            with self.subTest(relative=relative):
                self.assertIn(f"{expected} {suffix}", text)
                for unsafe in forbidden:
                    self.assertNotIn(unsafe, text)

    def test_refresh_keeps_old_config_on_failure_and_avoids_needless_restart(self) -> None:
        installer = (ROOT / "scripts" / "install-telegram-egress-iran.ps1").read_text(encoding="utf-8")
        selector = (ROOT / "scripts" / "select-xray-telegram-egress.py").read_text(encoding="utf-8")
        self.assertIn("set -euo pipefail", installer)
        self.assertLess(installer.index("select-telegram-egress.py"), installer.index("selected_hash="))
        self.assertIn('if test "$selected_hash" != "$current_hash"; then', installer)
        self.assertIn("live-post-activation-probe", installer)
        self.assertIn('install -o root -g dentegress -m 0640 "$previous_config"', installer)
        self.assertIn("listener_ready=0", installer)
        self.assertIn("for wait_attempt in $(seq 1 50)", installer)
        self.assertIn("https://api.telegram.org/bot0:invalid/getMe", installer)
        self.assertIn("temporary_output.replace(args.output)", selector)
        self.assertLess(selector.index("if stable is None:"), selector.index("temporary_output.replace(args.output)"))

    def test_runbook_records_subscription_refetch_and_real_api_probes(self) -> None:
        runbook = (ROOT / "docs" / "TELEGRAM_IRAN_EGRESS.md").read_text(encoding="utf-8")
        self.assertIn("every ten minutes", runbook)
        self.assertIn("downloads both subscriptions again", runbook)
        self.assertIn("last working configuration stays in place", runbook)

    def test_telegram_deploy_restarts_the_real_process_and_preserves_live_state(self) -> None:
        deploy = (ROOT / "scripts" / "deploy-telegram-to-iran.ps1").read_text(encoding="utf-8")
        self.assertIn("systemctl restart integrated-dent-bot.service", deploy)
        self.assertIn('readlink -f "/proc/$main_pid/cwd"', deploy)
        self.assertIn('= "$release_root"', deploy)
        self.assertNotIn("systemctl enable --now integrated-dent-bot.service", deploy)
        self.assertNotIn("systemctl disable --now integrated-dent-bot.service", deploy)
        self.assertIn("if ! test -s /var/lib/integrated-dent/dent-bot/state.sqlite3; then", deploy)
        self.assertIn("if ! test -s /etc/integrated-dent/dent-bot.env; then", deploy)
        self.assertIn("DENT_BOT_REQUIRED_CHANNEL_USERNAME=Dent1402Booklets", deploy)
        self.assertIn("requirements-bot-pdf.txt", deploy)
        self.assertIn("DENT_BOT_BOOKLET_FINGERPRINT_KEY", deploy)
        self.assertIn("/opt/integrated-dent/telegram-venv/bin/python", deploy)

    def test_runtime_check_rejects_a_stale_process_and_probes_real_owner_start(self) -> None:
        check = (ROOT / "scripts" / "check-iran-bot-runtime.ps1").read_text(encoding="utf-8")
        self.assertIn('test "$telegram_process_cwd" = "$telegram_current"', check)
        self.assertIn('test "$bale_process_cwd" = "$bale_current"', check)
        self.assertIn('owner_start_gate = "class-auth-reconnect"', check)
        self.assertIn('owner_site_connect_button = True', check)
        self.assertIn('owner_start_gate = "authenticated-home"', check)
        self.assertIn('"persian_digit_boundary": True', check)
        self.assertIn("tehran_azad_missing_from_keyboard", check)

    def test_all_bale_activation_paths_reject_stale_processes(self) -> None:
        for relative in (
            "scripts/deploy-bale-code-to-iran.ps1",
            "scripts/deploy-bale-to-iran.ps1",
        ):
            deploy = (ROOT / relative).read_text(encoding="utf-8")
            with self.subTest(relative=relative):
                self.assertIn("systemctl restart integrated-dent-bale-bot.service", deploy)
                self.assertIn('readlink -f "/proc/$main_pid/cwd"', deploy)
                self.assertIn('= "$release_root"', deploy)

        full = (ROOT / "scripts" / "deploy-bale-to-iran.ps1").read_text(encoding="utf-8")
        self.assertNotIn("systemctl enable --now integrated-dent-bale-bot.service", full)
        self.assertNotIn("systemctl disable --now integrated-dent-bale-bot.service", full)
        self.assertIn("if ! test -s /var/lib/integrated-dent/bale-bot/state.sqlite3; then", full)
        self.assertIn("if ! test -s /etc/integrated-dent/bale-bot.env; then", full)

    def test_disaster_restore_restarts_bots_and_asserts_active_release_cwd(self) -> None:
        restore = (ROOT / "scripts" / "restore-vps-state-to-server.ps1").read_text(encoding="utf-8")
        self.assertIn("systemctl restart integrated-dent-bot.service", restore)
        self.assertIn("systemctl restart integrated-dent-bale-bot.service", restore)
        self.assertIn('readlink -f "/proc/$telegram_pid/cwd"', restore)
        self.assertIn('readlink -f "/proc/$bale_pid/cwd"', restore)
        self.assertNotIn("systemctl enable --now integrated-dent-bot.service", restore)
        self.assertNotIn("systemctl enable --now integrated-dent-bale-bot.service", restore)

    def test_shared_bot_migration_asserts_both_new_and_rollback_process_cwds(self) -> None:
        deploy = (ROOT / "scripts" / "deploy-shared-payment-offers-to-iran.ps1").read_text(encoding="utf-8")
        self.assertIn('readlink -f "/proc/$telegram_pid/cwd"', deploy)
        self.assertIn('readlink -f "/proc/$bale_pid/cwd"', deploy)
        self.assertIn('= "$release_root"', deploy)
        self.assertIn('= "$previous_telegram"', deploy)
        self.assertIn('= "$previous_bale"', deploy)

    def test_shared_payment_deploy_is_idempotent_and_snapshots_before_destructive_rollback(self) -> None:
        deploy = (ROOT / "scripts" / "deploy-shared-payment-offers-to-iran.ps1").read_text(encoding="utf-8")
        self.assertNotIn('test ! -e "$shared_db"', deploy)
        self.assertIn('if test "$had_shared" -eq 1; then', deploy)
        self.assertIn('sqlite3 "$shared_db" ".backup', deploy)
        self.assertIn("shared_snapshot_ready=0", deploy)
        self.assertIn('if test "$shared_snapshot_ready" -eq 1; then', deploy)
        self.assertIn('install -o root -g dentcommerce -m 0660 "$rollback_root/payment-offers.sqlite3" "$shared_db"', deploy)

    def test_bot_deploy_rollback_restores_release_environment_and_unit(self) -> None:
        expectations = {
            "scripts/deploy-telegram-to-iran.ps1": (
                "dent-bot.env",
                "integrated-dent-bot.service",
            ),
            "scripts/deploy-bale-to-iran.ps1": (
                "bale-bot.env",
                "integrated-dent-bale-bot.service",
            ),
        }
        for relative, names in expectations.items():
            deploy = (ROOT / relative).read_text(encoding="utf-8")
            with self.subTest(relative=relative):
                self.assertIn("rollback_root=", deploy)
                self.assertIn(f'cp -a "$rollback_root/{names[0]}" /etc/integrated-dent/{names[0]}', deploy)
                self.assertIn(
                    f'cp -a "$rollback_root/{names[1]}" /etc/systemd/system/{names[1]}',
                    deploy,
                )
                self.assertIn('rm -rf -- "$rollback_root"', deploy)

    def test_telegram_deploy_transports_persian_source_title_as_ascii_safe_bytes(self) -> None:
        deploy = (ROOT / "scripts" / "deploy-telegram-to-iran.ps1").read_text(encoding="utf-8")
        encoded = "2KzYstmI2Ycg2K7YtdmI2LXbjCB8INiv2YbYr9in2YbigIzZvtiy2LTaqduMINiq2YfYsdin2YYg27HbtNuw27I="
        self.assertIn(encoded, deploy)
        self.assertEqual(base64.b64decode(encoded).decode("utf-8"), "جزوه خصوصی | دندان‌پزشکی تهران ۱۴۰۲")
        self.assertNotIn('DENT_BOT_BOOKLET_SOURCE_CHANNEL_TITLE="جزوه خصوصی', deploy)


if __name__ == "__main__":
    unittest.main()
