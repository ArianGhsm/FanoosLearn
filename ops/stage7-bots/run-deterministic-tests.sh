#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
python3 -m compileall -q packages/python apps tests/bots tests/workers
PYTHONPATH=packages/python python3 -m unittest discover -s tests/bots -p 'test_*.py' -v
PYTHONPATH=packages/python python3 -m unittest discover -s tests/workers -p 'test_*.py' -v
