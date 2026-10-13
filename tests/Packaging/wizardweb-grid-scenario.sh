#!/usr/bin/env bash
# A grid made in the browser, from the first page to the last, on a real install: the core, the database server, a person who is not
# the user of the install (the writing is handed to opensim with sudo, as on a real machine) and who has the administrator access
# of the database. The browser is web-browser-driver.php. Run by SCENARIO=wizardweb-grid-scenario.sh tests/Packaging/run, in the
# test container.

source /test/scenario-lib.sh

# The core, the .NET runtime and the person who runs the setup are prepared once (PREPARE=wizardweb-grid-prepare.sh in tests/Packaging/run
# keeps them in an image); without it they are made here
[[ -d /home/tester ]] || bash /test/wizardweb-grid-prepare.sh || exit 1

ts "the packages of the tools, which change from a run to the next"
apt_q install "$(deb opensim-rest-php)" "$(deb opensim-engine)" "$(deb opensim-helpers)" "$(deb opensim-tools)"
systemctl start mariadb
check "the tools are the ones of this run" "[ \"\$(dpkg-query -W -f='\${Version}' opensim-tools)\" = \"\$(dpkg-deb -f \$(deb opensim-tools) Version)\" ]"

# The person administers the database server with her own account
mysql -e "CREATE USER tester@localhost IDENTIFIED VIA unix_socket; GRANT ALL ON *.* TO tester@localhost WITH GRANT OPTION;"

# An account of the database that exists already, with a password the setup cannot guess: it asks for it for the database of the grid,
# and must not ask again for the one of the simulator (the same account)
mysql -e "CREATE USER opensim@localhost IDENTIFIED BY 'knownpw12';"

ts "the setup, in the browser"
# Over SSH (here said by SSH_CONNECTION: the client address and port, then the server's), the page is open to the network for the address the
# person connected from, and given at the address they connected to
(cd /tmp && runuser -u tester -- env SSH_CONNECTION='127.0.0.1 40000 127.0.0.1 22' opensim setup --web >/tmp/wizard.out 2>&1
    echo "exit code: $?" >>/tmp/wizard.out) &
wait_check 40 "the setup gives the address of its page" "grep -q 'http://127.0.0.1:[0-9]*/?t=' /tmp/wizard.out"
url=$(grep -o 'http://127.0.0.1:[0-9]*/?t=[0-9a-f]*' /tmp/wizard.out | head -1)
port=${url#http://*:}
port=${port%%/*}
ip=$(hostname -I 2>/dev/null | awk '{print $1}')
check "over SSH the server is open to the network: it listens on every interface" "grep -qi \"^ *[0-9]*: 00000000:\$(printf %04X $port) 00000000:0000 0A\" /proc/net/tcp"
check "only the address the person connected from is let in: another address of this machine is refused before anything else" "[ -z \"$ip\" ] || [ \"\$(curl -s http://$ip:$port/)\" = Forbidden ]"
check "the address let in reaches the server by any name, and is asked for the address of the page" "curl -s -H 'Host: grid.example.org' http://127.0.0.1:$port/ | grep -q 'cannot be used'"
php /test/web-browser-driver.php "$url" /test/quick-setup-fixture.php >/tmp/browser.out 2>&1
echo "exit code: $?" >>/tmp/browser.out
fold -s -w 200 /tmp/browser.out | sed 's/^/   | /' | head -60
check "the browser went through the setup to its last page" "grep -q '^done: ' /tmp/browser.out && grep -q 'exit code: 0' /tmp/browser.out"
pages=$(sed -n 's/^pages: //p' /tmp/browser.out)
check "a grid and its first region need a handful of pages ($pages), not a page per question" "[ \"${pages:-99}\" -le 10 ]"
check "the password of the account is asked once, for the database of the grid: the simulator uses it" "[ \"\$(grep -c '^page [0-9]*: Database' /tmp/browser.out)\" = 1 ]"
check "what the setup is about to create is a note, not a warning" "! grep -q '^warning: .*\(does not exist\|has no access\)' /tmp/browser.out"
wait_check 20 "the setup ends well, in the terminal too" "grep -q 'exit code: 0' /tmp/wizard.out"
dump /tmp/wizard.out 40

ts "what the setup made"
grid=/etc/opensim/grids/web_grid
check "the grid is made, with the name typed in the browser, by the user of the install" "grep -q 'Web Grid' $grid/Robust.HG.ini &&
    [ \"\$(stat -c %U $grid/Robust.HG.ini)\" = opensim ]"
check "the simulator and its region are made" "ls $grid/sims/*.ini >/dev/null 2>&1 && ls $grid/sims/*/regions/*.ini >/dev/null 2>&1"
check "the database is made, by the administrator access of the person" "[ \"\$(mysql -BN -e \"SHOW DATABASES LIKE 'web_grid_robust'\")\" = web_grid_robust ]"
check "the setup is kept for the group of the install" "[ \"\$(stat -c '%a %U:%G' $grid/setup.json)\" = '640 opensim:opensim' ]"
check "what the writing says (it is done as opensim) is on the page, not in the terminal" "grep -q \"configured\" /tmp/browser.out &&
    ! grep -q 'configured' /tmp/wizard.out"
# The quick setup enables and starts what it makes: Robust, then the simulator and its region, started by the commands of the setup
wait_check 300 "Robust of the grid is running" "pgrep -f '^dotnet .*Robust.dll' >/dev/null"
wait_check 400 "the region of the first simulator registers in the grid" "grep -q 'Region Welcome .* registered at 8002,8002' /var/log/opensim/web_grid_welcome.log"
check "nothing is left of the exchanges, the web server is stopped" "[ -z \"\$(ls -d /tmp/opensim-wizard-* 2>/dev/null)\" ] && ! pgrep -f 'php -S 127.0.0.1' >/dev/null"

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
