#!/usr/bin/env bash
# Runs the command line of the kit on a real core, in a container (see standalone-image.Containerfile) of the podman that
# tests/.env names (CONTAINER_CONNECTION): the kit, the engine and the core are sent to it as a tar stream, no package.
#
# Usage, from the repository root:   tests/Packaging/cli-check.sh
# ENGINE=<folder> says where the engine is (default ../opensim-engine), LOG=<file> keeps the output.

set -eo pipefail
cd "$(git rev-parse --show-toplevel)"
source tests/bootstrap.sh

core=$(ls -d core/opensim-* 2>/dev/null | grep -v zip | head -1)
[[ -n "$core" ]] || { echo "no core in core/" >&2; exit 1; }

image=opensim-kit-standalone
podman build -q -t "$image" -f tests/Packaging/standalone-image.Containerfile tests/Packaging >/dev/null

stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir -p "$stage/core" "$stage/kit"
ln -s "$PWD/$core" "$stage/core/$(basename "$core")"
for item in bin bootstrap.php src vendor share locales composer.json config etc .version; do
    [[ -e "$item" ]] && ln -s "$PWD/$item" "$stage/kit/$item"
done
for file in cli-scenario.sh scenario-lib.sh; do
    ln -s "$PWD/tests/Packaging/$file" "$stage/$file"
done

COPYFILE_DISABLE=1 tar -chf - -C "$stage" \
    --exclude=.git --exclude=tmp --exclude=tests --exclude=docs --exclude=dist --exclude=build --exclude=opensim-helpers \
    --exclude='opensim-engine/vendor' --exclude=node_modules . |
    podman run --rm -i --memory="${MEMORY:-2g}" "$image" \
        bash -c 'mkdir -p /work && tar -C /work -xf - --warning=no-unknown-keyword && bash /work/cli-scenario.sh' |
    tee "${LOG:-/dev/null}"
