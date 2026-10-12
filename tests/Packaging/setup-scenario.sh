#!/usr/bin/env bash
# setup.sh on a machine that has nothing of the kit: it installs the packages of a folder (the ones of /dist, as the Magiiic
# repository would give them), the .NET runtime and libgdiplus, and says it is ready. The wizard itself is not started
# (--no-setup): the scenarios of the grids do that. Run with SCENARIO=setup-scenario.sh tests/Packaging/run, in the test container.

source /test/scenario-lib.sh

# What apt would take from the repository for opensim-kit, in the folder the script is given
debs=/tmp/kit-debs
mkdir -p $debs
for name in opensim-rest-php opensim-engine opensim-helpers opensim-tools opensim-0.9.3.0 opensim opensim-0.9.3.0-opensimsearch opensim-0.9.3.0-gloebit opensim-kit; do
    ln -sf "$(deb $name)" $debs/
done
rm -f /usr/sbin/policy-rc.d # container images forbid service actions

ts "setup.sh: a machine with nothing of the kit"
check "no opensim command, no crudini, no bash-tools" "! command -v opensim >/dev/null && ! command -v crudini >/dev/null && ! dpkg -s bash-tools >/dev/null 2>&1"
(OPENSIM_KIT_DEBS=$debs bash /test/setup.sh -y --no-setup >/tmp/setup.out 2>&1; echo "exit code: $?" >>/tmp/setup.out)
dump /tmp/setup.out 8
check "it ends well and says the machine is ready" "grep -q 'exit code: 0' /tmp/setup.out && grep -q \"Run 'opensim setup'\" /tmp/setup.out"
check "the packages are installed, and the command runs" "dpkg -s opensim-kit >/dev/null 2>&1 && opensim help | grep -q 'opensim setup\\|setup'"
check "the profile of the release is the default, the modules are enabled" "grep -q '^DefaultProfile = opensim-0.9.3.0' /etc/opensim/opensim.conf && grep -q '^\\[opensim-0.9.3.0\\]' /etc/opensim/opensim.conf && grep -q '^EnabledModules = opensimsearch gloebit' /etc/opensim/opensim.conf"
check "the .NET runtime is there for the account that runs the instances" "runuser -u opensim -- dotnet --list-runtimes | grep -q 'Microsoft.NETCore.App 8\\.'"
check "libgdiplus is there" "ldconfig -p | grep -q 'libgdiplus\\.so'"
check "the tools need neither crudini nor bash-tools" "! command -v crudini >/dev/null && ! dpkg -s bash-tools >/dev/null 2>&1"

ts "setup.sh: again"
conf_sum=$(md5sum /etc/opensim/opensim.conf)
(OPENSIM_KIT_DEBS=$debs bash /test/setup.sh -y --no-setup >/tmp/setup2.out 2>&1; echo "exit code: $?" >>/tmp/setup2.out)
check "a second run changes nothing" "grep -q 'exit code: 0' /tmp/setup2.out && [ \"$conf_sum\" = \"\$(md5sum /etc/opensim/opensim.conf)\" ]"

ts "setup.sh: what is refused"
check "an unknown option" "bash /test/setup.sh --bogus >/tmp/bogus.out 2>&1; [ \$? = 2 ] && grep -q 'unknown option' /tmp/bogus.out"
check "a folder that does not exist" "OPENSIM_KIT_DEBS=/tmp/nothing-here bash /test/setup.sh -y --no-setup >/tmp/nofolder.out 2>&1; [ \$? != 0 ] && grep -q 'is not a folder' /tmp/nofolder.out"
check "the profiles follow the releases: a release removed is forgotten" "mv /usr/share/opensim/0.9.3.0 /tmp/core-away && php /usr/share/opensim-tools/libexec/update-profiles.php | grep -q 'profile opensim-0.9.3.0 removed' && ! grep -q '^\\[opensim-0.9.3.0\\]' /etc/opensim/opensim.conf; mv /tmp/core-away /usr/share/opensim/0.9.3.0 && php /usr/share/opensim-tools/libexec/update-profiles.php | grep -q 'profile opensim-0.9.3.0 added'"
echo
[ "$FAILED" = 0 ] && ts "done: all checks passed" || ts "done: SOME CHECKS FAILED"
exit $FAILED
