#!/usr/bin/env bash
# The command line of the kit on a real core, in the container of cli-check.sh: a grid with its Robust and a simulator made from a
# setup file (the database and its account too, the owner of the estate), started by the setup, then seen, stopped, started and
# restarted by the commands, and the same for a standalone.

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
opensim() { php /work/kit/bin/opensim "$@"; }
login() {
    local member
    member() { printf '<member><name>%s</name><value><string>%s</string></value></member>' "$1" "$2"; }
    curl -s -X POST -H 'Content-Type: text/xml' "http://localhost:$1/" -d "<?xml version=\"1.0\"?><methodCall><methodName>login_to_simulator</methodName><params><param><value><struct>$(
        member first Ada
        member last Love
        member passwd "\$1\$$(printf 'secret123' | md5sum | cut -d' ' -f1)"
        member start last
        member channel test
        member version 1
        member mac 00:00:00:00:00:00
        member id0 0
        member agree_to_tos true
        member read_critical true
    )</struct></value></param></params></methodCall>"
}

ts "database"
mkdir -p /run/mysqld && chown mysql: /run/mysqld
(mysqld_safe >/tmp/mysql.log 2>&1 &)
for _ in $(seq 1 30); do mysqladmin ping >/dev/null 2>&1 && break; sleep 1; done

ts "a grid and its simulator, made from a file"
cat >/tmp/alpha.yaml <<'YAML'
grid:
  name: Alpha
  nick: alpha
  hostname: localhost
  web_url: https://localhost
  public_port: 8002
  private_port: 8003
  console: rest
  helpers: false
  database: { user: alpha, password: alphapw1 }
owner: { name: Ada Love, password: secret123, email: ada@example.org }
simulators:
  - name: Welcome
    http_port: 9000
    console: rest
    estate: { name: Alpha Estate, owner: Ada Love }
    regions:
      - { name: Welcome, port: 9001, roles: [default, default_hg, fallback] }
YAML
opensim import /tmp/alpha.yaml --apply >/tmp/alpha.out 2>&1
echo "exit code: $?" >>/tmp/alpha.out
dump /tmp/alpha.out 14
check "the setup ends well" "grep -q 'exit code: 0' /tmp/alpha.out"
wait_check 120 "the grid answers" "curl -fs http://localhost:8002/get_grid_info | grep -q '<gridname>Alpha</gridname>'"
check "its database and its account are made" "[ \"\$(mysql -BN -e \"SHOW DATABASES LIKE 'alpha_robust'\")\" = alpha_robust ]"
wait_check 60 "the owner signs in" "login 8002 | grep -q '<name>login</name><value><string>true</string>'"

ts "status, stop, start, restart"
opensim status >/tmp/status.out 2>&1
dump /tmp/status.out 6
check "status shows the Robust and the simulator up" "grep -q 'alpha .*robust' /tmp/status.out && grep -q 'alpha_welcome .*opensim' /tmp/status.out && ! grep -q 'down' /tmp/status.out"
opensim stop now alpha --simulators >/tmp/stop.out 2>&1
echo "exit code: $?" >>/tmp/stop.out
check "stop now stops the grid and its simulators" "grep -q 'exit code: 0' /tmp/stop.out && ! pgrep -f 'OpenSim.dll|Robust.dll' >/dev/null"
opensim status >/tmp/status.out 2>&1
check "status says they are down" "grep -q 'down' /tmp/status.out"
opensim start alpha --simulators >/tmp/start.out 2>&1
echo "exit code: $?" >>/tmp/start.out
dump /tmp/start.out 6
check "start brings the Robust, then the simulator" "grep -q 'exit code: 0' /tmp/start.out"
wait_check 120 "the owner signs in again" "login 8002 | grep -q '<name>login</name><value><string>true</string>'"
opensim restart now alpha_welcome >/tmp/restart.out 2>&1
echo "exit code: $?" >>/tmp/restart.out
check "restart now restarts the simulator" "grep -q 'exit code: 0' /tmp/restart.out"
opensim api GET /grids/alpha/monitor format=text >/tmp/monitor.out 2>&1
dump /tmp/monitor.out 6
check "the monitor of the API shows the grid and its regions" "grep -q 'alpha' /tmp/monitor.out && grep -q 'robust' /tmp/monitor.out"

ts "a standalone, from a file"
mysql -e "DROP DATABASE IF EXISTS solo; CREATE DATABASE solo"
cat >/tmp/solo.yaml <<'YAML'
grid:
  name: Solo
  nick: solo
  standalone: true
  hostname: localhost
  web_url: https://localhost
  public_port: 9100
  helpers: false
  database: { user: solo, password: solopw1 }
owner: { name: Ada Love, password: secret123 }
simulators:
  - name: Welcome
    console: rest
    estate: { name: Solo Estate, owner: Ada Love }
    regions:
      - { name: Solo Welcome, port: 9101, roles: [default, default_hg, fallback] }
YAML
opensim import /tmp/solo.yaml --apply >/tmp/solo.out 2>&1
echo "exit code: $?" >>/tmp/solo.out
dump /tmp/solo.out 10
check "the setup ends well" "grep -q 'exit code: 0' /tmp/solo.out"
wait_check 120 "the standalone answers and its owner signs in" "login 9100 | grep -q '<name>login</name><value><string>true</string>'"
opensim stop now solo >/tmp/solo-stop.out 2>&1
check "stop by the name of the standalone" "! pgrep -f 'solo_welcome' >/dev/null"

pkill -f 'OpenSim.dll|Robust.dll'
exit $FAILED
