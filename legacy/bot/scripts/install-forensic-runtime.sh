#!/usr/bin/env bash
set -euo pipefail

forensic_requirements="${1:?forensic requirements path is required}"
pdf_requirements="${2:?PDF requirements path is required}"
venv_root="/opt/integrated-dent/forensic-venv"

case "$forensic_requirements:$pdf_requirements" in
  /tmp/integrated-dent-forensic-*:/tmp/integrated-dent-forensic-*) ;;
  *) echo "unsafe forensic installer input" >&2; exit 64 ;;
esac

sudo -n apt-get update -qq
sudo -n env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ghostscript python3-venv >/dev/null
sudo -n python3 -m venv "$venv_root"
sudo -n "$venv_root/bin/python" -m pip install --disable-pip-version-check --no-cache-dir \
  -r "$pdf_requirements" -r "$forensic_requirements"
sudo -n chown -R root:root "$venv_root"
sudo -n chmod -R a+rX,go-w "$venv_root"

"$venv_root/bin/python" - <<'PY'
import cv2
import numpy
import pymupdf
assert cv2.__version__
assert numpy.__version__
assert pymupdf.__version__
PY
gs --version >/dev/null
printf 'FORENSIC_RUNTIME_READY=true\n'
