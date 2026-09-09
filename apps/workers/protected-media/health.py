#!/usr/bin/env python3
from __future__ import annotations

import shutil

from runtime import build


REQUIRED_BINARIES = ("qpdf", "pdfinfo", "pdftoppm")


def main() -> None:
    missing = [name for name in REQUIRED_BINARIES if shutil.which(name) is None]
    try:
        from PIL import Image, features  # noqa: F401

        pillow = True
        raqm = bool(features.check("raqm"))
    except Exception:
        pillow = False
        raqm = False

    if missing or not pillow or not raqm:
        print(
            "FAIL protected-media dependencies",
            {"missing": missing, "pillow": pillow, "raqm": raqm},
        )
        raise SystemExit(1)

    try:
        api, processor = build()
        if api is None or processor is None:
            raise RuntimeError("protected-media runtime build returned incomplete components")
    except Exception as exc:
        print("FAIL protected-media health", type(exc).__name__)
        raise SystemExit(1) from exc

    print("OK protected-media dependencies/configuration")


if __name__ == "__main__":
    main()
