#!/usr/bin/env bash
# A grid made in the browser, from the first page to the last, on a real install: the core, the database server, a person who is not
# the user of the install (the writing is handed to opensim with sudo, as on a real machine) and who has the administrator access
# of the database. The browser is web-browser-driver.php. Run by SCENARIO=wizardweb-grid-scenario.sh tests/Packaging/run, in the
# test container.

source /test/scenario-lib.sh
magiiic_repository

ts "packages"
apt_q install "$(deb opensim-0.9.3.0)" "$(deb opensim-rest-php)" "$(deb opensim-engine)" "$(deb opensim-helpers)" "$(deb opensim-tools)"
apt_q install sudo
systemctl start mariadb
check "the core and the tools are installed" "[ -f /usr/share/opensim/0.9.3.0/bin/OpenSim.exe ] && command -v opensim >/dev/null"

ts dotnet
opensim install-dotnet -y 2>&1 | tail -1
check ".NET runtime" "runuser -u opensim -- dotnet --list-runtimes | grep -q NETCore.App"

# A person who runs the setup: may act as opensim (sudo), and administers the database server with her own account
useradd -m tester
echo 'tester ALL=(opensim) NOPASSWD: ALL' >/etc/sudoers.d/tester
chmod 440 /etc/sudoers.d/tester
mysql -e "CREATE USER tester@localhost IDENTIFIED VIA unix_socket; GRANT ALL ON *.* TO tester@localhost WITH GRANT OPTION;"

ts "the setup, in the browser"
(cd /tmp && runuser -u tester -- opensim setup --web >/tmp/wizard.out 2>&1
    echo "exit code: $?" >>/tmp/wizard.out) &
wait_check 40 "the setup gives the address of its page" "grep -q 'http://127.0.0.1:[0-9]*/?t=' /tmp/wizard.out"
url=$(grep -o 'http://127.0.0.1:[0-9]*/?t=[0-9a-f]*' /tmp/wizard.out | head -1)
php /test/web-browser-driver.php "$url" /test/quick-setup-fixture.php >/tmp/browser.out 2>&1
echo "exit code: $?" >>/tmp/browser.out
fold -s -w 200 /tmp/browser.out | sed 's/^/   | /' | head -60
check "the browser went through the setup to its last page" "grep -q '^done: ' /tmp/browser.out && grep -q 'exit code: 0' /tmp/browser.out"
pages=$(sed -n 's/^pages: //p' /tmp/browser.out)
check "a grid and its first region need a handful of pages ($pages), not a page per question" "[ \"${pages:-99}\" -le 10 ]"
wait_check 20 "the setup ends well, in the terminal too" "grep -q 'exit code: 0' /tmp/wizard.out"
dump /tmp/wizard.out 12

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
