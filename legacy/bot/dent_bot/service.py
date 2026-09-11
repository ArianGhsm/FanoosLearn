from __future__ import annotations

from .api import TelegramBotApi
from .app import DentBotApp
from .bot_home_classops_ux_v2 import install_bot_home_classops_ux_v2
from .bot_home_classops_ux_v2_compat import install_bot_home_classops_ux_v2_compat
from .class_operations import install_class_operations_product
from .classops_ux_v3 import install_classops_ux_v3
from .config import load_settings
from .runtime import run_service
from .term7_group_management import install_term7_group_management


def main() -> int:
    settings = load_settings()
    api = TelegramBotApi(settings.token, proxy_url=settings.telegram_proxy_url)
    install_class_operations_product()
    install_term7_group_management()
    base_callback = DentBotApp._callback
    install_bot_home_classops_ux_v2()
    install_bot_home_classops_ux_v2_compat(base_callback=base_callback)
    install_classops_ux_v3()
    return run_service(settings=settings, api=api, platform_name="Telegram")


if __name__ == "__main__":
    raise SystemExit(main())
