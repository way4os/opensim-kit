# Migrating a simulator of an old install (opensim-debian layout) to v3

Operator steps around `opensim import --simulator`. Nothing here is run without the typed go of the owner of the server.

Facts known (inventory 2026-11-15, residences): Debian 12, core 0.9.2.2 on mono, `/opt/opensim` layout, files owned by `magic`
(mode 640), one `screen` session per simulator as user `magic`, no systemd unit for the simulators, an hourly cron
(`/etc/cron.hourly/opensim` running `opensim start`) that restarts what is down.

Read on residences 2026-11-17 (read-only, Olivier's go typed in the session): the facts below marked "residences". Marked **verify** = still to check.

## 0. Before

- Backup done and checked (DB dump gzip -t, OAR, config tar): see `old-backup` results in `/var/backups/<short>/magic/opensim-charm/`.
- v3 packages installed on the server (they create the user and group `opensim`); the target grid exists on the machine (plan against it, not against a test grid).
- Plan read and accepted: `opensim import --simulator FILE [--simulator FILE …] GRID` (plan only, nothing written).

## 0b. What the plan does before writing (engine f6478f4, CLI fe35af0)

- The plan starts with a backup of the source (`backup` in the plan): the databases named by the ini (here the simulator's own, `osestate`,
  `osgloebit`), with the credentials of the ini, the config and its includes, the region files, in
  `/var/backups/opensim/<grid>/<sim>/<grid>-<sim>-<stamp>-source.tar.gz`. A source alone: `opensim backup --simulator FILE [GRID]`.
  The disk needs the room: residences has 24 GB free on `/` (13 GB used; `/var/backups` is on it), `var/cache` of the 8 sims is 4.8 GB in total
  (reaction 1.7 GB, residences 1.3 GB, rebelle 0.9 GB, refraction 0.5 GB, regain 0.4 GB, reality 0.1 GB, recital and rehab 41 MB each), the databases 49-60 MB each.
- The script state (`ScriptEngines` of the old cache folder) is copied to the new simulator's data folder; `--no-scripts` skips it. It needs the read access of step 1
  on `/opt/opensim/var/cache`.
- Modules (engine 2806044): `import-module-found` / `import-module-missing` name the destination `/usr/share/opensim-modules/<core>/<Module>/`
  (here `0.9.3.0`) and say to add the module to `EnabledModules` of `opensim.conf`. A module is built for a version of OpenSimulator: the DLL of the
  old install (core 0.9.2.2) is copied only if the new core has the same version, else a build for the new core is needed. For residences, each of
  the 8 sims reports the same three:
  - `OpenSimSearch` ([Search] Module): the module exists as a package for the new core: `sudo apt install opensim-0.9.3.0-opensimsearch` (apt.magiiic.com,
    the fork, built for 0.9.3.0: packaging/opensim-modules.versions). Do not copy the old DLL (`etc/grids/Speculoos/OpenSimSearch.Modules.dll`, a 0.9.2.2 build).
  - `Gloebit` ([Economy] economymodule): `sudo apt install opensim-0.9.3.0-gloebit` (the official Gloebit build for 0.9.3.0, build 88, checked by hash).
    Do not copy the old `Gloebit.dll` (0.9.2.2).
  - `DTLNSLMoneyModule` ([Economy] EconomyModule): leave this setting out of the import. The old install has no DLL for it (neither next to the config nor in
    the BinDir), so it never loaded there: the economy ran through Gloebit. Only if the plan shows that the two keys conflict in a way that matters
    does it need a decision of the owner.
  The packages install into `/usr/share/opensim-modules/0.9.3.0/opensimsearch` and `/gloebit` (lowercase, one folder per module, not loaded until enabled): add each
  to `EnabledModules` of `opensim.conf`. The import notice names the folder with the module's class name (`.../OpenSimSearch`, `.../Gloebit`) and looks for the
  class in the DLLs under the modules root (not by folder name), so the notice goes away once the packages are installed; do not create the folder it names by hand.
  Until a build is installed, the new simulator would not load the module.
- Shared databases (`osestate`, `osgloebit`) are dumped once per import; each simulator's backup keeps its own copy.
- `opensim start` refuses a simulator whose http port is still taken (`port-taken`): a second guard after step 2.4.

## 1. Read access for the import (user `opensim` reads files of `magic`)

What the import reads (all checked first; unreadable paths are listed in the plan, none on the local copy): the simulator's ini and its includes
(`/opt/opensim/etc/grids/<Grid>/*.ini`, `config-include/*.ini`), its region files (`sims/<sim>/regions/*.ini`), the module DLLs of the old install (next to the
config, or its BinDir), `/opt/opensim/var/cache/<Grid>/<sim>` (script state), and the databases named by the ini (read with its credentials, nothing from `magic`'s account).
Residences: nothing to grant. The config files are mode 644 and the folders 755 (`/opt/opensim/etc`, `var/cache`, `var/data`, the sims'
configs and regions, the DLLs of the grid folder), owner `magic:magic`; `setfacl`/`getfacl` are installed; the user and group `opensim` do not
exist yet (the v3 packages create them). Do the check below once the packages are installed; the ACL is the answer for a server where the
files are 640. Other servers: **verify** the modes the same way (`stat -c '%A %U:%G %n'`).

The import reads: `/opt/opensim/etc` (opensim.d, grids/<Grid>/ with its includes and `sims/<sim>/regions`), and, for the copy of
the script state, `/opt/opensim/var/cache/<Grid>/<sim>` and `/opt/opensim/var/data/<Grid>/<sim>`.

Grant (ACL, removable, changes no owner or mode):

```bash
sudo setfacl -R -m u:opensim:rX /opt/opensim/etc /opt/opensim/var/cache /opt/opensim/var/data
sudo -u opensim test -r /opt/opensim/etc/opensim.d/SIM.ini && echo readable
```

Remove, once the import is applied and checked:

```bash
sudo setfacl -R -x u:opensim /opt/opensim/etc /opt/opensim/var/cache /opt/opensim/var/data
getfacl -R /opt/opensim/etc | grep -c 'user:opensim'   # 0
```

- Needs the `acl` package (installed on residences). If absent, `sudo apt install acl`, or the fallback below.
- Fallback without ACL: `sudo usermod -aG magic opensim` (the files are group `magic`, 640 gives the group read; directories need `x` for
  the group, **verify** they are 750 or more). Remove with `sudo gpasswd -d opensim magic`. A group stays in effect for new logins of
  `opensim` only: the engine API running as `opensim` must be restarted.
- Nothing is made world-readable; the DB passwords in the ini files stay unreadable to others.

## 2. Stopping an old simulator (one at a time) and keeping it stopped

How it runs on residences: 8 `screen` sessions of user `magic`, named as the simulator (`residences`, `reaction`, `reality`, `rebelle`, `recital`,
`refraction`, `regain`, `rehab`), each `mono OpenSim.exe -inifile=/opt/opensim/etc/opensim.d/<sim>.ini`; no systemd unit, no crontab;
`/etc/cron.hourly/opensim` (a symlink to `/opt/opensim/lib/cron.hourly/opensim`) runs `sudo -u magic /opt/opensim/bin/opensim start >> var/logs/cron.log`.
`opensim start` (no argument) starts every `etc/opensim.d/*.ini` and `etc/simulators-enabled/*.ini` (that last folder does not exist) whose
`screen` session is not already running. So a simulator is kept from restarting by taking its `opensim.d/<sim>.ini` out of the `*.ini` glob.
`opensim stop SIM` (the old script) warns the users, sends `shutdown` to the console and then runs `screen -x`, which attaches interactively:
do not use it from a script; send `shutdown` through the screen instead.

Per simulator `SIM`, as `magic`:

1. Keep it from restarting first (the cron may fire during the stop): `mv /opt/opensim/etc/opensim.d/SIM.ini /opt/opensim/etc/opensim.d/SIM.ini.migrated`
   (a symlink to `../grids/Speculoos/sims/SIM.ini`; the target stays untouched; `.migrated` is not matched by `*.ini`).
2. Warn, then shut down through the console, and wait for the end of the process:
   `screen -S SIM -X stuff "alert The simulator restarts in 5 minutes"$'\r'`, later `screen -S SIM -X stuff "shutdown"$'\r'`; wait until
   `screen -ls | grep -w SIM` and `pgrep -f "opensim.d/SIM.ini"` are empty. (A `screen -X stuff` line is cut near 100 characters: short commands only.)
3. Check that the ports are free: `ss -ltnup | grep -E ':(HTTPPORT|REGIONPORT)\b'` (empty). Ports: residences 9044/9079, reaction 9033/9075,
   rebelle 9034/9076, refraction 9035/9158, reality 9041/9087, regain 9037/9082, rehab 9042/9083, recital 9040/9077.
4. Enable and start the new one: `opensim enable GRID.SIM`, `opensim start GRID.SIM`; check `opensim status`, the log, a login on the region.

Rollback (new one not good): `opensim stop GRID.SIM`, `opensim disable GRID.SIM`, `mv SIM.ini.migrated SIM.ini`; the next hourly cron starts the old one, or
`/opt/opensim/bin/opensim start SIM` as `magic`. The database is the same: nothing to restore unless the new one wrote bad data (then the dump of step 0).

## 3. After

- Remove the ACL if one was granted (step 1).
- Old install kept untouched for a while (rollback); the cron stays disabled for the migrated simulators.
- Delete the scratch copies of the config (they hold DB passwords).

## Order and limits

- A simulator at a time, the smallest first (recital, rehab), then the others of the same server; `residences` last (the one that carries most).
- One server per day; no change on the grid (Robust) and its welcome simulator until the helpers move.
- A simulator restored alone keeps the grid's databases (engine), with a check of the estates: read the plan's notice.
