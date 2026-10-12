#!/usr/bin/env bash
# A setup file gives its answers by the names of the fields, nothing is asked: a grid is made from the file, then a simulator with two
# regions joins the grid the previous sections used (an account of that grid owns its estate). Neither is enabled or started, the
# install is back as it was at the end. Sourced by package-scenario.sh (STOP_AFTER=setupfile stops the scenario after it), in the
# test container.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

grid=/etc/opensim/grids/filegrid
instance=testgrid_filesim
sims=/etc/opensim/grids/testgrid/sims

ts "setup file: a grid made from its answers"
cat >/tmp/filegrid.yaml <<'YAML'
grid:
  name: File Grid
  nick: filegrid
  hostname: localhost
  web_url: https://localhost
  public_port: 8102
  private_port: 8103
  center: "8102,8102"
  console: screen
  helpers: false
  enable: false
  database: { user: file_user, password: filepw1, admin_user: dbroot, admin_password: adminpw }
YAML
chmod 644 /tmp/filegrid.yaml
(cd /tmp && runuser -u opensim -- opensim import /tmp/filegrid.yaml --apply >/tmp/filegrid.out 2>&1
    echo "exit code: $?" >>/tmp/filegrid.out)
dump /tmp/filegrid.out 6
check "the setup ends well" "grep -q 'exit code: 0' /tmp/filegrid.out"
check "the grid has the name, the ports and the center of the file" "grep -q 'File Grid' $grid/Robust.HG.ini && grep -q '8102' $grid/Robust.HG.ini && grep -q '8103' $grid/Robust.HG.ini && grep -q '8102,8102' $grid/filegrid.conf"
check "its database and its account are made with the administrator of the file" "[ \"\$(mysql -BN -e \"SHOW DATABASES LIKE 'filegrid_robust'\")\" = filegrid_robust ] && [ \"\$(mysql -BN -e \"SELECT COUNT(*) FROM mysql.user WHERE User='file_user'\")\" -ge 1 ]"
check "the grid is not enabled, the setup is kept for its owner" "[ ! -e /etc/opensim/robust.d/filegrid.ini ] && grep -q file_user $grid/setup.json"

ts "setup file: a simulator with two regions, in the grid of the previous sections"
cat >/tmp/filesim.yaml <<'YAML'
grid: { nick: testgrid }
simulators:
  - name: Filesim
    http_port: 9200
    console: screen
    enable: false
    database: { user: filesim_user, password: filepw2, admin_user: dbroot, admin_password: adminpw }
    estate: { name: File Estate, owner: Test Owner }
    regions:
      - { name: File Welcome, location: "9300,9300", port: 9201 }
      - { name: File Second, location: "9301,9300", port: 9202 }
YAML
chmod 644 /tmp/filesim.yaml
(cd /tmp && runuser -u opensim -- opensim import /tmp/filesim.yaml --apply >/tmp/filesim.out 2>&1
    echo "exit code: $?" >>/tmp/filesim.out)
dump /tmp/filesim.out 6
check "the setup ends well" "grep -q 'exit code: 0' /tmp/filesim.out"
check "the simulator has the port and the estate of the file, owned by an account of the grid" "grep -q '^http_listener_port = 9200' $sims/$instance.ini && grep -q 'File Estate' $sims/$instance.ini && grep -q '\"Test Owner\"' $sims/$instance.ini"
check "the two regions are described, at the places and with the ports of the file" "[ \"\$(ls $sims/$instance/regions | wc -l)\" = 2 ] && grep -q '^Location = 9300,9300' $sims/$instance/regions/FileWelcome.ini && grep -q '^InternalPort = 9201' $sims/$instance/regions/FileWelcome.ini && grep -q '^Location = 9301,9300' $sims/$instance/regions/FileSecond.ini && grep -q '^InternalPort = 9202' $sims/$instance/regions/FileSecond.ini"
check "its database is made, the simulator is not enabled" "[ \"\$(mysql -BN -e \"SHOW DATABASES LIKE '$instance'\")\" = $instance ] && [ ! -e /etc/opensim/opensim.d/$instance.ini ]"

ts "setup file: the install is back as it was"
rm -rf $grid /var/lib/opensim/data/filegrid /var/cache/opensim/filegrid $sims/$instance* /var/lib/opensim/data/testgrid/$instance /var/cache/opensim/testgrid/$instance
mysql -e "DROP DATABASE IF EXISTS filegrid_robust; DROP DATABASE IF EXISTS $instance; DROP USER IF EXISTS file_user@localhost, filesim_user@localhost"
