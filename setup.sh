#!/usr/bin/env bash
# OpenSim Kit: gets this machine ready, then starts `opensim setup`, which asks about your grid.
#
#   One command, from the web (Debian or Ubuntu):
#     curl -fsSL https://raw.githubusercontent.com/GuduleLapointe/opensim-kit/refs/heads/master/setup.sh | sudo bash
#
#   Or from a clone of the repository:
#     git clone https://github.com/GuduleLapointe/opensim-kit.git && cd opensim-kit && ./setup.sh
#
#   Or with the packages only:  sudo apt install opensim-kit && opensim setup
#
# From the web it installs the packages of the Magiiic repository (opensim-kit and what it needs, the .NET runtime
# included). From a clone it runs the tools of the clone: its PHP libraries come from composer.
#
# Options:  -y, --yes   do not ask before installing
#           --no-setup  prepare the machine, do not start `opensim setup`
# Environment: OPENSIM_KIT_DEBS=folder  install the packages (*.deb) of that folder instead of the Magiiic repository

set -euo pipefail

MAGIIIC_KEY=https://apt.magiiic.com/magiiic-packaging.asc
# The OpenSim packages (charm) and the general ones they need (main: w4os-site, bash-tools)
MAGIIIC_REPOSITORY="deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com charm stable
deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com main stable"
# What the tools need on a machine that does not have the packages (a clone): the same as the package opensim-tools
PHP_PACKAGES=(php-cli php-curl php-intl php-mbstring php-mysql php-xml)
TOOL_PACKAGES=(ca-certificates curl git unzip screen sudo default-mysql-client)

ASSUME_YES=
RUN_SETUP=yes
for argument in "$@"; do
	case "$argument" in
	-y | --yes) ASSUME_YES=yes ;;
	--no-setup) RUN_SETUP= ;;
	-h | --help)
		sed -n '2,/^$/p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
		exit 0
		;;
	*)
		echo "setup.sh: unknown option $argument (see --help)" >&2
		exit 2
		;;
	esac
done

say() { printf '%s\n' "$*"; }
die() {
	printf 'setup.sh: %s\n' "$*" >&2
	exit 1
}

# A question for the person at the terminal, even when this script comes from a pipe; yes when nobody can answer
ask() {
	[[ -n "$ASSUME_YES" ]] && return 0
	(: </dev/tty) 2>/dev/null || return 0
	local answer
	read -r -p "$1 [Y/n] " answer </dev/tty
	[[ ! $answer =~ ^[Nn] ]]
}

# This script is in a clone of the repository when its folder has the command and the composer file
here=
if [[ -n "${BASH_SOURCE[0]:-}" && -f "${BASH_SOURCE[0]}" ]]; then
	here=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
fi
clone=
[[ -n "$here" && -x "$here/bin/opensim" && -f "$here/composer.json" ]] && clone=$here

as_root() {
	if [[ $(id -u) -eq 0 ]]; then
		"$@"
	elif command -v sudo >/dev/null 2>&1; then
		sudo "$@"
	else
		die "this needs administrator rights: run it as root, or install sudo"
	fi
}

start_setup() { # the library of the questions, then the command to run, which gets the keyboard even when this script came through a pipe
	local frontend=$1 status=0
	shift
	if [[ -z "$RUN_SETUP" ]]; then
		say "Ready. Run '$*' when you want to make your grid."
		exit 0
	fi
	say "Starting the setup..."
	# The questions are shown here (dialog or plain prompts), unless nobody has a keyboard to answer
	if [[ -r "$frontend" ]] && (: </dev/tty) 2>/dev/null; then
		# shellcheck source=libexec/setup-frontend.sh
		source "$frontend"
		frontend_run "$@" || status=$?
		exit "$status"
	fi
	if [[ ! -t 0 ]] && (: </dev/tty) 2>/dev/null; then
		exec "$@" </dev/tty
	fi
	exec "$@"
}

