from __future__ import annotations

import os
import subprocess
import sys
import textwrap


def test_production_install_sequence_preserves_message_and_callback_contracts() -> None:
    script = textwrap.dedent(
        r'''
        from pathlib import Path
        from tempfile import TemporaryDirectory

        from dent_bot.app import DentBotApp
        from dent_bot.bot_home_classops_ux_v2 import install_bot_home_classops_ux_v2
        from dent_bot.bot_home_classops_ux_v2_compat import install_bot_home_classops_ux_v2_compat
        from dent_bot.class_operations import install_class_operations_product
        from dent_bot.state import BotState
        from dent_bot.term7_group_management import install_term7_group_management

        callback_calls = []
        message_calls = []

        def base_callback(self, callback, *, interaction_version=None):
            callback_calls.append((dict(callback), interaction_version))

        def base_message(self, message):
            message_calls.append(dict(message))

        DentBotApp._callback = base_callback
        DentBotApp._message = base_message

        install_class_operations_product()
        install_term7_group_management()
        canonical_callback = DentBotApp._callback
        install_bot_home_classops_ux_v2()
        install_bot_home_classops_ux_v2_compat(base_callback=canonical_callback)

        callback = {
            "id": "cb-runtime-regression",
            "data": "v1:not-a-v2-action",
            "from": {"id": 1402},
            "message": {
                "message_id": 11,
                "chat": {"id": 1402, "type": "private"},
            },
        }

        class Dummy:
            pass

        dummy = Dummy()
        DentBotApp._callback(dummy, callback, interaction_version=7)
        assert callback_calls == [(callback, 7)]

        with TemporaryDirectory() as temporary:
            state = BotState(Path(temporary) / "state.sqlite3")
            state.start_dialog(1402, "non-v2-dialog", "step", {"type": "exam"})
            dummy.state = state
            message = {
                "message_id": 12,
                "from": {"id": 1402},
                "chat": {"id": 1402, "type": "private"},
                "text": "runtime smoke",
            }
            DentBotApp._message(dummy, message)
            assert message_calls == [message]
            dialog = state.get_dialog(1402)
            assert dialog == state.dialog(1402)
            assert dialog["kind"] == "non-v2-dialog"
            assert dialog["step"] == "step"
            assert dialog["payload"] == {"type": "exam"}
        '''
    )
    env = dict(os.environ)
    result = subprocess.run(
        [sys.executable, "-c", script],
        env=env,
        capture_output=True,
        text=True,
        check=False,
    )
    assert result.returncode == 0, result.stdout + result.stderr
