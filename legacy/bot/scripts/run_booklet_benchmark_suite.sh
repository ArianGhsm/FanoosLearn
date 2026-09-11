#!/usr/bin/env bash
set -euo pipefail

benchmark_script="${1:?benchmark script path is required}"
work_root="${2:?work root is required}"
label="${3:-current}"
python_bin="${PYTHON_BIN:-/opt/integrated-dent/telegram-venv/bin/python}"
qpdf_bin="${QPDF_BIN:-/usr/bin/qpdf}"
memory_limit_kib="${MEMORY_LIMIT_KIB:-1048576}"
normalizer="${PDF_NORMALIZER:-none}"

case "$work_root" in
  /tmp/dent-booklet-benchmark-*) ;;
  *) echo "unsafe benchmark work root" >&2; exit 64 ;;
esac

umask 077
mkdir -p "$work_root"
ulimit -v "$memory_limit_kib"
results="$work_root/results.jsonl"
: > "$results"

"$python_bin" "$benchmark_script" fixture --output "$work_root/mixed-8.pdf" --pages 8 --profile mixed >/dev/null
"$python_bin" "$benchmark_script" fixture --output "$work_root/mixed-40.pdf" --pages 40 --profile mixed >/dev/null
"$python_bin" "$benchmark_script" fixture --output "$work_root/mixed-80.pdf" --pages 80 --profile mixed >/dev/null

for fixture in mixed-8 mixed-40 mixed-80; do
  timeout 240s "$python_bin" "$benchmark_script" watermark \
    --input "$work_root/$fixture.pdf" \
    --output "$work_root/$fixture-watermarked.pdf" \
    --qpdf "$qpdf_bin" --normalizer "$normalizer" --label "$label-$fixture" >> "$results"
done

for scenario in cache-hit same-document; do
  timeout 240s "$python_bin" "$benchmark_script" load \
    --input "$work_root/mixed-8.pdf" --scenario "$scenario" \
    --requests 20 --workers 1 --queue-size 24 --qpdf "$qpdf_bin" \
    --normalizer "$normalizer" --label "$label-$scenario-w1" >> "$results"
done

for workers in 1 2; do
  timeout 480s "$python_bin" "$benchmark_script" load \
    --input "$work_root/mixed-8.pdf" --scenario distinct-documents \
    --requests 20 --workers "$workers" --queue-size 24 --qpdf "$qpdf_bin" \
    --normalizer "$normalizer" --label "$label-distinct-w$workers" >> "$results"
done

timeout 600s "$python_bin" "$benchmark_script" load \
  --input "$work_root/mixed-40.pdf" --scenario distinct-documents \
  --requests 20 --workers 1 --queue-size 24 --qpdf "$qpdf_bin" \
  --normalizer "$normalizer" --label "$label-distinct-large-w1" >> "$results"

"$python_bin" - "$results" <<'PY'
import json
import pathlib
import sys

path = pathlib.Path(sys.argv[1])
rows = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
print(json.dumps({"success": True, "results": rows}, ensure_ascii=False, sort_keys=True, separators=(",", ":")))
PY
