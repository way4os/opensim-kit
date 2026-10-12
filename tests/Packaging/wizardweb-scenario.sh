#!/usr/bin/env bash
# The setup in a browser, on the tools alone (no core, no database): the real hub behind the real web server of the setup,
# driven with curl as a browser would. Run by SCENARIO=wizardweb-scenario.sh tests/Packaging/run, in the test container.

source /test/scenario-lib.sh
magiiic_repository

ts "tools"
apt_q install "$(deb opensim-rest-php)" "$(deb opensim-engine)" "$(deb opensim-helpers)" "$(deb opensim-tools)"
check "the tools are installed, with their user" "command -v opensim >/dev/null && getent passwd opensim >/dev/null"

# What a browser does: the cookie it was given, the page, an answer to the screen it shows
page() { curl -s -b /tmp/jar "$base/${1:-}"; }
screen_id() { grep -o 'name="id" value="[0-9]*"' | head -1 | grep -o '[0-9]*'; }
answer() { # id, then the fields of the answer: name=value...
    local id=$1 field args=(-d "id=$1")
    shift
    for field in "$@"; do args+=(--data-urlencode "$field"); done
    curl -s -b /tmp/jar -o /dev/null "${args[@]}" "$base/answer"
}

ts "the setup, in the browser"
(cd /tmp && runuser -u opensim -- opensim setup --web >/tmp/wizard.out 2>&1
    echo "exit code: $?" >>/tmp/wizard.out) &
wait_check 40 "the setup gives the address of its page" "grep -q 'http://127.0.0.1:[0-9]*/?t=' /tmp/wizard.out"
dump /tmp/wizard.out 8
url=$(grep -o 'http://127.0.0.1:[0-9]*/?t=[0-9a-f]*' /tmp/wizard.out | head -1)
base=${url%%/?t=*}
check "only this machine, only the user of the setup: the folder of the exchanges is closed to the others" "
    [ \"\$(stat -c '%a %U' /tmp/opensim-wizard-*)\" = '700 opensim' ]"
check "the address gives the cookie, and works once" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' -c /tmp/jar $url)\" = 303 ] &&
    [ \"\$(curl -s -o /dev/null -w '%{http_code}' $url)\" = 403 ]"
check "without the cookie, no page" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' $base/)\" = 403 ]"
check "a request that names another host is refused" "[ \"\$(curl -s -o /dev/null -w '%{http_code}' -b /tmp/jar -H 'Host: evil.example' $base/)\" = 403 ]"

page >/tmp/home.html
check "the hub shows its menu" "grep -q 'OpenSim — setup' /tmp/home.html && grep -q 'Add grid' /tmp/home.html"

answer "$(screen_id </tmp/home.html)" 'v[value]=add'
sleep 1
page >/tmp/home.html
check "a grid cannot be added without a core, the page says so above the menu" "grep -q 'Install an OpenSim core first' /tmp/home.html &&
    grep -q 'Add grid' /tmp/home.html"

answer "$(screen_id </tmp/home.html)" 'v[value]=quit'
wait_check 20 "the page that ends the setup" "page | grep -q 'The setup is finished'"
wait_check 20 "the setup ends well" "grep -q 'exit code: 0' /tmp/wizard.out"
check "nothing is left of the exchanges, the web server is stopped" "[ -z \"\$(ls -d /tmp/opensim-wizard-* 2>/dev/null)\" ] &&
    ! pgrep -f 'php -S 127.0.0.1' >/dev/null"

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
