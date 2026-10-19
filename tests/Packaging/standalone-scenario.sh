#!/usr/bin/env bash
# A standalone made by the engine, in the container of standalone-check.sh: the grid with no Robust, its only simulator
# started, the grid it answers for, the owner of the estate, and a second simulator refused.

source /work/scenario-lib.sh

mkdir -p /etc/opensim /work/etc /work/data /work/cache /work/logs /work/var
cat >/etc/opensim/opensim.conf <<CONF
[Defaults]
CoreRoot = /work/core
EtcRoot = /work/etc
VarRoot = /work/var
DataRoot = /work/data
CacheRoot = /work/cache
LogsRoot = /work/logs
CONF
grid=/work/etc/grids/solo
driver() { php /work/standalone-driver.php "$1" /work/engine 2>&1; echo "exit code: $?"; }

ts "database"
mkdir -p /run/mysqld && chown mysql: /run/mysqld
(mysqld_safe >/tmp/mysql.log 2>&1 &)
for _ in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done
mysql -e "CREATE DATABASE solo; CREATE USER 'os'@'localhost' IDENTIFIED BY 'pw'; GRANT ALL ON solo.* TO 'os'@'localhost';"

ts "the grid"
driver grid >/tmp/grid.out
check "the setup ends well" "grep -q 'exit code: 0' /tmp/grid.out"
check "it describes a standalone" "grep -q '^Standalone = true' $grid/solo.conf"
check "it has no Robust" "! ls $grid/Robust*.ini >/dev/null 2>&1"
check "it has the architecture of a standalone, with its own common settings" "[ -f $grid/config-include/StandaloneHypergrid.ini ] && [ -f $grid/config-include/StandaloneCommon.ini ] && [ ! -f $grid/config-include/GridCommon.ini ]"
check "the simulators read it from the master" "grep -q 'StandaloneHypergrid.ini' $grid/OpenSimGridDefaults.ini"
check "the storage of the distribution does not override the database" "grep -qE '^\s*;\s*Include-Storage' $grid/config-include/StandaloneCommon.ini"
dump /tmp/grid.out 8