install_packages() {
	command -v apt-get >/dev/null 2>&1 || die "no apt-get here: this works on Debian and Ubuntu (see INSTALLATION.md for the others)"
	export DEBIAN_FRONTEND=noninteractive
	as_root apt-get update -qq
	as_root apt-get install -y -qq "$@"
}

# The .NET runtime OpenSimulator needs, and libgdiplus, by the command of the tools (it knows what each system has)
dotnet_runtime() {
	as_root "$1" install-dotnet -y
}

case "$(uname -s)" in
Linux | Darwin) ;;
*) die "unsupported system $(uname -s)" ;;
esac

if [[ -n "$clone" ]]; then
	###
	# A clone: its tools run from the folder, with the PHP libraries of composer
	###
	say "OpenSim Kit, from the clone in $clone"
	if [[ $(uname -s) == Darwin ]]; then
		command -v brew >/dev/null 2>&1 || die "Homebrew is needed on macOS: https://brew.sh"
		ask "Install php, composer and screen with Homebrew if they are missing?" || die "cancelled"
		for tool in php composer screen; do
			command -v "$tool" >/dev/null 2>&1 || brew install "$tool"
		done
	else
		missing=()
		command -v php >/dev/null 2>&1 || missing+=("${PHP_PACKAGES[@]}")
		command -v composer >/dev/null 2>&1 || missing+=(composer)
		for tool in curl git unzip screen; do
			command -v "$tool" >/dev/null 2>&1 || missing+=("$tool")
		done
		command -v mysql >/dev/null 2>&1 || command -v mariadb >/dev/null 2>&1 || missing+=(default-mysql-client)
		if ((${#missing[@]})); then
			say "This machine needs: ${missing[*]}"
			ask "Install them?" || die "cancelled"
			install_packages "${missing[@]}"
		fi
	fi
	php -r 'exit(PHP_VERSION_ID >= 80200 ? 0 : 1);' || die "PHP 8.2 or newer is needed (this is $(php -r 'echo PHP_VERSION;'))"

	if [[ ! -f "$clone/vendor/autoload.php" ]]; then
		say "Installing the PHP libraries (composer)..."
		(cd "$clone" && composer install --no-dev --no-interaction --prefer-dist)
	fi
	if [[ $(uname -s) == Linux ]]; then
		dotnet_runtime "$clone/bin/opensim"
	fi
	start_setup "$clone/libexec/setup-frontend.sh" "$clone/bin/opensim" setup
fi

###
# Not a clone: the packages, from the Magiiic repository or from a folder
###
[[ $(uname -s) == Linux ]] || die "the packages are for Debian and Ubuntu: on macOS, clone the repository and run ./setup.sh from it"
command -v apt-get >/dev/null 2>&1 || die "no apt-get here: this works on Debian and Ubuntu (see INSTALLATION.md for the others)"

if [[ -n "${OPENSIM_KIT_DEBS:-}" ]]; then
	debs=$(cd "$OPENSIM_KIT_DEBS" && pwd) || die "$OPENSIM_KIT_DEBS is not a folder"
	say "OpenSim Kit, from the packages in $debs"
	ls "$debs"/*.deb >/dev/null 2>&1 || die "no package (*.deb) in $debs"
	ask "Install them?" || die "cancelled"
	install_packages "$debs"/*.deb
else
	say "OpenSim Kit, from the packages of the Magiiic repository (apt.magiiic.com)"
	ask "Add the repository and install opensim-kit?" || die "cancelled"
	install_packages ca-certificates curl gpg
	if [[ ! -f /etc/apt/sources.list.d/magiiic.list ]]; then
		curl -fsSL "$MAGIIIC_KEY" | as_root gpg --dearmor --yes -o /usr/share/keyrings/magiiic-packaging.gpg
		printf '%s\n' "$MAGIIIC_REPOSITORY" | as_root tee /etc/apt/sources.list.d/magiiic.list >/dev/null
	fi
	install_packages opensim-kit
fi

dotnet_runtime opensim
# The command of opensim-cli asks its own questions
start_setup "" opensim setup
