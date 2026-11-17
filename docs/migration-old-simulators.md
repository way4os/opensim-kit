# Migrating a simulator of an old install (opensim-debian layout) to v3

Operator steps around `opensim import --simulator`. Nothing here is run without the typed go of the owner of the server.

Facts known (inventory 2026-11-15, residences): Debian 12, core 0.9.2.2 on mono, `/opt/opensim` layout, files owned by `magic`
(mode 640), one `screen` session per simulator as user `magic`, no systemd unit for the simulators, an hourly cron
(`/etc/cron.hourly/opensim` running `opensim start`) that restarts what is down.

Marked **verify** = not yet read on the server, to check read-only before the real run.

## 0. Before

- Backup done and checked (DB dump gzip -t, OAR, config tar): see `old-backup` results in `/var/backups/<short>/magic/opensim-charm/`.
- v3 packages installed on the server (they create the user and group `opensim`); the target grid exists on the machine (plan against it, not against a test grid).
- Plan read and accepted: `opensim import --simulator FILE [--simulator FILE …] GRID` (plan only, nothing written).

## 0b. What the plan does before writing (engine f6478f4, CLI fe35af0)

- The plan starts with a backup of the source (`backup` in the plan): the databases named by the ini (here the simulator's own, `osestate`,
  `osgloebit`), with the credentials of the ini, the config and its includes, the region files, in
  `/var/backups/opensim/<grid>/<sim>/<grid>-<sim>-<stamp>-source.tar.gz`. A source alone: `opensim backup --simulator FILE [GRID]`.
  The disk needs the room: check `df` on `/var/backups` before (residences: 8 databases of 49-60 MB, a few MB of config).
- The script state (`ScriptEngines` of the old cache folder) is copied to the new simulator's data folder; `--no-scripts` skips it. It needs the read access of step 1
  on `/opt/opensim/var/cache`.
- `import-module-missing`: the kept `*Module` settings are checked against the core and the modules folder. For residences, three modules the new
  install does not have: `OpenSimSearch` ([Search] Module), `DTLNSLMoneyModule` ([Economy] EconomyModule), `Gloebit` ([Economy] economymodule). Their DLLs
  are in the grid folder of the old install (`etc/grids/Speculoos/OpenSimSearch.Modules.dll`, `Gloebit.dll`): copy them to the modules folder of the new install
  (the OpenSimSearch fork has a DLL per OpenSim version) before enabling the new simulator, or leave the settings out. Each of the 8 reports the same three.
- `opensim start` refuses a simulator whose http port is still taken (`port-taken`): a second guard after step 2.4.

## 1. Read access for the import (user `opensim` reads files of `magic`)

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

- Needs the `acl` package (**verify**: `command -v setfacl`). If absent, `sudo apt install acl`, or the fallback below.
- Fallback without ACL: `sudo usermod -aG magic opensim` (the files are group `magic`, 640 gives the group read; directories need `x` for
  the group, **verify** they are 750 or more). Remove with `sudo gpasswd -d opensim magic`. A group stays in effect for new logins of
  `opensim` only: the engine API running as `opensim` must be restarted.
- Nothing is made world-readable; the DB passwords in the ini files stay unreadable to others.

## 2. Stopping an old simulator (one at a time) and keeping it stopped

Per simulator `SIM` (residences: 8 of them, one region each), as `magic`:

1. Ask for the shutdown through its console, then wait for the process to end (the log ends with the shutdown lines):
   `screen -S SIM -X stuff "shutdown"$'\r'` then `tail -f /opt/opensim/var/logs/SIM.log` until it ends, `pgrep -f "SIM"` empty.
   (A `screen -X stuff` line is cut near 100 characters: only short commands.)
2. Keep it from restarting: the hourly cron runs `opensim start` and would bring it back. **verify** how `opensim start` picks the
   simulators (read `/etc/cron.hourly/opensim` and the old `opensim` script). If it starts every `opensim.d/*.ini`, move the entry out:
   `mv /opt/opensim/etc/opensim.d/SIM.ini /opt/opensim/etc/opensim.d/SIM.ini.migrated` (it is a symlink to `grids/<Grid>/sims/SIM.ini`; the
   target stays). If the cron is the only starter, a stopped file `/etc/cron.hourly/opensim` (`chmod -x`) for the whole install is the
   alternative when all 8 move the same day.
3. Check that the ports are free: `ss -ltnup | grep -E ':(HTTPPORT|REGIONPORT)\b'` (empty), and no `screen -ls` session called SIM.
4. Enable and start the new one: `opensim enable GRID.SIM`, `opensim start GRID.SIM`; check `opensim status`, the log, a login on the region.

Rollback (new one not good): `opensim stop GRID.SIM`, `opensim disable GRID.SIM`, `mv SIM.ini.migrated SIM.ini`, start the old one from the
cron or by hand. The database is the same: nothing to restore unless the new one wrote bad data (then the dump of step 0).

## 3. After

- Remove the ACL (step 1).
- Old install kept untouched for a while (rollback); the cron stays disabled for the migrated simulators.
- Delete the scratch copies of the config (they hold DB passwords).

## Order and limits

- A simulator at a time, the smallest first (recital, rehab), then the others of the same server; `residences` last (the one that carries most).
- One server per day; no change on the grid (Robust) and its welcome simulator until the helpers move.
- A simulator restored alone keeps the grid's databases (engine), with a check of the estates: read the plan's notice.
