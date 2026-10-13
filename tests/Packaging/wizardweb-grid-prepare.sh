#!/usr/bin/env bash
# What wizardweb-grid-scenario.sh needs and does not change from a run to the next, done once: the core, the tools and the .NET
# runtime (minutes of installation and download), and the person who runs the setup. Run by PREPARE=wizardweb-grid-prepare.sh
# tests/Packaging/run in the test container, which is then kept as an image; the scenario runs it itself when it starts without.

source /test/scenario-lib.sh
magiiic_repository

ts "packages"
apt_q install "$(deb opensim-0.9.3.0)" "$(deb opensim-rest-php)" "$(deb opensim-engine)" "$(deb opensim-helpers)" "$(deb opensim-tools)"
apt_q install sudo
check "the core and the tools are installed" "[ -f /usr/share/opensim/0.9.3.0/bin/OpenSim.exe ] && command -v opensim >/dev/null"

ts dotnet
opensim install-dotnet -y 2>&1 | tail -1
check ".NET runtime" "runuser -u opensim -- dotnet --list-runtimes | grep -q NETCore.App"

# A person who runs the setup: may act as opensim (sudo)
useradd -m tester
echo 'tester ALL=(opensim) NOPASSWD: ALL' >/etc/sudoers.d/tester
chmod 440 /etc/sudoers.d/tester
check "the person who runs the setup can act as opensim" "runuser -u tester -- sudo -n -u opensim true"

exit $FAILED
