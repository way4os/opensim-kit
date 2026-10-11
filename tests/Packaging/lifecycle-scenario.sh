#!/usr/bin/env bash
# The launcher on a real machine (systemd, ps, screen, runuser) with a dotnet that is a fake (lifecycle/dotnet-fake): opensim
# start, status and stop for a grid and its simulator, through the command line and the service. Run by tests/Packaging/run:
#   SCENARIO=lifecycle-scenario.sh tests/Packaging/run

source /test/scenario-lib.sh
tools="$(deb opensim-rest-php) $(deb opensim-engine) $(deb opensim-helpers) $(deb opensim-tools)"
robust_pid() { pgrep -f "^dotnet .*Robust.dll" | head -1; }
sim_pid() { pgrep -f "^dotnet .*OpenSim.dll" | head -1; }

# What a command printed, indented, to read why a check failed
shown() {
    echo "   --- $1"
    sed 's/^/   | /' "/tmp/$1"
    ps -axo pid,user,args | grep -E 'dotnet|SCREEN' | grep -v grep | cut -c1-200 | sed 's/^/   ps /'
    runuser -u opensim -- php -r 'require "/usr/share/opensim-tools/vendor/autoload.php"; $raw = (string) shell_exec("ps -axo pid=,uname=,pcpu=,pmem=,rss=,args= 2>&1"); echo "   raw: ", strlen($raw), " bytes, ", count(OpenSim\Runtime\SystemProcessTable::parse($raw)), " parsed, table ", count((new OpenSim\Runtime\SystemProcessTable())->all()), "\n"; foreach (explode("\n", $raw) as $l) { if (str_contains($l, "dotnet")) echo "   raw line: ", substr($l, 0, 160), "\n"; }' 2>&1
}

magiiic_repository

ts "a core that is a fake, the tools"
mkdir -p /usr/share/opensim/0.9.3.0/bin
touch /usr/share/opensim/0.9.3.0/bin/{OpenSim,Robust}.dll
for base in OpenSim Robust; do
    echo "<configuration><appender name=\"LogFileAppender\" type=\"log4net.Appender.FileAppender\"><file value=\"$base.log\" /></appender></configuration>" \
        >"/usr/share/opensim/0.9.3.0/bin/$base.exe.config"
done
install -m 755 /test/lifecycle/dotnet-fake /usr/local/bin/dotnet
touch /usr/lib/libgdiplus.so.0
apt_q install $tools
check "opensim account" "getent passwd opensim >/dev/null"

ts "a grid and a simulator, made by hand"
grid=/etc/opensim/grids/lc
mkdir -p $grid/sims/lcsim/regions
cat >$grid/lc.conf <<'INI'
[Grid]
GridName = Lc
CoreDirectory = /usr/share/opensim/0.9.3.0
INI
cat >$grid/Robust.HG.ini <<'INI'
[Const]
    PublicPort = 8002
    PrivatePort = 8003
[ServiceList]
    AssetServiceConnector = "8003/OpenSim.Server.Handlers.dll:AssetServiceConnector"
    GridServiceConnector = "8003/OpenSim.Server.Handlers.dll:GridServiceConnector"
INI
cat >$grid/sims/lcsim.ini <<'INI'
[Const]
    LogsDirectory = "/var/log/opensim"
[Startup]
    regionload_regionsdir = "/etc/opensim/grids/lc/sims/lcsim/regions"
[Network]
    http_listener_port = 9000
INI
printf '[Plaza]\nRegionUUID = 11111111-1111-4111-8111-111111111111\nInternalPort = 9001\n' >$grid/sims/lcsim/regions/Plaza.ini
chown -R opensim:opensim $grid
ln -s $grid/Robust.HG.ini /etc/opensim/robust.d/lc.ini
ln -s $grid/sims/lcsim.ini /etc/opensim/opensim.d/lcsim.ini
chown -h opensim:opensim /etc/opensim/robust.d/lc.ini /etc/opensim/opensim.d/lcsim.ini

ts "status, start, stop"
opensim status >/tmp/status0.out 2>&1
check "status lists the enabled instances, down" "grep -q '^lc .*robust .*\[down\]' /tmp/status0.out && grep -q '^lcsim .*opensim .*\[down\]' /tmp/status0.out && tail -1 /tmp/status0.out | grep -q '0 up, 2 down'"
opensim start lc >/tmp/start-lc.out 2>&1
code=$?
check "start lc: Robust comes up, ready, exit code 0" "[ $code = 0 ] && [ -n \"\$(robust_pid)\" ] && grep -q 'lc started' /tmp/start-lc.out && [ -z \"\$(sim_pid)\" ]"
pid=$(robust_pid)
opensim status >/tmp/status1.out 2>&1
shown status1.out
echo "   --- as opensim, directly"; runuser -u opensim -- /usr/share/opensim-tools/bin/opensim status 2>&1 | sed 's/^/   | /'
echo "   --- probe"; runuser -u opensim -- php /test/lifecycle/status-probe.php 2>&1 | sed 's/^/   | /'
echo "   --- without stdin"; opensim status </dev/null 2>&1 | sed 's/^/   | /'
check "status tells Robust runs, as the system user, the simulator does not" "grep -qE '^lc +robust +$pid +opensim' /tmp/status1.out && tail -1 /tmp/status1.out | grep -q '1 up, 1 down'"
opensim start lc >/tmp/start-lc2.out 2>&1
check "start lc again leaves it as it is" "[ \"\$(robust_pid)\" = '$pid' ] && [ \$(pgrep -fc '^dotnet .*Robust.dll') = 1 ]"
opensim start lcsim >/tmp/start-sim.out 2>&1
check "start lcsim: the simulator comes up, in a screen session" "[ -n \"\$(sim_pid)\" ] && grep -q 'lcsim started' /tmp/start-sim.out && runuser -u opensim -- screen -ls | grep -q '\\.lcsim'"
simpid=$(sim_pid)
opensim restart now lc >/tmp/restart-lc.out 2>&1
check "restart lc: Robust again, another process; the simulator was left alone" "[ -n \"\$(robust_pid)\" ] && [ \"\$(robust_pid)\" != '$pid' ] && [ \"\$(sim_pid)\" = '$simpid' ]"
opensim restart now --sims lc >/tmp/restart-all.out 2>&1
check "restart --sims lc: the simulator restarts with it" "[ -n \"\$(robust_pid)\" ] && [ -n \"\$(sim_pid)\" ] && [ \"\$(sim_pid)\" != '$simpid' ]"
opensim stop now lcsim >/tmp/stop-sim.out 2>&1
check "stop now lcsim: only the simulator goes" "[ -z \"\$(sim_pid)\" ] && [ -n \"\$(robust_pid)\" ]"
opensim stop now lc >/tmp/stop-lc.out 2>&1
shown stop-lc.out
check "stop now lc: Robust goes" "[ -z \"\$(robust_pid)\" ] && grep -q 'lc stopped' /tmp/stop-lc.out"
opensim stop now lc >/dev/null 2>&1
check "stop a Robust that does not run is no failure" "[ \$? = 0 ]"
opensim start nosuch >/tmp/start-nosuch.out 2>&1
check "start of what does not exist fails and says so" "[ \$? != 0 ] && grep -q 'no ini file found for nosuch' /tmp/start-nosuch.out"

ts "the service"
systemctl start opensim
check "the service starts every enabled instance" "[ -n \"\$(robust_pid)\" ] && [ -n \"\$(sim_pid)\" ]"
systemctl stop opensim
check "the service stops them" "[ -z \"\$(robust_pid)\" ] && [ -z \"\$(sim_pid)\" ]"

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
