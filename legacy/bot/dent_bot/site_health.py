from __future__ import annotations

import json

from .config import load_settings
from .site_api import SiteApiClient, SiteApiError


def check() -> dict[str, object]:
    settings = load_settings()
    client = SiteApiClient(
        settings.site_api_url,
        settings.site_service_secret,
        platform=settings.platform,
        relay_secret=settings.site_relay_secret,
    )
    result = client.account(settings.owner_id)
    return {
        "ready": result.get("success") is True and result.get("linked") is True,
        "signed_site_api": result.get("success") is True,
        "owner_account_linked": result.get("linked") is True,
    }


def main() -> int:
    try:
        result = check()
    except SiteApiError as error:
        result = {
            "ready": False,
            "signed_site_api": False,
            "owner_account_linked": False,
            "error": error.code,
            "http_status": error.status,
            "message": str(error)[:120],
        }
    except (OSError, ValueError):
        result = {
            "ready": False,
            "signed_site_api": False,
            "owner_account_linked": False,
            "error": "site-health-check-failed",
        }
    print(json.dumps(result, ensure_ascii=False))
    return 0 if result["ready"] is True else 2


if __name__ == "__main__":
    raise SystemExit(main())
