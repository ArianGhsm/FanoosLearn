#!/usr/bin/env python3
"""Audit or normalize identity-auth v2 state without exposing PII."""

from __future__ import annotations

import argparse
import json

from dent_bot.site_api import SiteApiClient


def site_client(platform: str) -> tuple[object, SiteApiClient]:
    if platform == "bale":
        from dent_bot.bale_config import load_settings
    else:
        from dent_bot.config import load_settings
    settings = load_settings()
    client = SiteApiClient(
        settings.site_api_url,
        settings.site_service_secret,
        platform=settings.platform,
        timeout=12.0,
        relay_secret=settings.site_relay_secret,
    )
    return settings, client


def audit_auth_v2(platform: str) -> int:
    settings, client = site_client(platform)
    status = client.identity_auth_v2_status(settings.owner_id)
    print(json.dumps({
        "pendingManualClaims": int(status.get("pendingManualClaims") or 0),
        "platform": settings.platform,
        "mappings": int(status.get("mappings") or 0),
        "pendingProfileEdits": int(status.get("pendingProfileEdits") or 0),
    }, separators=(",", ":")))
    return 0


def normalize_auth_v2(platform: str) -> int:
    settings, client = site_client(platform)
    payload = client.normalize_identity_auth_v2(settings.owner_id)
    result = payload.get("result") if isinstance(payload.get("result"), dict) else {}
    allowed = {
        key: int(result.get(key) or 0)
        for key in (
            "pendingRejected",
            "approvedMigrated",
            "approvedLinkedFromClaim",
            "approvedRejectedWithoutLink",
            "approvedRejectedConflict",
            "candidatesRetired",
            "profilesEnsured",
        )
    }
    print(json.dumps(allowed, separators=(",", ":")))
    return 0


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser(description=__doc__)
    result.add_argument("command", choices=("audit-v2", "normalize-v2"))
    result.add_argument("--platform", choices=("telegram", "bale"), default="telegram")
    return result


def main() -> int:
    args = parser().parse_args()
    return audit_auth_v2(args.platform) if args.command == "audit-v2" else normalize_auth_v2(args.platform)


if __name__ == "__main__":
    raise SystemExit(main())
