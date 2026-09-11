#!/usr/bin/env bash
set -euo pipefail

environment_file="$1"
release_root="$2"
probe_script="$3"
platform="$4"

set -a
# shellcheck disable=SC1090 -- production path is supplied by the verified wrapper.
. "$environment_file"
set +a
cd "$release_root"
PYTHONPATH="$release_root" python3 "$probe_script" --platform "$platform"