ts "its simulator"
driver simulator >/tmp/simulator.out
check "the setup ends well" "grep -q 'exit code: 0' /tmp/simulator.out"
sim=$(ls $grid/sims/*.ini 2>/dev/null | head -1)
check "the simulator has the port of the grid, and the database of the grid" "grep -q 'http_listener_port = 9000' $sim && grep -q 'Database=solo' $sim"
check "the password of the owner is not left in its config" "! grep -q DefaultEstateOwnerPassword $sim"
# Only a simulator that was started is waited for
grep -q 'exit code: 0' /tmp/simulator.out && wait=120 || wait=1
wait_check $wait "the grid answers for itself" "curl -fs http://localhost:9000/get_grid_info | grep -q '<gridname>Solo</gridname>' && curl -fs http://localhost:9000/get_grid_info | grep -q '<gridnick>solo</gridnick>'"
hash=$(printf 'secret123' | md5sum | cut -d' ' -f1)
login() {
    local member
    member() { printf '<member><name>%s</name><value><string>%s</string></value></member>' "$1" "$2"; }
    curl -s -X POST -H 'Content-Type: text/xml' http://localhost:9000/ -d "<?xml version=\"1.0\"?><methodCall><methodName>login_to_simulator</methodName><params><param><value><struct>$(
        member first Ada
        member last Love
        member passwd "\$1\$$hash"
        member start last
        member channel test
        member version 1
        member mac 00:00:00:00:00:00
        member id0 0
        member agree_to_tos true
        member read_critical true
    )</struct></value></param></params></methodCall>"
}
login >/tmp/login.out
check "its owner signs in with the password the setup was given" "grep -q '<name>login</name><value><string>true</string>' /tmp/login.out"
grep -q 'login</name><value><string>true' /tmp/login.out || dump /tmp/login.out 12
check "the owner of the estate has its account" "[ \"\$(mysql -N solo -e \"SELECT COUNT(*) FROM UserAccounts WHERE FirstName = 'Ada' AND LastName = 'Love'\")\" = 1 ]"
check "the region is registered with the flags the setup gave it" "grep -q 'Region Welcome .* registered at 9000,9000 with flags DefaultRegion.*DefaultHGRegion' /work/logs/solo_welcome.log"
dump /tmp/simulator.out 12
if [ "$FAILED" != 0 ]; then
    curl -s http://localhost:9000/get_grid_info >/tmp/info.out 2>&1; dump /tmp/info.out 20
    ps aux | grep '[O]penSim.dll' | cut -c1-200
    ls -la /work/logs /work/cache | head -30
    for log in /work/logs/*.log; do tail -40 "$log" >/tmp/log.out; dump /tmp/log.out 40; done
    mysql -N solo -e "SHOW TABLES" | tr '\n' ' '
fi

ts "a second simulator"
driver again >/tmp/again.out
check "it is refused, and the grid is as it was" "grep -q 'problem: name' /tmp/again.out && [ \$(ls $grid/sims/*.ini | wc -l) = 1 ]"

ts "its monitor, its stop and its start"
php /work/standalone-driver.php monitor /work/engine >/tmp/monitor.out 2>&1
check "the monitor shows the simulator that is the whole grid, up, with its region" "grep -q 'solo_welcome *simulator *up *1/1' /tmp/monitor.out"
dump /tmp/monitor.out 6
driver stop >/tmp/stop.out
check "the grid is stopped with its simulator" "grep -q 'exit code: 0' /tmp/stop.out && ! pgrep -f 'inifile=/work/etc/opensim.d/solo_welcome.ini' >/dev/null"
php /work/standalone-driver.php monitor /work/engine >/tmp/monitor-down.out 2>&1
check "the monitor says it is down" "grep -q 'down' /tmp/monitor-down.out"
driver start >/tmp/start.out
wait_check 120 "the grid is back with its own accounts" "curl -fs http://localhost:9000/get_grid_info | grep -q '<gridname>Solo</gridname>'"
dump /tmp/stop.out 4

ts "its welcome message"
check "the login says the welcome of the grid" "grep -q 'Welcome to Solo, Ada Love!' /tmp/login.out"

ts "a standalone with the helpers, the hypergrid and the offline messages of the module V2"
mysql -e "CREATE DATABASE duo; GRANT ALL ON duo.* TO 'os'@'localhost';"
driver() { TEST_GRID=Duo TEST_NICK=duo TEST_PORT=9100 TEST_DB_NAME=duo TEST_HELPERS=1 TEST_OFFLINE=robust php /work/standalone-driver.php "$1" /work/engine 2>&1; echo "exit code: $?"; }
driver grid >/tmp/duo-grid.out
check "the grid is made, its common settings tell the viewers where the helpers are" "grep -q 'exit code: 0' /tmp/duo-grid.out && grep -q 'economy = \"\${Const|WebURL}/helpers\"' /work/etc/grids/duo/config-include/StandaloneCommon.ini"
driver simulator >/tmp/duo-simulator.out
check "its simulator is started" "grep -q 'exit code: 0' /tmp/duo-simulator.out"
wait_check 120 "get_grid_info says the helpers, the profile page and the gatekeeper" "curl -fs http://localhost:9100/get_grid_info >/tmp/duo-info.xml && grep -q '<economy>https://www.example.org/helpers</economy>' /tmp/duo-info.xml && grep -q '<web_profile_url>' /tmp/duo-info.xml && grep -q '<gatekeeper>http://localhost:9100</gatekeeper>' /tmp/duo-info.xml && grep -q '<search>https://www.example.org' /tmp/duo-info.xml"
log=$(ls /work/logs/duo_welcome.log 2>/dev/null)
check "the offline messages are kept by the simulator itself" "grep -q 'Offline messages enabled by Offline Message Module V2' $log"
check "the groups messaging runs" "grep -q 'GroupsMessagingModule enabled' $log"
check "no error in the log of the simulator" "! grep -q ' ERROR ' $log"
grep -E ' (ERROR|FATAL) ' $log | head -8 | cut -c1-220
dump /tmp/duo-info.xml 4

pkill -f OpenSim.dll
exit $FAILED
