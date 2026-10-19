# OpenSimulator Kit

![Stable](https://img.shields.io/github/release/GuduleLapointe/opensim-debian?label=stable&color=green&include_prerelease)
![GitHub Tag](https://img.shields.io/github/tag/GuduleLapointe/opensim-debian?label=latest&include_prereleases)
![GitHub commits since latest release](https://img.shields.io/github/commits-since/GuduleLapointe/opensim-debian/latest?label=dev)
![PHP](https://img.shields.io/badge/PHP-8.2+-7884bf)
[![License](https://img.shields.io/badge/license-AGPL--3.0-552b55)](LICENSE)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/GuduleLapointe/opensim-debian/total)
[![Donate](https://img.shields.io/badge/-Donate-yellow)](https://magiiic.org/donate/)

This is an framework to facilitate installation and use of OpenSim with Debian or other *nix flavors.

https://www.speculoos.world/opensim-debian-installation-framework/

## Installation

Three ways, the same wizard at the end: `opensim setup` asks about your grid.

**From the packages** (Debian, Ubuntu)

```bash
## Add the Magiiic APT repository (once)
curl -fsSL https://apt.magiiic.com/magiiic-packaging.asc | sudo gpg --dearmor -o /usr/share/keyrings/magiiic-packaging.gpg
echo "deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com stable main" | sudo tee /etc/apt/sources.list.d/magiiic.list
sudo apt update

## Install the packages, then the .NET runtime if the system has no package for it
sudo apt install opensim-kit
sudo opensim install-dotnet -y
opensim setup
```

**With one command** (Debian, Ubuntu): the script adds the repository, installs the packages and the .NET runtime, then starts the setup.

```bash
curl -fsSL https://raw.githubusercontent.com/GuduleLapointe/opensim-kit/refs/heads/master/setup.sh | sudo bash
```

**From a clone** (any system with PHP 8.2 or newer; on macOS with Homebrew): the tools run from the clone, their PHP libraries come from composer.

```bash
git clone https://github.com/GuduleLapointe/opensim-kit.git
cd opensim-kit
./setup.sh
```

`./setup.sh --help` lists its options (`-y` does not ask, `--no-setup` only prepares the machine).

See [INSTALLATION.md](INSTALLATION.md) for more details and advanced installation options.

- create basic directory structure, download OpenSim and other needed libraries
- read Robust default configuration, ask a few questions and build a working configuration in etc/robust-d

## Features

`opensim start [target...]`

- Without a target, start every enabled instance: the Robust of each grid (linked in etc/robust.d), then the simulators (linked in etc/opensim.d)
- A target is a grid (`vonda`: its services, its Robust) or a simulator (`vonda_welcome`); the simulators do not need to restart with the Robust, `--simulators` adds the enabled simulators of the grid

`opensim stop [now] [target...]`

`opensim restart [now] [target...]`

- Stop/Restart everything enabled or the targets; with `--simulators`, a grid stops its simulators first, then its Robust, and starts the other way round
- If first parameter is "now", stops the simulator immediately, otherwise send reminders to leave during 2 minutes then stop

`opensim enable|disable <grid> search|economy|profiles`

- Write, or take out, the settings of a feature in the files of the grid: the in-world search, the economy (beta module or Gloebit), the profiles with their classifieds

`opensim status`

- Show active instances, per instance and global memory and cpu usage

## Main motivation

In a software application, particularly a complicate one like OpenSim, some
thing should never be stored at the same place. Essentially, there is a place
for static files (executables, libraries), a place for preferences, and a place
for data created by the application (permanent or temporary).

This way, you can

- easily update the software without touching preferences and data
- backup the data without duplicating the software
- avoid duplicating the application if you need to run several instances...

So, we reorganised the files and folders, matching the general Linux standards.

- The whole thing is stored in /opt/opensim-debian (could become
  /usr/share/opensim if we make a package), refferred as OSDDIR below
- Scripts and utilities are in /OSDDIR/bin/
- The main code (latest stable OpenSim release) is located in /OSDDIR/core/opensim (no, not in bin, because they are not directly executable on all OSes, and they rely on lot of other files around them)
- Preferences are read from /OSDDIR/etc/ /etc/ and ~/etc/, each one overriding the precedent
- Cache is stored in /OSDDIR/var/cache
- Logs in /OSDDIR/var/logs
- Databases (if using sqlite) should be store in var/db (but we don't use
  sqlite, so this could be added or not later)
- Git clone and other works in progress should go in dev/

It was important to achieve this without altering the main OpenSim code.
So we created some scripts which:

- read the preferences in etc/
- looks for instances to start in etc/robust.d and etc/opensim.d
- tells OpenSim where to save data, cache and logs

We have developed and used this setup for several years in Speculoos Grid
and wanted to share. Although this was working for us, we don't push the whole
thing as is, as we want to make sure the methods are as globals as they can.
