#!/usr/bin/env bash
# The environment of the tests, sourced by every script of tests/ that starts a container (and by the tools that start them for
# the tests). Nothing specific to a machine is written in the tests: what is, is in tests/.env (see tests/.env.example), which is
# made from the example the first time.
#
#   CONTAINER_CONNECTION  the podman connection every container of the tests goes to, the machine that has the CPU and not the
#                         one the tests are started from. Every podman command that follows goes there, whoever runs it.
#   MEMORY                the memory of the container of a scenario
#
# Usage: source tests/bootstrap.sh   (from anywhere in the repository)

TESTS_ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
[[ -f "$TESTS_ROOT/vendor/bin/bash-helpers" ]] || { echo "bash-tools is not installed: composer install" >&2; exit 1; }
source "$TESTS_ROOT/vendor/bin/bash-helpers"

if [[ ! -f "$TESTS_ROOT/tests/.env" && -f "$TESTS_ROOT/tests/.env.example" ]]; then
    cp "$TESTS_ROOT/tests/.env.example" "$TESTS_ROOT/tests/.env"
    log "tests/.env made from tests/.env.example: edit it to run the containers on another machine"
fi

# The environment of the project (.env) and the one of the tests (tests/.env)
ENV_SLUG=tests read_env || true
if [[ -n "${CONTAINER_CONNECTION:-}" ]]; then
    export CONTAINER_CONNECTION
    log "Podman connection: $CONTAINER_CONNECTION"
fi
