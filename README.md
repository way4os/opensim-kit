# OpenSimulator Kit

![Stable](https://img.shields.io/github/release/way4os/opensim-kit?label=stable&color=green&include_prerelease)
![GitHub Tag](https://img.shields.io/github/tag/way4os/opensim-kit?label=latest&include_prereleases)
![GitHub commits since latest release](https://img.shields.io/github/commits-since/way4os/opensim-kit/latest?label=dev)
[![License](https://img.shields.io/badge/license-AGPL--3.0-552b55)](LICENSE)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/way4os/opensim-kit/total)
[![Donate](https://img.shields.io/badge/-Donate-yellow)](https://magiiic.org/donate/)

The packages of OpenSimulator for Debian and Ubuntu, and `opensim-kit`, which installs the whole environment to create and run
grids and regions: the `opensim` command, its engine, the website of a grid, the latest release of OpenSimulator and its
essential modules.

https://www.speculoos.world/opensim-debian-installation-framework/

## Installation

**With the installer** of the command line ([w4os-cli](https://github.com/way4os/w4os-cli)): it adds the
repository, installs the packages, the database server and the .NET runtime, and can start the setup.

```bash
curl -fsSL -O https://raw.githubusercontent.com/way4os/w4os-cli/master/kickstart.sh
chmod +x kickstart.sh
sudo ./kickstart.sh --setup
```

`./kickstart.sh --help` lists its options. `--unstable` includes the beta versions of the packages, `--testing` the test builds (and
`--unstable`); `--tools` installs the command line and the engine alone.

**From the packages** (Debian, Ubuntu):

```bash
## Add the Magiiic APT repository (once)
curl -fsSL https://apt.magiiic.com/magiiic-packaging.asc | sudo gpg --dearmor -o /usr/share/keyrings/magiiic-packaging.gpg
echo "deb [signed-by=/usr/share/keyrings/magiiic-packaging.gpg] https://apt.magiiic.com charm stable" | sudo tee /etc/apt/sources.list.d/magiiic.list
sudo apt update

## Install the packages, then the .NET runtime if the system has no package for it
sudo apt install opensim-kit
sudo opensim install-dotnet -y
opensim setup
```

`opensim-kit` installs:

| Package                                       | What it is                                                              |
| --------------------------------------------- | ----------------------------------------------------------------------- |
| `w4os-cli`                                    | the `opensim` command and its service                                   |
| `w4os-engine`                                 | the rules and the work behind it, with `opensim-rest-php`               |
| `w4os-site`                                   | the website of a grid, with the helpers (`w4os-site setup`, then `web`) |
| `opensim`                                     | the latest OpenSimulator release                                        |
| `opensim-<version>-opensimsearch`, `-gloebit` | its essential modules, enabled once                                     |
| `default-mysql-server`                        | the database server                                                     |

What the `opensim` command does is in the README of [w4os-cli](https://github.com/way4os/w4os-cli).

A server with the generation before (`opensim-tools`, `opensim-web`): `sudo apt full-upgrade` replaces `opensim-tools` with `w4os-cli`; `opensim-web` stays until the site of `w4os-site` is set up, then `sudo apt purge opensim-web`. Until then, `sudo apt-mark manual opensim-web` keeps `apt autoremove` from taking it away with the helpers. Removing `opensim-tools` stops the instances: `sudo systemctl start opensim` after the upgrade.

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
