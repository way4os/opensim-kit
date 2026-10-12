#!/usr/bin/env bash
# `opensim ban`, on the grid of the scenario: an account and a visitor of another grid are found by name, the statements are
# written and nothing is changed until they are run. Sourced by package-scenario.sh (STOP_AFTER=ban stops the scenario after it),
# in the test container.

[[ $(type -t check) == function ]] || source /test/scenario-lib.sh

db=testgrid_robust
simdb=testgrid_sim1
sql() { mysql -BN "$db" -e "$1"; }
zero=00000000-0000-0000-0000-000000000000
visitor=77777777-6666-5555-4444-333333333333

ts "ban: an account, a visitor of another grid, an object"
printf 'first,last,email,password\nBanned,Person,,\n' >/tmp/ban.csv
(cd /tmp && runuser -u opensim -- opensim import testgrid /tmp/ban.csv --apply >/tmp/ban-import.out 2>&1)
banned=$(sql "SELECT PrincipalID FROM UserAccounts WHERE FirstName='Banned' AND LastName='Person'")
check "the account to ban exists" "[ -n '$banned' ]"
sql "INSERT INTO GridUser (UserID, HomeRegionID, HomePosition, HomeLookAt, LastRegionID, LastPosition, LastLookAt, Online, Login, Logout) VALUES ('$visitor;http://other.example.org:8002/;Evil Visitor', '$zero', '<0,0,0>', '<0,0,0>', '$zero', '<0,0,0>', '<0,0,0>', 'false', '0', '0')"
mysql -e "SET SESSION sql_mode=''; INSERT INTO $simdb.prims (UUID, OwnerID) VALUES ('$visitor', '$banned')"
estates=$(mysql -BN -e "SELECT COUNT(*) FROM $simdb.estate_settings")

rm -rf /tmp/ban && mkdir /tmp/ban && chown opensim /tmp/ban
(cd /tmp/ban && runuser -u opensim -- opensim ban testgrid Banned.Person 'Evil.Visitor@other.example.org' >/tmp/ban.out 2>&1; echo "exit code: $?" >>/tmp/ban.out)
check "the users are found, the account of the grid and the visitor" "grep -q 'exit code: 0' /tmp/ban.out && grep -q \"^$banned.Banned.Person\" /tmp/ban.out && grep -q \"^$visitor.Evil.Visitor@other.example.org:8002\" /tmp/ban.out"
check "the statements are written, with the objects and the ban in every estate" "grep -q \"DELETE FROM .$db...UserAccounts. WHERE PrincipalID = '$banned'\" /tmp/ban/ban.cleanup &&
    grep -q \"DELETE FROM .$db...inventoryfolders. WHERE agentID = '$banned'\" /tmp/ban/ban.cleanup &&
    grep -q \"DELETE FROM .$simdb...prims. WHERE OwnerID = '$banned'\" /tmp/ban/ban.cleanup &&
    grep -q \"DELETE FROM .$db...GridUser. WHERE UserID = '$visitor' OR UserID LIKE '$visitor;%'\" /tmp/ban/ban.cleanup &&
    [ \"\$(grep -c \"^INSERT INTO .*estateban.* '$banned'\" /tmp/ban/ban.ban)\" -ge $estates ]"
check "the data of the databases is not changed" "[ \"\$(sql \"SELECT COUNT(*) FROM UserAccounts WHERE PrincipalID='$banned'\")\" = 1 ] &&
    [ \"\$(mysql -BN -e \"SELECT COUNT(*) FROM $simdb.prims WHERE OwnerID='$banned'\")\" = 1 ]"
check "a name that nobody has is said" "(cd /tmp/ban && ! runuser -u opensim -- opensim ban testgrid Nobody.Here >/tmp/ban-none.out 2>&1) && grep -q \"no user found for 'Nobody.Here'\" /tmp/ban-none.out"

ts "ban: the statements, run"
cat /tmp/ban/ban.cleanup /tmp/ban/ban.ban | mysql
check "the account, its inventory, its objects and the visitor are gone" "[ \"\$(sql \"SELECT COUNT(*) FROM UserAccounts WHERE PrincipalID='$banned'\")\" = 0 ] &&
    [ \"\$(sql \"SELECT COUNT(*) FROM inventoryfolders WHERE agentID='$banned'\")\" = 0 ] &&
    [ \"\$(sql \"SELECT COUNT(*) FROM auth WHERE UUID='$banned'\")\" = 0 ] &&
    [ \"\$(sql \"SELECT COUNT(*) FROM GridUser WHERE UserID LIKE '$visitor%'\")\" = 0 ] &&
    [ \"\$(mysql -BN -e \"SELECT COUNT(*) FROM $simdb.prims WHERE OwnerID='$banned'\")\" = 0 ]"
check "both are banned from the estates" "[ \"\$(mysql -BN -e \"SELECT COUNT(*) FROM $simdb.estateban WHERE bannedUUID IN ('$banned', '$visitor')\")\" -ge $((estates * 2)) ]"
(cd /tmp/ban && runuser -u opensim -- opensim ban testgrid "$banned" >/tmp/ban-again.out 2>&1)
check "a second time, only what is left is asked: nothing to delete, nobody to ban twice" "grep -q '0 delete and 0 ban statements' /tmp/ban-again.out"
