#!/usr/bin/env bash
# Makes a standalone with the engine and runs it, in a container (see standalone-image.Containerfile) of the podman that
# tests/.env names (CONTAINER_CONNECTION): the engine, the core and the scripts are sent to it as a tar stream.
#
# Usage, from the repository root:   tests/Packaging/standalone-check.sh
# ENGINE=<folder> says where the engine is (default ../opensim-engine), KEEP=1 keeps the container, LOG=<file> keeps the output.

set -eo pipefail
cd "$(git rev-parse --show-toplevel)"
source tests/bootstrap.sh

engine=$(cd "${ENGINE:-../opensim-engine}" && pwd)
core=$(ls -d core/opensim-* 2>/dev/null | head -1)
[[ -n "$core" ]] || { echo "no core in core/: the scenario runs the one of the kit" >&2; exit 1; }

image=opensim-kit-standalone
podman build -q -t "$image" -f tests/Packaging/standalone-image.Containerfile tests/Packaging >/dev/null

stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/core" "$stage/engine"
ln -s "$PWD/$core" "$stage/core/$(basename "$core")"
for item in src includes resources vendor composer.json locale; do
    [[ -e "$engine/$item" ]] && ln -s "$engine/$item" "$stage/engine/$item"
done
for file in standalone-driver.php standalone-scenario.sh scenario-lib.sh; do
    ln -s "$PWD/tests/Packaging/$file" "$stage/$file"
done

COPYFILE_DISABLE=1 tar -chf - -C "$stage" . |
    podman run --rm -i --memory="${MEMORY:-2g}" "$image" \
        bash -c 'mkdir -p /work && tar -C /work -xf - --warning=no-unknown-keyword && bash /work/standalone-scenario.sh' |
    tee "${LOG:-/dev/null}"
