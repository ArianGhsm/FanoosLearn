#!/usr/bin/env bash
set -euo pipefail

validation_root="${1:?validation root is required}"
memory_limit_kib="${2:-1048576}"

case "$validation_root" in
  /tmp/dent-booklet-benchmark-v5-validation-*) ;;
  *) echo "unsafe validation root" >&2; exit 64 ;;
esac

active="$(readlink -f /opt/integrated-dent/telegram/current)"
test -d "$active/dent_bot"
test -r "$active/dent_bot/assets/fonts/B_Nazanin_Bold.ttf"
test -x /opt/integrated-dent/telegram-venv/bin/python
test -x /opt/integrated-dent/forensic-venv/bin/python
command -v qpdf >/dev/null
command -v gs >/dev/null

chown -R dentbot:dentbot "$validation_root"

runuser -u dentbot -- env \
  PYTHONPATH="$active" \
  PYTHON_BIN=/opt/integrated-dent/telegram-venv/bin/python \
  DENT_BOT_BOOKLET_WATERMARK_FONT="$active/dent_bot/assets/fonts/B_Nazanin_Bold.ttf" \
  MEMORY_LIMIT_KIB="$memory_limit_kib" \
  PDF_NORMALIZER=none \
  bash "$validation_root/scripts/run_booklet_benchmark_suite.sh" \
    "$validation_root/scripts/benchmark_booklet_pipeline.py" \
    "$validation_root/benchmark-work" \
    server-v5 > "$validation_root/benchmark.json"

runuser -u dentbot -- env \
  PYTHONPATH="$active" \
  DENT_BOT_BOOKLET_WATERMARK_FONT="$active/dent_bot/assets/fonts/B_Nazanin_Bold.ttf" \
  MEMORY_LIMIT_KIB="$memory_limit_kib" \
  bash -c 'ulimit -v "$1"; exec "$2" "$3" --output-pdf "$4" --report "$5"' \
    _ "$memory_limit_kib" \
    /opt/integrated-dent/forensic-venv/bin/python \
    "$validation_root/scripts/forensic_attack_suite.py" \
    "$validation_root/server-v5.pdf" \
    "$validation_root/attack.json" > "$validation_root/attack.stdout.json"

python3 - "$validation_root/benchmark.json" "$validation_root/attack.json" <<'PY'
import json
import pathlib
import sys

benchmark = json.loads(pathlib.Path(sys.argv[1]).read_text(encoding="utf-8"))
attack = json.loads(pathlib.Path(sys.argv[2]).read_text(encoding="utf-8"))
assert benchmark.get("success") is True
assert attack.get("success") is True
print("SERVER_V5_BENCHMARK_ROWS=" + str(len(benchmark.get("results") or [])))
print("SERVER_V5_QPDF_AVAILABLE=" + str(bool((attack.get("pdfAttacks") or {}).get("qpdf-rewrite", {}).get("attack", {}).get("available"))).lower())
print("SERVER_V5_GHOSTSCRIPT_AVAILABLE=" + str(bool((attack.get("pdfAttacks") or {}).get("ghostscript-rewrite", {}).get("attack", {}).get("available"))).lower())
print("SERVER_V5_VALIDATION_OK")
PY

chmod 0644 "$validation_root/benchmark.json" "$validation_root/attack.json"
