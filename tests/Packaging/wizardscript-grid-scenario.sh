#!/usr/bin/env bash
# A grid made by the script that asks the questions (libexec/setup-frontend.sh), from the first question to the last, on a real install:
# the core, the database server, a person who is not the user of the install (the writing is handed to opensim with sudo). The person
# is scripted-person.sh: it answers the questions by their label. Run by SCENARIO=wizardscript-grid-scenario.sh tests/Packaging/run, in
# the test container (PREPARE=wizardweb-grid-prepare.sh keeps the core and the person in an image).

source /test/scenario-lib.sh

[[ -d /home/tester ]] || bash /test/wizardweb-grid-prepare.sh || exit 1

ts "the packages of the tools, which change from a run to the next"
apt_q install "$(deb opensim-rest-php)" "$(deb opensim-engine)" "$(deb opensim-helpers)" "$(deb opensim-tools)"
systemctl start mariadb
check "the tools are the ones of this run" "[ \"\$(dpkg-query -W -f='\${Version}' opensim-tools)\" = \"\$(dpkg-deb -f \$(deb opensim-tools) Version)\" ]"
check "the script that asks the questions is in the package" "[ -f /usr/share/opensim-tools/libexec/setup-frontend.sh ]"

# The person administers the database server with her own account
mysql -e "CREATE USER tester@localhost IDENTIFIED VIA unix_socket; GRANT ALL ON *.* TO tester@localhost WITH GRANT OPTION;"
# An account of the database that exists already, with a password the setup cannot guess: it asks for it once
mysql -e "CREATE USER opensim@localhost IDENTIFIED BY 'knownpw12';"

# The person: the answers by the label of the question, what the setup proposes for the others. A question asked more than a hundred
# times is a menu the person cannot get out of: the person leaves
cat >/tmp/scripted-person.sh <<'PERSON'
source /usr/share/opensim-tools/libexec/setup-frontend.sh
ASKED=0
fe_answer() {
    local id=${FE_FIELDS[1]} kind=${FE_FIELDS[2]} label=${FE_FIELDS[3]} default=${FE_FIELDS[4]:-} answer i
    ASKED=$((ASKED + 1))
    echo "question $ASKED: [$kind] $label (default: $default)" >>/tmp/questions.log
    if ((ASKED > 100)); then
        printf 'quit\x1f%s\n' "$id" >&6
        return
    fi
    answer=$default
    case "$label" in
    'Grid name') answer='Script Grid' ;;
    'Grid URL') answer='localhost:8002' ;;
    'Grid owner') answer='Script Owner' ;;
    'Owner password') answer='secret12' ;;
    'Database password') answer='knownpw12' ;;
    esac
    if [[ $kind == choose ]]; then
        fe_options
        # The menus that go on: the person leaves where she could add more
        for ((i = 0; i < ${#FE_KEYS[@]}; i++)); do
            case "$label:${FE_LABELS[i]}" in
            Simulator*:Quit* | Setup*:Continue*) answer=${FE_KEYS[i]} ;;
            esac
        done
    fi
    printf 'answer\x1f%s\x1f%s\n' "$id" "$(fe_escape "$answer")" >&6
}
frontend_run opensim setup
PERSON

ts "the setup, with its questions asked by the script"
(cd /tmp && runuser -u tester -- env OPENSIM_SETUP_UI=plain bash /tmp/scripted-person.sh >/tmp/script-setup.out 2>&1
    echo "exit code: $?" >>/tmp/script-setup.out)
fold -s -w 200 /tmp/script-setup.out | sed 's/^/   | /' | head -60
sed 's/^/   | /' /tmp/questions.log | head -40
check "the setup went through to its end, with the status of the setup" "grep -q 'exit code: 0' /tmp/script-setup.out"
check "the questions were asked one at a time, not a hundred" "[ \"\$(wc -l </tmp/questions.log)\" -lt 60 ]"
check "the password of the account is asked once, for the database of the grid" "[ \"\$(grep -c 'Database password' /tmp/questions.log)\" = 1 ]"
check "what the programs of the setup say reaches the script (their output is not lost)" "grep -qi 'configured' /tmp/script-setup.out"
check "nothing is left of the pipes" "[ -z \"\$(ls -d /tmp/opensim-setup.* 2>/dev/null)\" ]"

ts "what the setup made"
grid=/etc/opensim/grids/script_grid
check "the grid is made, with the name typed, by the user of the install" "grep -q 'Script Grid' $grid/Robust.HG.ini &&
    [ \"\$(stat -c %U $grid/Robust.HG.ini)\" = opensim ]"
check "the simulator and its region are made" "ls $grid/sims/*.ini >/dev/null 2>&1 && ls $grid/sims/*/regions/*.ini >/dev/null 2>&1"
check "the database is made, by the administrator access of the person" "[ \"\$(mysql -BN -e \"SHOW DATABASES LIKE 'script_grid_robust'\")\" = script_grid_robust ]"
check "the setup is kept for the group of the install" "[ \"\$(stat -c '%a %U:%G' $grid/setup.json)\" = '640 opensim:opensim' ]"
wait_check 300 "Robust of the grid is running" "pgrep -f '^dotnet .*Robust.dll' >/dev/null"
wait_check 400 "the region of the first simulator registers in the grid" "grep -q 'Region Welcome .* registered at 8002,8002' /var/log/opensim/script_grid_welcome.log"

ts "done: $([ $FAILED = 0 ] && echo 'all checks passed' || echo 'SOME CHECKS FAILED')"
exit $FAILED
