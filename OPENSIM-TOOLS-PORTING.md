# opensim-tools: what is ported, what is not

opensim-tools, the PHP command line of this repository, is replaced by opensim-cli (the `opensim` command), opensim-engine (the
rules and the work, its API), opensim-helpers and w4os-site (the website of a grid, which carries the helpers). This file lists
what opensim-tools does, and where each feature lives now, before its code leaves this repository. It is the reference when a
feature of before is missed: the old code stays readable in git.

- Old code: the paths of this repository at commit `de51268` (`git show de51268:src/Setup/NewGrid.php`). The packaging files
  removed with opensim-tools are at `146b947` (`git show 146b947:packaging/opensim-tools.postinst`).
- New code, as audited on 2026-11-20: `cli` = opensim-cli `charm-dev` (62211f4), `engine` (or `eng`) = opensim-engine
  `claude/engine-api-oscc-0d9ad2` (996c6a8), `helpers` = opensim-helpers `charm-dev` (8068f1e), `w4os-site` = w4os-site
  `charm-dev` (88574fa), `bt` = bash-tools or build-tools `dev`. A line number is the one of that commit.

## Summary

### Gaps of the new set: fixed on 2026-11-20

Verified on ursull (Debian 13, the packages of these commits installed, upgraded, reinstalled and purged; the suites of the engine
and of the CLI):

- **The ini files of a grid** are 640 for the group of the web server again at each install or upgrade, MoneyServer.ini too
  (engine `27c262b`: `GridFileService`, `GridCompletion`, `MoneyServerConfig`).
- **screen and procps** are dependencies of opensim-engine, which runs the instances (engine `27c262b`).
- **Through `sudo -u`**: `OPENSIM_IAR_PASSWORD`, `OPENSIM_CONF`, `DEBUG` and `QUIET` are kept; `-v` and `-q` reach the engine
  (`QUIET` leaves the errors only) (cli `7fa8f24`).
- **The options after a command are its own**: `opensim save iar -h URL -v` (only `--help`, or `-h` right after the command,
  is the help) (cli `7fa8f24`).
- **`opensim api [METHOD] PATH [name=value...]`** is a command of the CLI, as the CLI and its README said (cli `7fa8f24`).
- **The preset `webroot-website`** is the site of w4os-site, in `/var/lib/opensim/www` (engine `27c262b`).
- **kickstart.sh**: its dead `start_setup` is gone (cli `7fa8f24`).
- **Builds**: rest-php comes from its package in the CLI (`packaging/siblings`); the preinst of the engine replaces an old
  container-tools folder by its link (cli `7fa8f24`, engine `27c262b`).
- **Purge** of opensim-engine removes `/var/cache/opensim` and the owners of the layout (dpkg-statoverride); config, data,
  logs, backups and the account are kept (engine `27c262b`).
- **copyright** in opensim-engine (with a README.Debian), opensim-cli, opensim-helpers (`a33a609`), opensim-rest-php
  (`bd76918`) and w4os-site (`603963b`, Grav and its plugins MIT, Font Awesome Free apart).
- **MySQL `lower_case_table_names=1`**: not needed. opensim-tools never applied it (`share/mysql/conf.d/lowercase.cnf` was
  shipped, never used), and the SQL of the engine and of the helpers names the tables as OpenSimulator makes them
  (UserAccounts, GridUser, Presence, regions...), which a case-sensitive server takes as they are.

### Features not ported (Olivier decides which ones get ported, to the engine or the CLI)

Olivier's choices (2026-11-20). For 3.0: the import of a Robust.ini (the same grid, migrated first, then managed in place), the
accounts import, a watchdog that keeps the grid and the simulators online, `--version` on every executable (`opensim` listing the
versions of everything that matters), the notices that a grid or simulator restart is required, `opensim oar
pack|info|check|unpack`, the bash completion kept with the CLI, a podman/docker README of suggestions (no image yet). Not now:
`opensim command` (`opensim rest`, the shortcuts and `opensim console` do it), changing an existing grid, simulator or region
from the setup (the setup is for the first install; then the admin UI), the setup in a browser (3.0 if time allows, else 3.1 or
3.2).

Commands:
- `opensim command INSTANCE TEXT|-`: lines to a console, from stdin, through screen when there is no REST console
  (`src/Console/Commands/CommandCommand.php:29-79`). Only `opensim rest INSTANCE -- CMD` (one command, REST only) is left.
- `opensim profile list|add|default|remove` (`src/Console/Commands/ProfileCommand.php:43-105`): the packages make one profile
  per release, no command to add one for a core of elsewhere or to change the default.
- `opensim oar pack|info|check|unpack` (`src/Console/Commands/OarCommand.php:41-67`): in the engine (`eng:src/Archives/Oar.php`),
  no command.
- `opensim -V/--version`, `opensim help CMD`, `opensim list` (`src/Console/Application.php:121-130`).
- Accounts import, CSV/JSON/YAML, generated passwords in a 600 result file, `--keep-going`
  (`src/Setup/Import/ImportUsers.php:32-125`): `eng:src/Provisioning/AccountImporter.php` exists, nothing calls it; the `users`
  of a setup file are validated, never applied.
- Import of a Robust ini of another install as a new grid, with its report (`src/Setup/Import/ImportSettings.php:99-166`):
  `eng:src/Provisioning/Import/RobustImporter.php` has no caller; `--grid-settings` only brings settings into an existing grid.
- `opensim import FILE` that finds the kind of the file, and `--grid/--simulator/--users` to keep a part of a setup file
  (`src/Setup/Import/ImportFile.php:30-237`).
- Region enable/disable from the command line (`src/Setup/Actions.php:53-70`): API only.
- The hourly watchdog `opensim start` that starts again what is down (`share/cron.hourly/opensim:3-8`).
- Messages: enable/disable says "nothing to change" and "restart to apply: opensim restart ..."
  (`src/Console/Commands/ToggleCommand.php:171-192`); coloured status summary; red errors; `web show` tells the docroot.
- Bash completion: grid nicks, disabled instances for `enable`, the options of import, backup, save and load, the economy
  variants.

Setup:
- The setup in a browser (`--web`, one-time token and cookie, Host, Origin and address checks, SSH tunnel instructions,
  `--port --listen --allow --host`): `src/Web/*`, `src/Ui/WebUi.php`, `src/Setup/SetupMode.php:24-168`. The engine scenario is
  made for any client (`/setups/steps`), no web client exists.
- Advanced setup (every setting, one by one) and "Edit config" after the review (`src/Setup/StartSetup.php:45-52`,
  `src/Setup/SetupRunner.php:57-65`). Not asked any more: nick, spacing, core, console mode, web URL, helpers path, offline
  messages, web server, website, simulator name, core and public address, estate name, choice among the accounts.
- Changing an existing grid, simulator or region from the setup (modify or abandon, move a region keeping its UUID):
  `src/Setup/NewGrid.php:224-241`, `src/Setup/NewSimulator.php:406-455, 595-615`.
- The menus of the setup (grids, simulators, regions, the core and the default profile): `src/Setup/Hub.php:37-413`.
- Search as a choice of three (own, 2do.directory, none): a box now, "none" cannot be chosen with the helpers on.
- The offer, at the end, to restart what needs it, users warned (`src/Setup/Hub.php:107-139`): recorded, never offered.
- After writing, the questions enable? start now? warn the users? (`src/Setup/NewGrid.php:143-158`): always enabled and started.
- Taken place of a region: the nearest free one proposed, "Use x,y?" (`src/Setup/NewSimulator.php:767-805`): applied silently.
- Database: 10 attempts at most (`src/Setup/NewGrid.php:88-104`); the caller's own database rights (`~/.my.cnf`): the whole
  setup runs as opensim now (`src/System/Elevated.php:9-66`; now `cli:src/lib/common:73-139`).
- Questions: a yes/no kind, choices, check boxes and conditions inside a form, a hint under a field, the focus on the wrong field
  (`src/Ui/Prompts/FormPrompt.php:66-263`); in plain mode a secret left empty is kept and the default of a menu is the engine's.
- Escape goes back (it quits now), Ctrl-Q quits (`src/Ui/Prompts/Shortcuts.php:32-43`).
- Translations of the command line and of the setup (`src/I18n/I18n.php`, `locales/`): the CLI is English only.
- Offer to install a missing tool (`src/System/Packages.php:31-49`).

Container image (`packaging/container`), against `cli:share/podman` and `kickstart --podman`:
- An entrypoint without systemd that starts the instances, stops them on TERM, and shows their logs on the console
  (`packaging/container/opensim-container:7-37`); `SCREENDIR`; the volumes; the locked-down run (read-only, cap-drop, no new
  privileges); `--stop-timeout`; docker; a memory cap; the essential modules enabled; uid 10001 and no sudo in the image.

Tests (`tests/Packaging`), with nothing equivalent in the new projects:
- The systemd lifecycle of the packages (enabled on install, not restarted by an upgrade, stopped on removal, back on
  reinstall, purge), the removal of a core or a build, the opensim-cli package installed in a test, the setups in a browser and
  through a script, the container image, nginx and Apache serving the helpers for real. The other scenarios (grid, simulator,
  search, offline, economy, ban, standalone, pages) are unit tests now, without a real core nor a database.

### The other scripts of the old install (outside the first audit)

At `de51268` too, gone with the code of opensim-tools:

- `install/install.php`: the entry of the old setup, replaced by `opensim setup`.
- `install/*.Tweaks.ini` (GridCommon, OpenSim, Robust): settings laid over the examples of the core (the PID files and the
  registry in the cache and data roots, the console prompt, the Hypergrid URIs from BaseURL): now the presets of the engine
  (`share/presets`, robust, master).
- `install/build-version.sh`: the version of a build from git, now build-tools (`build-tools version`).
- `install/iar2library`, `install/inv2library`: a library of the inventory (the one every avatar sees) made from an IAR or from an
  inventory. **Not ported.**
- `install/experimental-convert-fsassets-8to9`: the FSAssets folders of OpenSimulator 0.8 renamed for 0.9. Not ported, obsolete.
- `install/experimentalDeleteAvatar`: the deletion of a test avatar, in the database. **Not ported**: the engine has no deletion
  of an avatar.
- `install/transfer-remote-simulator.sh`: a simulator of another server copied to this one, over ssh. Partly: `opensim import
  --simulator` and `opensim backup --simulator` take a simulator whose files are on this machine; the copy from the other server
  is not done.
- `deploy.yaml`, `deploy/` (Deployer): the code of opensim-debian deployed on the servers, the configs of the grids pushed from a
  private repository, simulators enabled or disabled over ssh. The packages replace the deployment of the code; the push of the
  configs from a repository is **not ported**; enabling over ssh is `ssh host opensim enable ...`.
- `web/router.php`: the router of the setup in a browser, with `src/Web` (not ported, the browser wizard is for 3.1 or 3.2).
- `etc/`, `var/`, `core/`: the folders of the bundled layout, made by the system layout of the engine now.

### Left out on purpose

The new set does these otherwise, nothing to port: the list of releases of opensimulator.org and the download of their tarballs
(the releases are packages), Mono, the choice of the layout and of its folders (the system layout of the engine), the git
submodules, `libexec/tailgrep` (the engine follows the logs), the placeholder site of opensim-web (w4os-site), the fifo protocol
of the script frontend (JSON steps of the engine), `KillMode=none` of the old unit.

## Details

Each table: what opensim-tools does, where it is (old), where it is now or "not ported".

### Commands, console, service

#### Dispatcher, help, global options, reporter

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Entry point `opensim` | bin/opensim:1-14 | cli:bin/opensim:1-140 | Now bash; the PHP part is cli:src/php/run.php |
| Command = first word that is not an option (`opensim -v start x`) | src/Console/Application.php:157-169 | bt:src/lib/bash-helpers:442-492 (COMMANDOPTS cli:bin/opensim:8) | |
| `--` ends the search for a command | Application.php:160-162 | not ported | Minor |
| Global help (commands grouped by use) | Help.php:14-65; Application.php:121-134 | cli:bin/opensim:9-38, 110-113 | Flat list, no groups |
| Usage line `opensim [-hvq] <command> ...` | Help.php:12 | partly: bt:src/lib/bash-helpers:290-306 builds it | No explicit USAGE in cli:bin/opensim |
| Explanation "a target is a grid or a simulator; --simulators adds…" | Help.php:58-59 | cli:libexec/start:5, cli:libexec/stop:5, cli:libexec/restart:5 | Per command now |
| `opensim help <cmd>` = `<cmd> --help` | Application.php:122-125 | partly: `opensim <cmd> -h/--help` cli:bin/opensim:40-44 | `opensim help start` prints the global help (`help` is not in COMMANDOPTS) |
| `opensim list` alias of help | Application.php:121 | not ported | |
| `-V/--version` (reads .version or VERSION) | Application.php:126-130, 172-181 | not ported | cli has a .version file but nothing reads it |
| `-h/--help` | Help.php:61 | bt:src/lib/bash-helpers:462-466; cli:bin/opensim:40-44 | |
| `-v/--verbose` (engine debug messages) | Help.php:62; ConsoleReporter.php:48-54 | partly: bt:src/lib/bash-helpers:477-481 | Consumed in bash: not passed to libexec, DEBUG not exported, not in PRESERVE_ENV (cli:bin/opensim:45). Engine debug only with DEBUG=yes in the caller's environment (cli:src/php/run.php:212) |
| `-q/--quiet` (no info or step lines, errors still shown) | Help.php:63; ConsoleReporter.php:41-46, 58-60 | partly: bt:src/lib/bash-helpers:467-472 | Not forwarded to run.php: the engine output is still printed |
| `DEBUG=yes/true/1` env var | Commands/EngineCommand.php:31 | cli:src/php/run.php:212-213 | Lost across `sudo -u` (not in PRESERVE_ENV) |
| Unknown command message | Application.php:147 | cli:bin/opensim:136-138 | "Unrecognized command" |
| Instance commands run as the system user (start, stop, restart, status, command, console, screen, enable, disable, save, load, backup) | Application.php:46, 136-142 | cli:bin/opensim:132-135; cli:src/lib/common:73-84 | Now every command except setup and install-dotnet, including rest, api-key, ban, web, next, ports |
| System user lookup | Application.php:138 (Layout::systemUser) | cli:src/php/run.php:205-208; eng:src/Config/Layout.php:224 | |
| Environment kept across sudo | n/a | cli:bin/opensim:45 | Missing from the list: OPENSIM_IAR_PASSWORD, DEBUG, OPENSIM_CONF |
| Reporter: `opensim:` prefix on stderr | ConsoleReporter.php:35-39 | cli:src/php/run.php:50-53 | |
| Reporter: errors in red, shown even with -q | ConsoleReporter.php:41-46 | partly: cli:src/php/run.php:55-58 | `opensim: error:` prefix, no colour |
| Reporter: one replaced progress line on a terminal, cut to the width | ConsoleReporter.php:56-66 | partly: cli:src/php/run.php:65-79 (pending) | No cut to the terminal width |
| Reporter: same step not repeated off a terminal | ConsoleReporter.php:68-71 | not ported | Every step is printed |
| `--layout --format=env` (layout as shell exports) | n/a | cli:bin/opensim:50-105 | New |

#### start / stop / restart / status

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `opensim start [target...]`, everything enabled when none | Commands/StartCommand.php:21-30 | cli:libexec/start:1-9; cli:src/php/run.php:226-227; eng:src/OpenSim.php:47-59 | |
| start `--simulators` (also the grid's simulators, after its Robust) | StartCommand.php:24 | cli:src/php/run.php:209; eng:src/OpenSim.php:259-263 | `--sims` alias is new |
| Robust started before simulators | engine (enabled order) | eng:src/OpenSim.php:128-154 | |
| An instance already running is left alone | engine | eng:src/Runtime/StartService.php:48-49 | |
| Start waits on the log and the services until ready | engine | eng:src/Runtime/StartService.php:137, 264-330 | |
| `opensim stop [target...]` | Commands/StopCommand.php:21-32 | cli:libexec/stop; cli:src/php/run.php:228-229; eng:src/OpenSim.php:69-82 | |
| `stop now` (first word: no warning) | EngineCommand.php:56-61 | cli:src/php/run.php:216-217; eng:src/Runtime/StopService.php:42 | |
| stop `--simulators` (simulators before Robust) | StopCommand.php:24 | cli:src/php/run.php:209; eng:src/OpenSim.php:77, 275-281 | |
| 2-minute warning, 3 reminders (`alert ... stopped in N seconds`) | engine | eng:src/Runtime/StopService.php:19-20, 104-124, 108 | |
| `change region root` so the alert reaches every region | engine | eng:src/Runtime/StopService.php:101 | |
| No wait when no user is in its regions (checked every 5 s) | engine | eng:src/Runtime/StopService.php:85-97, 111-123, 151-163 | |
| No answer about users: stop now | engine | eng:src/Runtime/StopService.php:89-92 | |
| Final `alert Shutting down now.` | engine | eng:src/Runtime/StopService.php:125-127 | |
| Only running OpenSim simulators are warned | engine | eng:src/Runtime/StopService.php:41 | |
| `shutdown` sent, up to 120 s wait showing the last log line | engine | eng:src/Runtime/StopService.php:23, 46-55, 172-191 | |
| Forced stop: TERM, then KILL after 15 s | engine | eng:src/Runtime/StopService.php:56-65, 194-203 | |
| Countdown on one line on a terminal | engine | eng:src/Runtime/StopService.php:139-169 | |
| Mapped ports closed on stop | engine | eng:src/Runtime/StopService.php:67 | |
| `opensim restart [--simulators] [now] [target...]` | Commands/RestartCommand.php:21-32 | cli:libexec/restart; cli:src/php/run.php:230-231; eng:src/OpenSim.php:92-112 | |
| restart: an instance that did not stop is not started again | engine | eng:src/OpenSim.php:103-104 | |
| Exit 1 when one instance failed (one still loading is not a failure) | EngineCommand.php:39-48 | cli:src/php/run.php:191-200 | |
| Target = grid name (its Robust) | engine | eng:src/OpenSim.php:190-195 | |
| Target = `grid.sim` (dot kept in the name) | engine | eng:src/Controllers/AbstractController.php:76-79 | |
| Target = `host:port` address | engine | eng:src/OpenSim.php:175-189 | |
| Target = an .ini path | engine | eng:src/OpenSim.php:167-173 | |
| Target = a standalone grid name (its simulator) | engine | eng:src/OpenSim.php:239-250 | |
| Unknown target: "no ini file found for X" | engine | eng:src/OpenSim.php:251-256 | |
| Enabled instances = links in robust.d / opensim.d | engine | eng:src/OpenSim.php:128-154 | Also robust-enabled and simulators-enabled now |
| `opensim status` table INSTANCE TYPE PID USER %CPU %MEM RSS | Commands/StatusCommand.php:33-53 | cli:libexec/status; cli:src/php/run.php:152-183 | |
| status `[down]` for a stopped instance | StatusCommand.php:36-37 | cli:src/php/run.php:158-159 | |
| status summary `N up, M down - cpu - mem - rss` | StatusCommand.php:54-56; 60-67 | cli:src/php/run.php:184-187; eng:src/Runtime/Status.php:21-38 | |
| status summary in colour (green up, red down) | StatusCommand.php:25, 62-64 | not ported | Plain text |
| Process data (cpu, mem, rss, user) | engine | eng:src/Runtime/SystemProcessTable.php:15 | |
| Start/stop/restart through the API | n/a | eng:src/Api/Api.php:1516-1560 (`/instances/{name}/{action}`) | New |

#### console / screen / command / rest

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `opensim console <instance>` attaches to the screen session | Commands/ConsoleCommand.php:43-48 | cli:libexec/console; cli:src/php/run.php:259-263 | |
| `screen` alias | ConsoleCommand.php:24 | cli:libexec/screen (symlink to console); cli:bin/opensim:8 | |
| "Ctrl-A D to leave it running" hint | ConsoleCommand.php:45 | cli:src/php/run.php:261 | |
| Remote console prompt when there is no screen (Ctrl-D leaves) | ConsoleCommand.php:50-74 | cli:src/php/run.php:264-288 | |
| "no ini file found" / "is not running" | ConsoleCommand.php:38-41, 52-55 | cli:src/php/run.php:252-258, 267-270 | |
| `opensim command <instance> <text>` | Commands/CommandCommand.php:29-68 | partly: `opensim rest INSTANCE -- CMD` cli:src/php/commands.php:503-523 | Remote console only, no screen fallback. cli:README.md:64 unchecked |
| `command <instance> -` (each stdin line; empty line answers a prompt) | CommandCommand.php:41-42, 70-79 | not ported | |
| command via screen, 1 s between lines | CommandCommand.php:57-65 | not ported | |
| `opensim rest --url --user (-- cmd \| - \| --repl)` | Commands/RestCommand.php:13-28 | cli:libexec/rest; cli:src/php/commands.php:524-527 | Now runs as the system user (was as the caller) |
| rest password from OPENSIM_REST_PASSWORD | RestCommand.php:17 | cli:bin/opensim:45 (preserved); cli:libexec/rest:6 | |
| `rest INSTANCE -- cmd` (address and account from its config) | n/a | cli:src/php/commands.php:503-523 | New; secrets masked (eng:src/Text/Secrets.php:33) |

#### enable / disable

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `enable/disable <instance...>` (grid or simulator by instance name) | Commands/ToggleCommand.php:65-101 | cli:libexec/enable, cli:libexec/disable; cli:src/php/commands.php:78-115 | |
| Name matched case-insensitively / snake | ToggleCommand.php:69-79 | cli:src/php/commands.php:82-95 | |
| Unknown name lists the known ones | ToggleCommand.php:80-87 | partly: cli:src/php/commands.php:96-99 | No "known: ..." list |
| "already enabled/disabled" | ToggleCommand.php:89-92 | cli:src/php/commands.php:103-105 | |
| "enabled: opensim start NAME" hint | ToggleCommand.php:95 | cli:src/php/commands.php:108 | |
| Specific failures (no Robust config, permission) | ToggleCommand.php:109-124 | partly: cli:src/php/commands.php:110 | Generic "could not update" |
| `<grid> <option> [variant] [url prefix]` via GridOptions | ToggleCommand.php:59-63, 143-199 | cli:src/php/commands.php:51-76; eng:src/Provisioning/GridOptions.php:71, 820, 871 | |
| search / economy beta\|gloebit / profiles / offline helpers\|robust / ossl-extended | Help.php:26-29; ToggleCommand.php:33 | eng:share/presets/*/option.conf via eng:src/Provisioning/GridOptions.php:53 | Usage text lists only search, economy, profiles |
| Unknown option lists the available ones | ToggleCommand.php:150-153 | partly: cli:src/php/commands.php:56-59 | Usage line only |
| "nothing to change: already X" | ToggleCommand.php:171-175 | not ported | Always prints "X enabled for grid" |
| What changed (N simulators, Robust, OpenSim.ini, files) + "restart to apply: opensim restart ..." | ToggleCommand.php:177-192 | not ported | Data is still in the engine result |
| Notes of the option | ToggleCommand.php:194-196 | cli:src/php/commands.php:71-73 | |

#### ports

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Table INSTANCE TYPE PORT PROTO USE FOR | Commands/PortsCommand.php:98-103 | cli:libexec/ports; cli:src/php/ports.php:118-134; eng:src/Controllers/AbstractController.php:362 | |
| `[instance...]` filter | PortsCommand.php:40-55 | cli:src/php/ports.php:69-80 | Also addresses and ini files; stops at the first unknown name |
| `--publish` (`-p` for podman/docker; private and console ports bound to the private address) | PortsCommand.php:71-84 | cli:src/php/ports.php:91-100 | |
| `--ufw` (allow public, comment the private ones) | PortsCommand.php:86-96 | cli:src/php/ports.php:101-117 | |
| `--private-address=ADDR` (default 127.0.0.1) | PortsCommand.php:33, 38-39 | cli:src/php/ports.php:27-30, 49-53, 67 | Also accepts a separate value; IPv4/IPv6 checked |
| `--from` / `--manage-from` (ufw rules for given sources) | n/a | cli:src/php/ports.php:31-41, 54-66, 108-111 | New |
| `--publish` and `--ufw` are exclusive; unknown option refused | n/a | cli:src/php/ports.php:21-24, 42-44 | New |
| "no enabled instance" | PortsCommand.php:56-60 | cli:src/php/ports.php:81-84 | |

#### next

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `next port [MIN] [COUNT]` (default 9010, 1) | Commands/NextCommand.php:66-77 | cli:libexec/next; cli:src/php/commands.php:309-318; eng:src/Network/Ports.php:112 | |
| Ports known to the grids' registries excluded | NextCommand.php:72-75 | cli:src/php/commands.php:315; eng:src/Provisioning/GridRegistry.php:51 | |
| `-e PORT` (repeatable) | NextCommand.php:53-62 | cli:src/php/commands.php:299-307 | `--exclude` alias new; missing value refused |
| `next <grid> location [X,Y] [COUNT]` | NextCommand.php:40-48, 80-104 | cli:src/php/commands.php:288-293, 319-338; eng:src/Provisioning/Places.php:25 | |
| Default place: the grid centre | NextCommand.php:99 | cli:src/php/commands.php:332; eng:src/Models/Grid.php:1288 | |
| `-e X,Y` places taken | NextCommand.php:93-98 | cli:src/php/commands.php:328-331 | |
| Warning when the grid does not answer (regions incomplete) | NextCommand.php:106-108 | cli:src/php/commands.php:339-341 | |

#### web

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `web [GRID] [show]` (services, web URL, helpers) | Commands/WebCommand.php:130-137 | cli:libexec/web; cli:src/php/commands.php:272-281 | Pages and who serves them are new |
| show: Docroot line + "helpers not installed: apt install opensim-helpers" | WebCommand.php:133 | not ported | |
| `web [GRID] check` (HTTP status of each URL; exit 1 on 404/403/50x) | WebCommand.php:116-128 | cli:src/php/commands.php:261-271 | |
| `web [GRID] snippet caddy\|nginx\|apache` | WebCommand.php:98-106 | cli:src/php/commands.php:237-251; eng:src/Provisioning/Web/Snippets.php:131 | |
| `--docroot`, `--socket`, `--grid` options | WebCommand.php:43-57 | cli:src/php/commands.php:164-178 | |
| Grid optional when only one; error lists the grids | WebCommand.php:72-91 | cli:src/php/commands.php:190-202 | Shorter messages, no "make one with opensim setup" |
| `web [GRID] set PAGE URL` | n/a | cli:src/php/commands.php:203-217; eng:src/Provisioning/Web/SitePages.php:96 | New |
| `web [GRID] api caddy\|nginx\|apache [--port] [--web-group]` | n/a | cli:src/php/commands.php:219-233 | New |

#### ban

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `ban [GRID] NAME\|UUID...` (First Last, First.Last, First.Last@grid, UUID) | Commands/BanCommand.php:33-103; BanPlan.php:38-55 | cli:libexec/ban; cli:src/php/commands.php:346-404; cli:src/php/BanPlan.php:27-45; eng:src/Models/Avatar.php:1396 | |
| Local grids only; grid optional when only one | BanCommand.php:52-62 | cli:src/php/commands.php:357-367 | |
| `--output DIR` (default cwd), must be writable | BanCommand.php:41-49, 68-70 | cli:src/php/commands.php:348-356, 371-373 | Written by the system user now |
| Account database configured and readable | BanCommand.php:72-79 | cli:src/php/commands.php:374-380 | |
| Cleanup SQL of what they left, per service database | BanPlan.php:64-72 | cli:src/php/BanPlan.php:54-62; eng:src/Models/Avatar.php:1443, 1472 | |
| Ban SQL in every estate | BanPlan.php:73-79 | cli:src/php/BanPlan.php:63-68; eng:src/Models/Estate.php:223 | |
| Hypergrid traveling data | BanPlan.php:80-82 | cli:src/php/BanPlan.php:69-71; eng:src/Models/Avatar.php:1502 | |
| Files ban.users / ban.cleanup / ban.ban / ban.hg_traveling_data | BanPlan.php:106-119; BanCommand.php:88-91 | cli:src/php/BanPlan.php:80-89; cli:src/php/commands.php:389-395 | Write errors now checked |
| Nothing run; counts + "back up, stop simulators, `cat ... \| mysql`" | BanCommand.php:94-101 | cli:src/php/commands.php:396-402 | |
| Problems reported (no user, unknown database, no estates) | BanPlan.php:44-71 | cli:src/php/BanPlan.php:33-61 | |

#### save / load (iar, oar)

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `save\|load iar\|oar` through the simulator console | Commands/ArchiveCommand.php:43-63 | cli:libexec/save, cli:libexec/load; cli:src/php/commands.php:407-413; eng:src/Archives/ArchiveRunner.php:81-166 | |
| "no install found" check | ArchiveCommand.php:52-56 | not ported | Engine error instead |
| `[GRID [SIM\|REGION]]`, `_SIM`, `grid.sim` | ArchiveCommand.php:14-17 | eng:src/Archives/Instances.php:26-60 | |
| save iar `-h/--home=URL` | ArchiveCommand.php:22 | partly: eng:src/Archives/Archives.php:22 | `-h` is taken as help (cli:bin/opensim:42, bt:src/lib/bash-helpers:462); `--home=` works |
| save iar `-v/--verbose` | ArchiveCommand.php:22 | partly: eng:src/Archives/Archives.php:23 | Consumed by bash-helpers (bt:src/lib/bash-helpers:477), never reaches the engine |
| save iar `--noassets`, `--perm=`, `--skipbadassets`, `-c` creators, `-e` exclude, `-f` excludefolder | ArchiveCommand.php:22-23; completion:95 | eng:src/Archives/Archives.php:24-29 | |
| load iar `-m/--merge` | ArchiveCommand.php:24 | eng:src/Archives/Archives.php:33 | |
| save oar `--region`, `--all`, `--noassets`, `-h/--home`, `--publish`, `--perm=` | ArchiveCommand.php:25-26 | eng:src/Archives/Archives.php:35-42 | Same `-h` problem |
| load oar `--region`, `--merge`, `--skip-assets`, `--force-terrain`, `--force-parcels`, `--no-objects`, `--displacement`, `--rotation`, `--default-user` | ArchiveCommand.php:27; completion:101 | eng:src/Archives/Archives.php:44-55 | Also `--bounding-origin/size` |
| `--keep N` rotation after save | Help.php:45, 47 | eng:src/Archives/ArchiveRunner.php:103, 161-163; eng:src/Archives/Rotation.php:30 | |
| FILE: a name in `<data>/backups/iar\|oar`, or a path | ArchiveCommand.php:29 | eng:src/Archives/ArchiveRunner.php:118, 302-330 | |
| load picks the newest file when none given | ArchiveCommand.php:29 | eng:src/Archives/ArchiveRunner.php:320-322 | |
| Default names (grid-first-last[-modes]-stamp.iar, grid-sim[-region]...) | engine | eng:src/Archives/Archives.php:10-12, 184-223 | |
| IAR password: argument, OPENSIM_IAR_PASSWORD, or asked without echo | ArchiveCommand.php:30 | partly: eng:src/Archives/ArchiveRunner.php:62-72, 279-294 | OPENSIM_IAR_PASSWORD is dropped by `sudo -u` (missing from cli:bin/opensim:45) |
| Waits for the log; checks the file exists after save | engine | eng:src/Archives/ArchiveRunner.php:152-160, 344 | |

#### backup

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `backup` (every grid) / `backup GRID` / `backup GRID SIM\|_SIM` | Commands/BackupCommand.php:15-17 | cli:libexec/backup; cli:src/php/commands.php:416-471; eng:src/Archives/BackupService.php:56-77 | |
| `--logs` | BackupCommand.php:39-40 | cli:src/php/commands.php:422-423; eng:src/Archives/BackupService.php:102, 139 | |
| `--archives` (include the users' iar/oar) | BackupCommand.php:39-40 | cli:src/php/commands.php:422-423; eng:src/Archives/BackupService.php:103, 180 | |
| `--output DIR` (relative to cwd) | BackupCommand.php:41-50 | cli:src/php/commands.php:428-433 | |
| Default output | BackupCommand.php:20 (`<data>/backups/admin`) | eng:src/Archives/BackupService.php:42-46, 88-93 | Now BackupRoot/<grid>[/<sim>]; fails if the root is missing |
| `--keep N` | BackupCommand.php:51-58 | cli:src/php/commands.php:432-434; eng:src/Archives/Rotation.php:16 | |
| Unknown option refused | BackupCommand.php:59-62 | cli:src/php/commands.php:436-437 | |
| "no install found" check | BackupCommand.php:69-72 | not ported | Engine says "no grid here" |
| `--oars` (OAR of every region of running simulators) | n/a | cli:src/php/commands.php:442-444, 479-497; eng:src/Archives/OarBackup.php:41 | New |
| `--simulator FILE [GRID]` (backup of another install) | n/a | cli:src/php/commands.php:424-427, 446-463; eng:src/Archives/SourceBackup.php:44, 87 | New |

#### import / setup

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `import [GRID] FILE` (kind detected from the file) | Commands/ImportCommand.php:14-30 (ImportFile) | partly: cli:libexec/import:6-135 | Kind now given by option; no `[GRID] FILE` override of the grid nick |
| import a setup file (simulators and regions) | ImportCommand.php:14 | cli:libexec/import:79-83; cli:src/lib/common:142-163; eng:src/Provisioning/SetupApply.php:50, 213 | A grid creation (`grid.name`) or `users` in the file are not applied |
| import `--grid` (Robust config of another install) | ImportCommand.php:14 | partly: `--grid-settings FILE GRID` cli:libexec/import:33-38, 61-77 | Grid OpenSim.ini option settings only, not a Robust config |
| import `--simulator` | ImportCommand.php:14 | cli:libexec/import:27-32, 84-133 | Now `--simulator FILE` (several), `--no-scripts` |
| import `--users` (account list), `--result FILE`, `--format csv\|json\|yaml`, `--keep-going` | ImportCommand.php:14-15; completion:87 | not ported | eng:src/Provisioning/AccountImporter.php exists, no CLI or API use |
| import `--core DIR`, `--name NAME` | ImportCommand.php:14 | cli:libexec/import:39-50, 85 | One simulator only |
| import `--apply` (plan otherwise) | ImportCommand.php:14 | cli:libexec/import:51 | |
| `setup` wizard | Commands/SetupCommand.php:21-34 | cli:bin/opensim:116-124; cli:libexec/setup | |
| setup: refuses when sudo to the system user is not possible | SetupCommand.php:23-31 | partly: cli:src/lib/common:73-84, 87-139 (run_setup) | sudo is tried directly; no message given up front |
| setup `--file --check --result` | completion:74 | partly: `--config --prepare --apply --region-config` cli:libexec/setup:5-50 | Different option set |

#### api / api-key / profile / oar / install-dotnet

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `opensim api [METHOD] PATH [k=v] [--data=JSON\|@FILE\|-]` | Commands/ApiCommand.php:17-51 | partly: eng:bin/opensim-api.php:41-65; eng:src/Api/Cli.php:27-63 | No `opensim api` (not in cli:bin/opensim:8), yet cli:libexec/import:75, cli:libexec/restore:8 and cli:README.md:91 tell users to run it. opensim-api is not on PATH in the packages |
| api `--grid=NICK` | ApiCommand.php:25, 35-41 | eng:bin/opensim-api.php:42-48 | |
| `api-key create LABEL` (shown once) | Commands/ApiKeyCommand.php:32-49 | cli:libexec/api-key; cli:src/php/commands.php:129-135; eng:src/Security/ApiKeys.php:49 | No "Authorization: Bearer" hint; RuntimeException not caught |
| `api-key list` | ApiKeyCommand.php:50-74 | partly: cli:src/php/commands.php:136-148; eng:src/Security/ApiKeys.php:71 | No header row, no "active" state |
| `api-key revoke ID\|LABEL` | ApiKeyCommand.php:75-84 | cli:src/php/commands.php:149-156; eng:src/Security/ApiKeys.php:85 | |
| `profile list` | Commands/ProfileCommand.php:43-50 | not ported | cli:README.md:127 unchecked |
| `profile add NAME --core [--etc] [--data] [--default]` | ProfileCommand.php:52-83 | not ported | Packages make one profile per release: eng:src/Config/PackagedProfiles.php, eng:bin/opensim-profiles.php |
| `profile default NAME` | ProfileCommand.php:85-92 | not ported | Latest release becomes the default automatically (PackagedProfiles) |
| `profile remove NAME` | ProfileCommand.php:94-105 | not ported | Forgotten automatically when a release is removed |
| OPENSIM_CONF env var | ProfileCommand.php:35 | not ported (CLI) | Not in PRESERVE_ENV |
| `oar pack DIR [FILE]` | Commands/OarCommand.php:41-47 | not ported (CLI); eng:src/Archives/Oar.php:40 | cli:README.md:128 unchecked |
| `oar info FILE` | OarCommand.php:49-54 | not ported (CLI); eng:src/Archives/Oar.php:127 | |
| `oar check FILE` (exit 1 on problems) | OarCommand.php:56-60 | not ported (CLI); eng:src/Archives/Oar.php:178 | |
| `oar unpack FILE [DIR]` | OarCommand.php:62-67 | not ported (CLI); eng:src/Archives/Oar.php:107 | |
| `install-dotnet [MAJOR]` (default 8) | Commands/InstallDotnetCommand.php:31-39 | cli:libexec/install-dotnet; cli:src/php/run.php:289-297; eng:src/Runtime/DotnetInstaller.php:51 | |
| install-dotnet runs as root | Help.php:56 | cli:bin/opensim:126-130 | Always as root now, so the per-user (home) install is not reachable |
| install-dotnet interactive prompts unless `-y` or no tty | InstallDotnetCommand.php:30, 37 | partly: cli:src/php/run.php:295-296 (StdUi) | Never asks; `-y` ignored |

#### Bash completion

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Completion installed | share/bash_completion.d/opensim:128 | cli:share/bash-completion/opensim:72; cli:packaging/opensim-cli.yaml:70-71 | |
| Adds /opt/opensim/bin to PATH | completion:3 | not ported | Not needed with /usr/bin/opensim |
| OPENSIM_ETC_DIRS (default /etc/opensim /opt/opensim/etc) | completion:6-9 | cli:share/bash-completion/opensim:4-7 | /etc/opensim only |
| Command list | completion:51 | cli:share/bash-completion/opensim:26 | Missing import, region, simulator, settings, posts; `command` dropped |
| Enabled instances | completion:26-30 | cli:share/bash-completion/opensim:10-18 | |
| All instances incl. disabled (for enable) | completion:17-24, 64-65 | not ported | `enable` only proposes enabled instances |
| Grid nicks | completion:34-42 | not ported | |
| start / stop / restart (`now` at position 2, --simulators) | completion:55-60 | cli:share/bash-completion/opensim:36-40 | |
| enable/disable features + economy `beta gloebit` | completion:62-67 | partly: cli:share/bash-completion/opensim:45-46 | No variants, no position logic |
| console/screen/command | completion:69-72 | cli:share/bash-completion/opensim:42-43 | No `command` |
| ports `--publish --ufw` | completion:69-72 | cli:share/bash-completion/opensim:33-34 | No `--from/--manage-from` |
| setup options | completion:73-75 | cli:share/bash-completion/opensim:30-31 | New option set |
| next `port` / grid / `location` | completion:76-81 | partly: cli:share/bash-completion/opensim:51-52 | No grids, no position logic |
| import options | completion:82-89 | not ported | |
| save/load iar/oar option lists | completion:90-103 | partly: cli:share/bash-completion/opensim:57-58 | `iar oar` + instances only |
| backup options + directory for `--output` | completion:104-111 | not ported | cli:share/bash-completion/opensim:60-61 completes nothing |
| web show/snippet/check + servers by position | completion:112-123 | partly: cli:share/bash-completion/opensim:48-49 | Flat list, no set/api |
| ban, api-key, restore | n/a | cli:share/bash-completion/opensim:54-55, 63-67 | New |

#### tailgrep, cron, systemd

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `libexec/tailgrep PATTERN FILE` (tail until a pattern shows) | libexec/tailgrep:1-21 | not ported as a tool | Same idea inside the engine: eng:src/Runtime/StartService.php:309, eng:src/Archives/ArchiveRunner.php:344, eng:src/Runtime/LogFile.php:31-50. Unused in the kit |
| Hourly cron `opensim start` as the script owner (restarts what is down) | share/cron.hourly/opensim:3-8 | not ported | No watchdog or timer; the service starts only at boot. Scheduler (eng:src/Scheduling/Scheduler.php) has no such task |
| Cron log in /opt/opensim/var/logs/cron.log | share/cron.hourly/opensim:8 | not ported | |
| systemd `opensim.service`: ExecStart start / ExecStop stop now | share/systemd/system/opensim.service:8-9 | cli:packaging/opensim.service:15-16 | |
| service User=magic, WorkingDirectory=/opt/opensim | opensim.service:6-7 | cli:packaging/opensim.service:12-14 | Now User/Group opensim, WorkingDirectory=~ |
| After=syslog network | opensim.service:3 | cli:packaging/opensim.service:4-5 | Now network-online, mariadb, mysql |
| KillMode=none | opensim.service:10 | not ported | Now Type=oneshot, RemainAfterExit=yes, TimeoutStopSec=15min (cli:packaging/opensim.service:10-18) |
| Service enabled on install, stopped on remove | n/a | cli:packaging/opensim-cli.postinst:6-18; cli:packaging/opensim-cli.prerm:6-12; cli:packaging/opensim-cli.postrm | New |
| Daily backup timer (`backup --keep 14`, 03:30) | n/a | cli:packaging/opensim-backup.service:10; cli:packaging/opensim-backup.timer:5 | New |
| Weekly OAR timer (`backup --oars --keep 4`, Sunday 05:00) | n/a | cli:packaging/opensim-backup-oar.service:10; cli:packaging/opensim-backup-oar.timer:5 | New |

### Setup and import

#### Entry, modes, frontends
| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| First question: "What do you want to set up?" grid / simulator joining a grid | src/Setup/StartSetup.php:36-43 | engine:src/Provisioning/QuickSetup.php:84-108, 646-669 | Now a menu: Grid, Standalone, Simulator (join other grid), Speculoos, OSGrid and any found preset. Standalone is new |
| Menu cursor always reset to the first item (no landing on a public grid by accident) | – | engine:src/Provisioning/QuickSetup.php:663-665 | New safety guard |
| "How do you want to go on?" Quick / Advanced | src/Setup/StartSetup.php:45-52 | partly: engine:src/Provisioning/QuickSetup.php:358-391 | Quick only. Advanced ("every setting, one by one") is not ported |
| Edit config after refusing the plan (re-ask everything prefilled) | src/Setup/SetupRunner.php:57-65; src/Setup/Hub.php:218-223 | partly: Back button, engine:src/Provisioning/QuickSetup.php:497-501 | Back returns to the quick form/options, not to a full field-by-field editor |
| Setup mode: terminal / web page / script frontend (--terminal, --web, --frontend DIR with named pipes) | src/Setup/SetupMode.php:24-51, 130-140 | partly: cli:src/lib/ui:4,28 (OPENSIM_SETUP_UI=plain\|dialog, FE_INPUT_FD) | Terminal (dialog or plain) only. Web mode not ported. The script frontend is replaced by --config/--prepare/--apply (cli:libexec/setup:21-92) |
| Web port option --port (1024-65535) | src/Setup/SetupMode.php:59-69 | not ported | No web wizard |
| SSH-aware networking: --listen local/all/IP, --allow ADDR/any, --host, SSH_CONNECTION detection, refuse open server without --allow | src/Setup/SetupMode.php:83-128 | not ported | kickstart --remote runs setup over ssh instead (cli:kickstart.sh:222-260) |
| Instructions text (one-time URL, unencrypted warning, firewall hint, ssh -L tunnel) | src/Setup/SetupMode.php:150-168 | not ported | – |
| Prefill answers from a file | (setup files via SetupRunner) | cli:libexec/setup:78-85, 157-169 | --config JSON fills form fields and check boxes by name |
| Prepare answers without applying, saved mode 600 | – | cli:libexec/setup:42-48, 172-180; engine:src/Provisioning/QuickSetup.php:530-548 | New |
| Apply a prepared config without questions; abort if target drifted | – | cli:libexec/setup:53-74; engine:src/Provisioning/QuickSetup.php:598-643 | New. DB admin comes from OPENSIM_DB_ADMIN_USER/PASS |
| Questions still asked in file mode (credentials, admin user, password, try again) | src/Setup/SetupRunner.php:26 | partly: engine:src/Provisioning/DatabaseService.php:495-536; cli:libexec/setup:56-67 | Admin is taken from env in --apply, else the Database screen |
| Run writes as the install's system user (Elevated, --apply-grid/--apply-simulator/--apply-action) | src/Setup/NewGrid.php:170-184; src/Setup/NewSimulator.php:196-209; src/Setup/Hub.php:146-164 | cli:src/lib/common:73-139 (as_system_user, run_setup); cli:bin/opensim:116-124 | The whole setup runs as the system user. Private inputs are copied with umask 077 |

#### New grid: identity questions
| Feature | Old | Now | Note |
|---|---|---|---|
| Grid name, default random name | src/Setup/StartSetup.php:62 | engine:src/Provisioning/QuickSetup.php:1094, 438-442; engine:src/Provisioning/GridPlanner.php:144-147; engine:src/Models/Grid.php:158-166 | Same RandomName generator |
| Grid nick (derived, read-only after creation) | src/Setup/NewGrid.php:223 (Grid::fields nick) | partly: engine:src/Models/Grid.php:167-178; engine:src/Provisioning/GridPlanner.php:183 | Derived from the name. Not asked in quick setup |
| Grid URL / login host:port, default http://hostname -f:next free port | src/Setup/StartSetup.php:64-69 | engine:src/Provisioning/QuickSetup.php:1095, 251-253; engine:src/Models/Grid.php:515 (parseLoginUri) | Field is now "Login URI". https is refused/flagged |
| Login URI validation regex host:port ≤65535 | src/Setup/StartSetup.php:170-186 | engine:src/Models/Grid.php:515-; engine:src/Provisioning/QuickSetup.php:1246-1251 (remote) | – |
| Hostname proposal (hostname -f) | via GridPlanner | engine:src/Provisioning/GridPlanner.php:120-123; cli:libexec/setup:137 (hostname prefill) | kickstart passes the selected host's hostname |
| Grid owner "First Last" with name validation | src/Setup/StartSetup.php:70-77 | engine:src/Provisioning/QuickSetup.php:1096, 280-286; engine:src/Models/Avatar.php:801 | Now "Avatar name", default "<Host> God" or DEFAULT_OWNER_NAME (QuickSetup.php:1053-1062) |
| Owner password ≥6 chars, no double quote | src/Setup/StartSetup.php:78-85 | engine:src/Models/Avatar.php:881-889 | Now also max 16. Default from DEFAULT_OWNER_PASSWORD (QuickSetup.php:1063) |
| Password confirmation | – | engine:src/Provisioning/QuickSetup.php:1098, 779-782 | New |
| Owner email optional, validated | src/Setup/StartSetup.php:86-94 | engine:src/Provisioning/QuickSetup.php:1099; engine:src/Models/Avatar.php:892-901 | – |
| Hypergrid (grid group) | src/Setup/NewGrid.php:223 (Grid::fields) | engine:src/Provisioning/QuickSetup.php:39-44 | Check box, default on |
| Standalone flag | (field existed) | engine:src/Provisioning/QuickSetup.php:87-88, 204 | A setup type now |
| Region spacing (free blocks) | src/Setup/NewGrid.php:223; planText :331 | partly: engine:src/Models/Grid.php:187-194; shown in rows engine:src/Provisioning/QuickSetup.php:1439-1443 | Not asked. Settable only via the setup of a prepared config |
| Core choice when several cores | src/Setup/NewGrid.php:203-207, 223 | partly: engine:src/Models/Grid.php:195-202; engine:src/Provisioning/GridPlanner.php:186-188 | Not asked: the default profile's core is used |
| Grid already configured: Modify its settings / Abandon | src/Setup/NewGrid.php:224-241 | partly: engine:src/Provisioning/QuickSetup.php:270-272 ("taken") | Existing grids cannot be modified by setup. Use opensim settings (cli:libexec/settings) |

#### New grid: network and ports
| Feature | Old | Now | Note |
|---|---|---|---|
| Public port proposal (first free block of ten ending in 2, from 8000) | src/Setup/StartSetup.php:67 | engine:src/Provisioning/GridPlanner.php:138-141 | – |
| Private port (derived, asked) | src/Setup/NewGrid.php:244-251 | engine:src/Provisioning/QuickSetup.php:1101, 249; engine:src/Provisioning/GridPlanner.php:385-390 | Optional override field |
| Grid console mode rest/screen | src/Setup/NewGrid.php:244-251 (Grid::fields console) | partly: engine:src/Models/Grid.php:240-251; engine:src/Provisioning/GridPlanner.php:218 | Mode not asked (default rest) |
| Grid console port | same | engine:src/Provisioning/QuickSetup.php:1102 | Override field |
| Grid center (x,y) | same | not ported in UI: engine:src/Models/Grid.php:230-239 | Derived |
| Web URL | same | partly: engine:src/Provisioning/GridPlanner.php:201-202; shown QuickSetup.php:1447 | Not asked; https://host |
| Helpers path (/helpers) | same; Helpers URL note src/Setup/NewGrid.php:272-280 | partly: engine:src/Provisioning/GridPlanner.php:65, 222; row QuickSetup.php:1470 | Not asked. `opensim web GRID set helpers URL` (cli:libexec/web) |
| Occupied ports of the target host | – | engine:src/Provisioning/QuickSetup.php:311-325, 360-365; cli:libexec/setup:81-83 | New |
| Port bindings (publish public/private/console, udp regions) | – | engine:src/Provisioning/QuickSetup.php:556-595 | New |
| Ports overview / firewall rules | – | cli:libexec/ports | Related tool |

#### New grid: options
| Feature | Old | Now | Note |
|---|---|---|---|
| Options asked at start (hypergrid, search, economy, ossl, web_server, web_site) | src/Setup/StartSetup.php:26, 118-129 | engine:src/Provisioning/QuickSetup.php:35-75, 720-743 | Now a check-box screen: SSL, Hypergrid, Helpers, Cross-grid Search, plus offered presets (Gloebit, Extended OSSL) |
| Helpers on/off ("serve economy and search with opensim-helpers") | src/Setup/StartSetup.php:128 (condition helpers) | engine:src/Provisioning/QuickSetup.php:45-50 | Default on |
| Search: internal (own helpers) / shared (2do.directory) / none | src/Setup/NewGrid.php:343 | partly: engine:src/Provisioning/QuickSetup.php:51-59; engine:src/Models/Grid.php:335-350 | Now a boolean: on = shared, off = internal if helpers else disabled. "No search while helpers on" cannot be chosen |
| Search internal needs helpers (validation) | engine Grid::fields callback | engine:src/Models/Grid.php:345-348 | Same |
| Currency: Beta / Gloebit | src/Setup/NewGrid.php:344 | partly: engine:share/presets/economy-gloebit/option.conf; engine:src/Provisioning/GridPlanner.php:231 | Gloebit check box (offered); beta is the default when it is off. Gloebit keys are left to the operator (note) |
| Option needs helpers → helpers forced on with notice | – | engine:src/Provisioning/QuickSetup.php:468-481, 232-235 | New |
| OSSL extended functions for parcel owners | src/Setup/NewGrid.php:345 | engine:share/presets/ossl-extended/option.conf | "Extended OSSL" check box, default on |
| Offline messages helpers (V1) / robust (V2) | Grid::fields options group, NewGrid.php:254-264 | partly: engine:src/Models/Grid.php:351-368; engine:share/presets/offline-robust/option.conf | Not offered (robust default). opensim enable/disable GRID option (cli:src/php/commands.php:36-77) |
| Web server Caddy / Apache / nginx | src/Setup/NewGrid.php:346 | partly: engine:src/Provisioning/GridPlanner.php:126-135 (detected); engine:share/presets/webserver-{caddy,apache,nginx}/option.conf; cli:libexec/web (snippet) | Not asked. All three snippets are default presets; `opensim web GRID snippet …` |
| Web site: helpers only / mini website (opensim-web) | src/Setup/NewGrid.php:346 | partly: engine:share/presets/webroot-helpers/option.conf, webroot-website/option.conf | Mini website preset exists (Offer=false), not offered by setup |
| SSL | – | engine:src/Provisioning/QuickSetup.php:38 | New, "not implemented" |
| Destination guide / grid status rows (on with helpers) | – | engine:src/Provisioning/QuickSetup.php:1475-1476 | New |

#### Database
| Feature | Old | Now | Note |
|---|---|---|---|
| Database host/name/user/password questions | src/Setup/NewGrid.php:293-307; src/Setup/NewSimulator.php:652-678 | partly: engine:src/Provisioning/QuickSetup.php:978-1020 | Asked only when the check fails (Database screen), or in --prepare |
| Admin user/password to create DB and user | engine DatabaseService | engine:src/Provisioning/DatabaseService.php:495-536; engine:src/Provisioning/QuickSetup.php:1017-1018, 871-873 | Fields on the Database screen |
| Create DATABASE, CREATE USER, GRANT; show the commands | engine | engine:src/Provisioning/DatabaseService.php:89, 234, 291, 297, 329-340 | Shared engine code |
| Nothing written before DBs work; retry loop (max 10) then abort | src/Setup/NewGrid.php:88-104; src/Setup/NewSimulator.php:97-118 | partly: engine:src/Provisioning/QuickSetup.php:867-894 | Re-asks via the screen with the reasons. No 10-attempt cap. ABORT aborts |
| Random DB password, never "changeme"; reuse a known/found password | src/Setup/NewGrid.php:269-288 | engine:src/Provisioning/GridPlanner.php:249-262; engine:src/Provisioning/QuickSetup.php:1026-1040 (pinned) | – |
| Session memory of account/admin passwords | src/Setup/NewGrid.php:284, 301, 306 | engine:src/Provisioning/DatabaseService.php:62-87; QuickSetup.php:871-875 | – |
| Simulator database driver MySQL/SQLite | – | engine:src/Provisioning/QuickSetup.php:1107-1110, 698-703 | New |
| Simulator's own database (grid account reused) | src/Setup/NewSimulator.php:618-620 | engine:src/Models/Simulator.php:~292-303; engine:src/Provisioning/SimulatorPlanner.php | Not asked separately |
| MySQL lower_case_table_names=1 conf | share/mysql/conf.d/lowercase.cnf:1-2 | not ported | No conf.d or .cnf anywhere. kickstart phase_database (cli:kickstart.sh:849-890) does not set it |

#### Estate owner / accounts
| Feature | Old | Now | Note |
|---|---|---|---|
| Owner account created in the new grid (Robust console) | src/Setup/SetupRunner.php:67-70 (via plans) | engine:src/Provisioning/QuickSetup.php:904-916; engine:src/Provisioning/GridAccounts.php:186 | After Robust is up. A standalone makes it itself |
| Estate name question | src/Setup/NewSimulator.php:688-716 | partly: engine:src/Models/Simulator.php:~304; cli:libexec/simulator (--estate) | Not asked in quick setup |
| Estate owner: choose an existing grid account or "+ create new" | src/Setup/NewSimulator.php:688-716 | partly: engine:src/Models/Simulator.php:~306-325; cli:libexec/simulator (--owner) | Quick setup always creates (grid) or names an existing one (join) |
| Notes: remote grid owner must exist; grid with no account yet | src/Setup/NewSimulator.php:691-713 | partly: engine:src/Provisioning/QuickSetup.php:1279; review row 1372 "(an account of the grid)" | – |
| Owner given as password_hash/salt, written directly to DB | src/Setup/SetupRunner.php:127-146 | partly: validated only, engine:src/Provisioning/SetupFile.php:197-199 | Not applied: GridAccounts needs a plaintext password |
| Users list in setup (bulk accounts), passwords generated, result CSV mode 600 | src/Setup/SetupRunner.php:171-173, 204-245 | not ported (engine:src/Provisioning/SetupFile.php:207-210 validates only) | AccountImporter has no caller |

#### Simulator
| Feature | Old | Now | Note |
|---|---|---|---|
| Simulator name; already configured: modify (keep regions) / abandon | src/Setup/NewSimulator.php:595-615 | partly: engine:src/Provisioning/QuickSetup.php:1080, 1088 (adoption "Simulator name"); engine:src/Provisioning/Import/SimulatorImport.php:95 | Not asked in quick (named after the region). No modify |
| Simulator core choice | Simulator::fields via NewSimulator.php:595 | not asked: engine:src/Models/Simulator.php:~252 | – |
| HTTP port | same | engine:src/Provisioning/QuickSetup.php:1116; cli:libexec/simulator (--http-port) | Override |
| Standalone HTTP port must equal login port | – | engine:src/Provisioning/QuickSetup.php:294-296 | New check |
| Public address (external_host) | src/Setup/NewSimulator.php:591-595 | partly: engine:src/Provisioning/SimulatorPlanner.php:332 | Not asked |
| Console mode / console port | same | partly: engine:src/Provisioning/QuickSetup.php:1117 (port only) | – |
| Simulator not started before it has a region | src/Setup/NewSimulator.php:127-132 | partly: cli:libexec/simulator (--region required); QuickSetup always makes a region | – |
| Add simulator to a grid (pool) | src/Setup/Hub.php:325-357; src/Setup/NewSimulator.php:50-71 | cli:libexec/simulator; engine:src/Provisioning/SetupApply.php:50-139 | Plan by default, --apply writes. Not started |
| Reconfigure simulator | src/Setup/Hub.php:392-394 | not ported | – |

#### Region
| Feature | Old | Now | Note |
|---|---|---|---|
| First region: create? / name (default Welcome) | src/Setup/NewSimulator.php:38, 725-741 | engine:src/Provisioning/QuickSetup.php:278, 297-309; engine:src/Models/Region.php:~1729-1745 | Always created. Remote default "<Avatar>'s Place" (QuickSetup.php:1064) |
| Landing region note | src/Setup/NewSimulator.php:37, 565-567, 750-753 | not ported (engine:src/Models/Grid.php:1498 needsLandingRegion unused by setup) | – |
| Region roles | src/Setup/NewSimulator.php:829-831 | engine:src/Models/Region.php:~1747; cli:libexec/region (add/set --roles); rows QuickSetup.php:1502 | – |
| Location x,y; taken / too close → nearest free, "Use x,y?" or type another | src/Setup/NewSimulator.php:767-805 | partly: engine:src/Provisioning/RegionPlanner.php:214-311 | Nearest free place applied without confirmation |
| Grid unreachable warning while placing | src/Setup/NewSimulator.php:771-781 | partly: engine:src/Provisioning/RegionPlanner.php:221-227 | Computed, not surfaced in setup |
| Region size | Region::fields | engine:src/Models/Region.php:~1766; cli:libexec/region (--size) | – |
| Region UDP port | same | engine:src/Provisioning/QuickSetup.php:1124 | Override |
| Add region to grid: region first, then simulator Auto/new or pool | src/Setup/NewSimulator.php:216-300 | partly: cli:libexec/region (add GRID SIMULATOR) | Simulator is named; made when missing |
| Add region to a simulator; refuse if region file exists; load now / start | src/Setup/NewSimulator.php:320-369 | partly: cli:libexec/region; engine:src/Provisioning/SetupApply.php:66, 118-136 | Name/place/port uniqueness checked. Running simulator loads it (SetupApply.php:90) |
| Reuse ExternalHostName of existing regions | src/Setup/NewSimulator.php:391-396 | engine:src/Provisioning/SetupApply.php:274 | – |
| Reconfigure region place/port (keep name/UUID) | src/Setup/NewSimulator.php:406-455 | not ported (engine:src/Models/Region.php:~422-428: file fields read-only) | Only roles/owner/estate via PATCH (cli:libexec/region set) |
| Adopt an existing Region.ini | – | cli:libexec/setup:30-35, 87-92; engine:src/Provisioning/QuickSetup.php:1291-1311 | New |

#### Joining a grid / presets
| Feature | Old | Now | Note |
|---|---|---|---|
| "Which grid does it join?" preset list or "Another grid" | src/Setup/StartSetup.php:133-145; src/Setup/NewSimulator.php:518-531 | engine:src/Provisioning/QuickSetup.php:96-105 | Presets are menu types. Implemented only when present and describable |
| Address of the remote grid (host:port) | src/Setup/StartSetup.php:147-154; src/Setup/NewSimulator.php:533-541 | engine:src/Provisioning/QuickSetup.php:1084-1091, 1244-1253 | "Login URI of the grid" + "Grid private port" |
| Remote fields name/nick/hypergrid (grid tells them) | engine Grid::remoteFields | partly: engine:src/Models/Grid.php:414-457; engine:src/Provisioning/QuickSetup.php:1235-1271 | Not asked: taken from the grid |
| Preset form (grid, login, hypergrid read-only) | src/Setup/NewSimulator.php:532 | engine:src/Provisioning/QuickSetup.php:1068-1083 | – |
| Remote planner warnings/notes/error | src/Setup/NewSimulator.php:543-552 | engine:src/Provisioning/QuickSetup.php:1265-1270, 1338; review text :754 | – |
| Known local grids + "(Robust on another machine)" choice | src/Setup/NewSimulator.php:479-511 | partly: cli:libexec/simulator (GRID argument) | – |
| Preset osgrid (OpenSim.ini, config-include/GridCommon.ini) | share/presets/osgrid/* | engine:share/presets/osgrid/OpenSim.ini, config-include/GridCommon.ini | Identical |
| Preset speculoos (OpenSim.ini) | share/presets/speculoos/OpenSim.ini | engine:share/presets/speculoos/OpenSim.ini | Identical |
| Preset roots: etc/presets then share/presets | presets header :3-6 | engine:src/Provisioning/Presets.php:15, 30 | Joining now completes <etc>/presets/<name> (it was "read, not copied") |

#### Review, apply, after
| Feature | Old | Now | Note |
|---|---|---|---|
| Grid plan text (name, hypergrid, spacing, core, host, ports, console, web URL, helpers, search, currency, OSSL, web server/site, DB, robust ini, dir) | src/Setup/NewGrid.php:326-352 | partly: engine:src/Provisioning/QuickSetup.php:1419-1476 | Currency/OSSL shown as preset labels. Web site not shown |
| Simulator plan text | src/Setup/NewSimulator.php:812-835 | engine:src/Provisioning/QuickSetup.php:1478-1504, 1347-1380 | – |
| Apply this configuration? | src/Setup/NewGrid.php:131-142; src/Setup/NewSimulator.php:156-167 | engine:src/Provisioning/QuickSetup.php:746-761 | Review with Install/Back |
| Enable grid/simulator (link into robust.d/opensim.d)? Start now? | src/Setup/NewGrid.php:143-158; src/Setup/NewSimulator.php:168-183 | partly: engine:src/Provisioning/QuickSetup.php:902, 919-920 | Always enabled and started, no question |
| Warn users of a running simulator before restart | src/Setup/NewSimulator.php:184-191 | partly: cli:libexec/stop (warn unless `now`) | Not in setup |
| Abort if core missing / no framework | src/Setup/NewGrid.php:34-38, 203-207; src/Setup/Hub.php:69 | engine:src/Provisioning/QuickSetup.php:836-841, 847-852 | Also refuses when the system layout is missing |
| Refuse if the grid folder is not writable | – | engine:src/Provisioning/QuickSetup.php:853-863 | New |
| Verify written files exist | – | engine:src/Provisioning/QuickSetup.php:934-949 | New |
| Final message: ready, Login URI; Finish/Continue | src/Setup/Hub.php:232-250 | partly: engine:src/Provisioning/QuickSetup.php:958-974 | Message only, no Continue |
| Record setup.json in grid folder (owner-only) | (doc in setup.yaml:3) | engine:src/Provisioning/SetupFile.php:530; engine:src/Provisioning/GridProvisioner.php:42; engine:src/Provisioning/SimulatorProvisioner.php:57, 121 | – |
| Pending restarts: list, "Restart them now?", warn users, grids first | src/Setup/Hub.php:107-139; src/Setup/Actions.php:117-149 | partly: engine:src/Provisioning/SimulatorProvisioner.php:515 (add only) | Never read/offered. cli:libexec/restart is manual |

#### Hub / management screens
| Feature | Old | Now | Note |
|---|---|---|---|
| Home menu: core, grids (disabled/remote marks), add grid, quit; straight to add when core and no grid | src/Setup/Hub.php:37-85 | partly: engine:src/Provisioning/QuickSetup.php:402-404; cli:libexec/status | No hub. Setup starts at the type menu |
| Escape = Back, nothing written ("Abandoned") | src/Setup/Hub.php:95-104 | engine:src/Provisioning/QuickSetup.php:397-406, 413-415 | Back/Quit buttons, state held by the client |
| Grid screen (regions list, add region, simulators, configure, enable/disable) | src/Setup/Hub.php:253-318 | partly: cli:src/php/commands.php:36-107 (enable/disable instance); cli:libexec/region; cli:libexec/settings | – |
| Simulator screen (regions, add region, configure, toggle) | src/Setup/Hub.php:365-413 | partly: same | – |
| Region enable/disable (+pending restart) | src/Setup/Hub.php:445-461; src/Setup/Actions.php:53-70 | partly: engine:src/Api/Api.php:411-414 (PATCH enabled) | No CLI command |
| Grid/simulator enable/disable actions | src/Setup/Actions.php:35-52 | cli:src/php/commands.php:79-104 | `opensim enable/disable` |
| Core menu: select default profile / install new version | src/Setup/Hub.php:167-185 | not ported | Default profile is set by PackagedProfiles only |

#### Setup files and import
| Feature | Old | Now | Note |
|---|---|---|---|
| `opensim import [GRID] FILE` with auto-detect (robust ini / simulator ini / setup / accounts) | src/Setup/Import/ImportFile.php:30-237 | partly: cli:libexec/import:6-100 | Kind given by flags. One bare FILE = setup file |
| Setup file dry-run ("It would make…"), --apply | src/Setup/Import/ImportFile.php:178-196 | cli:src/lib/common:142-163; engine:src/Api/Api.php:~909-933 | – |
| --grid/--simulator/--users restriction of a setup file | src/Setup/Import/ImportFile.php:58-66, 153-171 | not ported | – |
| Setup file that makes a grid | src/Setup/SetupRunner.php:106-115 | partly: refused, engine:src/Provisioning/SetupApply.php:54-56 | Only through a prepared config (opensim setup --apply) |
| Simulators join the only grid when none named | src/Setup/SetupRunner.php:116-124 | engine:src/Provisioning/SetupApply.php:57-60 | – |
| Import Robust ini as new grid (generate + inject customizations + import-report.txt) | src/Setup/Import/ImportSettings.php:99-166 | not ported (engine:src/Provisioning/Import/RobustImporter.php has no plan() caller) | Replaced in part by --grid-settings into an existing grid (cli:libexec/import:62-77; engine:src/Api/Api.php:~1070) |
| Import simulator ini (regions copied with UUID, customizations kept, report, --name, --core, written disabled) | src/Setup/Import/ImportSettings.php:175-249; src/Setup/Actions.php:73-100 | cli:libexec/import:84-133; engine:src/Provisioning/Import/SimulatorImport.php:68-99, 229, 255, 268, 438 | Adds several simulators, backup, script state, --no-scripts |
| Refuse import over an existing simulator/grid | src/Setup/Import/ImportSettings.php:113-118, 191-193 | engine:src/Provisioning/Import/SimulatorImport.php:95 | Grid case n/a |
| Import accounts CSV/JSON/YAML (--keep-going, --result, --format, skip existing, generated passwords mode 600, exit status) | src/Setup/Import/ImportUsers.php:32-125 | not ported | – |
| ImportFailure with exit status | src/Setup/Import/ImportFailure.php:10-16 | cli:libexec/import:66-68, 98-100, 135 (die / jq status) | – |
| Example setup.yaml | share/examples/setup.yaml:1-37 | partly: format documented in engine:src/Provisioning/SetupFile.php:10-33 | No example file shipped |

#### Core install, releases, runtime
| Feature | Old | Now | Note |
|---|---|---|---|
| Release list from opensimulator.org/dist, 7-day cache, stale fallback, installed flag | src/Setup/Releases.php:11-100 | not ported | Cores are apt packages (cli:kickstart.sh:146-157) |
| Version menu + "Development version (not implemented)" | src/Setup/Installer.php:33-51 | partly: cli:kickstart.sh:155-157 (opensim-unstable) | – |
| Download tarball (reuse previous), extract --no-same-owner --strip-components | src/Setup/Distribution.php:15-45 | not ported | Packages |
| Copy System.Drawing.Common.dll.linux in place | src/Setup/Distribution.php:47-53 | not ported (in these repos) | – |
| OS / package manager detection (apt vs Homebrew) | src/Setup/RuntimeGate.php:116; Installer screen ensure :112 | cli:kickstart.sh:540-548, 748-780 | apt-get required. brew for tools on macOS |
| Install `screen` | src/Setup/Installer.php:112 | cli:kickstart.sh:151, 752, 760 | – |
| Runtime gate: mono <0.9.3.0, .NET 8 ≥0.9.3.0 | src/Setup/RuntimeGate.php:20-32 | engine:src/Provisioning/GridPlan.php:118; engine:src/Runtime/Core.php:124-129 | – |
| .NET present / newer → roll-forward choice / install native | src/Setup/RuntimeGate.php:34-72 | partly: engine:src/Runtime/Core.php:133-135 (--roll-forward Major); engine:src/Runtime/RuntimeProbe.php:39-45 | Automatic, no choice |
| Install .NET runtime | src/Setup/RuntimeGate.php:106-112 | cli:bin/opensim:126-130; cli:src/php/run.php:289-297; engine:src/Runtime/DotnetInstaller.php:51; cli:kickstart.sh:957-1003 | Also libgdiplus (DotnetInstaller.php:112-130) |
| Install Mono (mono / mono-complete), verify on PATH | src/Setup/RuntimeGate.php:74-90, 114-130 | not ported (engine:src/Runtime/RuntimeProbe.php:30-33 message only) | – |
| Runtime check at setup start, offer .NET install when root | src/Setup/RuntimeCheck.php:36-61; src/Setup/Hub.php:40 | partly: engine:src/Runtime/StartService.php:73-84 | Checked at instance start, points to `opensim install-dotnet`. Not at setup |
| Abort setup if runtime missing and declined | src/Setup/RuntimeGate.php:66-71, 84-89 | not ported | – |

#### Layout, profiles, packaging, folders
| Feature | Old | Now | Note |
|---|---|---|---|
| Layout choice system/bundled/flat, base dir, flat next to kit | src/Setup/DirectoryLayout.php:14-38; src/Setup/Installer.php:65-70 | partly: engine:src/Config/DirectoryLayout.php:12-47 | No UI. System layout made by engine:src/Provisioning/SystemLayout.php:54-109 |
| Ask each root (core, etc, var, logs, cache, data, sources) absolute | src/Setup/Installer.php:61-81 | not ported | Fixed system roots (SystemLayout.php:112-120) |
| Create folders etc/opensim.d, robust.d, grids (+ owner) | src/Setup/Installer.php:120-138; etc/{grids,opensim.d,robust.d}/README | engine:src/Provisioning/SystemLayout.php:78-93 | Adds presets and backup. Owned by the system user, dpkg-statoverride |
| System account creation, www-data group | – | engine:src/Provisioning/SystemLayout.php:62-76, 95-99 | New |
| Profile name + "Make default?" | src/Setup/Installer.php:84-96 | not ported | Automatic opensim-<version> |
| Write opensim.conf profile (Defaults mirror) and symlink ~/.config/opensim/opensim.conf | src/Setup/InstallConfig.php:119-141, 245-257 | partly: engine:src/Provisioning/SystemLayout.php:101-106 | No symlink |
| opensim.conf search paths | src/Setup/InstallConfig.php:17-38 | engine:src/Config/Layout.php:22-23 | Kit-local config/opensim.conf dropped. OPENSIM_* env added (Layout.php:59-100) |
| addProfile / removeProfile (default protected) | src/Setup/InstallConfig.php:150-199 | not ported | – |
| PackagedProfiles: profile per release, forget removed, latest default | src/Setup/PackagedProfiles.php:29-82, 110-122 | engine:src/Config/PackagedProfiles.php:16, 27-80, 108-117 | INHERITED adds BackupRoot |
| enableModulesOnce (--default-modules) | src/Setup/PackagedProfiles.php:90-103; libexec/update-profiles.php:23-25 | engine:src/Config/PackagedProfiles.php:88; engine:bin/opensim-profiles.php:37-39 | – |
| libexec/update-profiles.php (--conf, --cores) | libexec/update-profiles.php:18-25 | engine:bin/opensim-profiles.php:32-39; engine:packaging/opensim-engine.postinst:16-17, 23-25 | Run by the engine postinst and the /usr/share/opensim trigger |
| libexec/complete-grids.php (MoneyServer.ini for grids with Helpers.ini) | libexec/complete-grids.php:15-22 | engine:bin/opensim-complete-grids.php:30-33; engine:src/Provisioning/GridCompletion.php:23-48; postinst :19-21 | Also repairs options |
| Git submodule update on a checkout | src/Setup/Installer.php:115-118 | n/a (composer / packages) | – |

### Questions, web wizard, system, installer

#### Terminal prompts (PromptsUi, Prompts/*)

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Terminal frontend on Laravel Prompts (arrow-key select, inline validation) | src/Ui/PromptsUi.php:25-105 | partly ported: opensim-cli:src/lib/ui:103 (ui_menu), :144 (ui_form), :208 (ui_checks), :241 (ui_review), :282 (ui_screen) | Replaced by `dialog`; there is no PHP prompt library any more |
| Spinner while work runs (`spin`) | PromptsUi.php:101-104 | partly ported: opensim-cli:src/lib/ui:359-363 (`wait` text), libexec/setup:101-118 (live `opensim:` lines) | No animation, only a waiting text and progress lines |
| Entity names highlighted (cyan) | PromptsUi.php:37-40 | not ported | Engine `NullUi::entity` returns plain text (opensim-engine:src/Provisioning/NullUi.php) |
| intro / note / warning / error / outro styles | PromptsUi.php:27-55 | partly ported: screen `title`/`text`/`notice` opensim-cli:src/lib/ui:285-286; engine messages on stderr libexec/setup:101-118 | No separate warning/error styles |
| Select prompt with default | PromptsUi.php:57-65 | ported: opensim-cli:src/lib/ui:116 (`--default-item`) | In plain mode the proposed number is always 1, the default is ignored (ui:123) |
| Multi-select (checklist) | PromptsUi.php:67-79 | ported: opensim-cli:src/lib/ui:208-237 | Plain mode asks y/n per item instead of a list of numbers |
| Text prompt with validation | PromptsUi.php:81-84 | partly ported: only as a form field (ui:144); the engine checks the answers (opensim-engine:src/Provisioning/QuickSetup.php:767) | No single-text question kind |
| Password prompt | PromptsUi.php:86-89 | ported: dialog `--insecure` mixedform (ui:156); plain `read -s` (ui:55) | |
| Confirm (yes/no) prompt | PromptsUi.php:96-99; Prompts/ConfirmPrompt.php | not ported | The engine has no confirm screen kind (Endpoints.php:1174: menu, form, checks, review) |
| Escape gives up the screen (Back) | Prompts/Shortcuts.php:35-38; Back.php | partly ported: Back is the Cancel button (ui:93); Escape now means Quit (ui:95-99, status 255 → 2) | The meaning of Escape changed |
| Ctrl-Q quits from anywhere (`-ixon` set) | Shortcuts.php:32, 40-43; Quit.php | not ported | |
| Readline editing keys: Ctrl-U/K/W, Alt-D, Ctrl-Y, Ctrl-D, Ctrl-T, Alt-B/F, Ctrl/Alt-arrows | Shortcuts.php:64-128 | partly ported: plain mode uses `read -e` (ui:58), i.e. real readline | In dialog, only dialog's own editing keys |
| Terminal restored, newline when leaving a prompt | Shortcuts.php:141-147 | n/a | dialog handles the terminal itself |
| Renderer reuse of Laravel themes | Shortcuts.php:131-138 | n/a | |

#### Forms (AsksForms, FormPrompt, FieldForm)

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Several fields on one screen | Prompts/FormPrompt.php:19-51 | ported: opensim-cli:src/lib/ui:144-164 (`--mixedform`) | |
| Tab/Enter/Down next field, Shift-Tab/Up previous, Enter on last field sends | FormPrompt.php:207-232 | partly ported: Enter goes to the next field through a generated dialogrc (ui:79-85); Tab reaches the buttons (ui:156) | Enter on the last field does not send the form |
| Hint line "Tab or Enter: next field..." | FormPromptRenderer.php:41-47 | ported: ui:156 `--hline` | |
| Per-field hint/description under the form | FormPrompt.php:66-69; FormPromptRenderer.php:42 | not ported | Engine form fields have no hint (QuickSetup.php:691-697) |
| Choice, confirm and checklist fields inside a form (Left/Right/Space) | FormPrompt.php:159-205 | not ported | The CLI drops `options` of form fields, e.g. `database_driver` (QuickSetup.php:698-703 vs ui:318) |
| Conditional fields (`when` / `condition`) | FormPrompt.php:100-106; AsksForms.php:65-69 | not ported in the UI | Engine FieldSet keeps `condition`/`follows` (opensim-engine:src/Forms/FieldSet.php:29-31) and exposes them (src/Api/Contract.php:606-612); the CLI does not use them |
| First wrong field takes the focus, error shown under the form | FormPrompt.php:242-263 | partly ported: errors come back as `notice` lines "Error: <label> <msg>" (QuickSetup.php:776-784), shown above the form (ui:286) | No focus on the field |
| Required field check ("%s is required.") | FormPrompt.php:250-251; AsksForms.php:125-129 | ported (engine): QuickSetup.php:767-785 | |
| Secret masked with bullets | FormPrompt.php:152-154 | ported: dialog `--insecure`; plain shows `********` (ui:177) | |
| Read-only fields | — | new: ui:153, 186 | |
| Ask each field, then review "Is this right? Continue / Change something", change one field and keep the others | AsksForms.php:18-63 | ported: plain ui_form_plain (ui:166-195, number to edit, Enter continues); engine review screen (QuickSetup.php:746-761) | |
| Secret left empty on edit keeps the previous one | AsksForms.php:131-141 | not ported in plain mode | Editing a secret and pressing Enter empties it (ui:187-192) |
| Field that becomes visible after an edit is asked | AsksForms.php:56-61 | not ported | No conditions |
| Value shown as option label / Yes-No / joined checklist in the summary | AsksForms.php:72-95 | partly ported: review rows built by the engine (QuickSetup.php:1349-1351) | |
| FieldSet → questions; `follows` fields left as proposed are not sent back | src/Ui/FieldForm.php:27-79 | partly ported: engine side only (FieldSet.php:30, `follows`) | No terminal renderer of FieldSet forms in the CLI |
| Booleans as yes/no, checklists as comma lists | FieldForm.php:99-127 | partly ported: checks to true/false (ui:336) | |

#### Preset answers / quick setup / Back-Quit flow

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Answer the questions from a table (quick setup, setup file) | src/Ui/PresetUi.php:23-141 | ported differently: proposals in QuickSetup.php:437-443, 1049-1104; setup files through SetupFile (`opensim import FILE`, opensim-cli:libexec/import:6) | Answers by field name, not by label |
| Prefill mode: every question asked with the preset as default ("Edit config") | PresetUi.php:18-19, 58-60; EditConfig.php | partly ported: `opensim setup --config FILE` prefills forms and checks (opensim-cli:libexec/setup:78-85, 167-168) | No "Edit config" button after the quick review |
| Labels matched in English or translated (`_()`) | PresetUi.php:249-254 | n/a | |
| Answer refused by validation is asked again with that answer | PresetUi.php:172-176 | ported (engine): checkForm returns the form with corrections (QuickSetup.php:767-785) | |
| Database settings asked again after a failure, on one screen | PresetUi.php:61-66, 109-113, 162-168 | ported: QuickSetup.php:893 → screenDatabase :978-1007 | |
| `answered` list (secrets masked) | PresetUi.php:29-30, 256-261 | partly ported: review rows (QuickSetup.php:756) | |
| Account candidates tried in turn (an existing account, else a new one) | PresetUi.php:128-135 | not verified / not found | |
| Back = give up the screen, back to its menu | Back.php:5-6 | ported: engine `button: back` (QuickSetup.php:397-399, 413-414, 449-450, 464-465, 491-492, 498-502) | Back on the first screen ends the setup ("Cancelled") |
| Quit from anywhere | Quit.php:5-6 | ported: `button: quit` → abort "Cancelled" (QuickSetup.php:405-407) | The CLI then exits with an error status (libexec/setup:186) |
| First screen's back button is "Quit" | (Page.php:116, no Cancel on screen 1) | ported: QuickSetup.php:666 | |
| Prepare without applying / apply prepared | — | new: libexec/setup:42-48, 172-180; QuickSetup.php:358-391, 529-548 | |

#### Quiet and scripted UIs, setup frontend

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| QuietUi: every question gets its default; warnings/errors to stderr | src/Ui/QuietUi.php:13-72 | ported: opensim-cli:src/php/run.php:89-149 (StdUi); opensim-engine:src/Provisioning/NullUi.php:9 | |
| QuietUi verbose mode (notes on stdout only when asked) | QuietUi.php:17-24 | partly ported | StdUi always prints notes (run.php:93-96); NullUi `live` prints to stderr (NullUi.php:15, 31) |
| ScriptUi: line protocol over fifos (hello/intro/note/warn/error/outro/out/busy/ready/bye, ask/answer/back/quit, unit separator, escaping, VERSION 1) | src/Ui/ScriptUi.php:9-57, 59-73, 274-280 | replaced: JSON screens over `engine POST /setups/steps` (libexec/setup:135-189) | Different protocol; stateless `state` string |
| Script asks again until the answer is valid (choose/text/secret) | ScriptUi.php:140-184 | ported (engine re-shows the screen) | |
| Answer to another question id ignored | ScriptUi.php:230-232 | n/a (state string) | |
| Script gone / pipe closed → Quit | ScriptUi.php:266-269, 277-279 | n/a | |
| Idle callback while waiting for an answer (sudo kept alive) | ScriptUi.php:46, 261-264 | not ported | |
| setup-frontend.sh: dialog when there is one, else plain prompts | libexec/setup-frontend.sh:330-338 | ported with changes: opensim-cli:src/lib/ui:27-40 | dialog is now required at a terminal (ui:32, dies); plain below 78 columns (ui:21-22, 36); no `TERM=dumb` check |
| Env OPENSIM_SETUP_UI / FE_TTY / FE_INPUT_FD / FE_SCREEN | setup-frontend.sh:10-19 | ported: ui:4-5, 15-17, 28 | |
| Plain mode `:back`/`:b`, `:quit`/`:q` | setup-frontend.sh:163-170 | ported: ui:69-75 | |
| End of input = quit | setup-frontend.sh:132-145 | ported: ui:51-66 | |
| Plain text with `[default]`, readline `-e -i` prefill | setup-frontend.sh:133-145, 173-179 | ported: ui:52-66 | |
| Plain confirm `(Y/n, :back, :quit)` | setup-frontend.sh:188-205 | not ported | No confirm kind |
| Plain choose by number or by key, default number | setup-frontend.sh:207-233 | partly ported: ui:117-132 | Default always 1 |
| Plain checklist by numbers, `-` for none | setup-frontend.sh:235-264 | partly ported: y/n per item (ui:223-235) | |
| Dialog Back button on every question, Escape = quit | setup-frontend.sh:268-278 | ported: ui:89-100 | |
| Dialog confirm with extra "Back" button and `--defaultno` | setup-frontend.sh:295-306 | not ported | |
| Notes/warnings collected and shown above the next dialog | setup-frontend.sh:83-119, 280-286 | partly ported: screen `text`+`notice` (ui:286) | Engine progress is printed on stderr, not kept for the next box |
| Colours in plain mode (bold title, yellow `!`, red `x`) | setup-frontend.sh:79-113 | not ported | |
| Program output shown indented; busy/ready | setup-frontend.sh:107-111, 390 | partly ported: libexec/setup:101-118 (`opensim:` lines, `~` pending line replaced in place) | |
| `frontend_run`: mkfifo pair, background setup, trap INT/TERM kills it, exit status from `bye` or the process | setup-frontend.sh:358-407 | not ported (replaced) | libexec/setup:96 only cleans temp files; kickstart.sh:483-502 still has `start_setup` sourcing a `setup-frontend.sh` that no longer exists, and nothing calls it |
| Menu sizes computed (dialog height 0 bug), `--no-tags` | — | new: ui:114-116 | |
| Review list that scrolls | — | new: ui:255-260 | |
| DB credentials from OPENSIM_DB_* env prefilled / sent | — | new: ui:305-309, 342-348 | |

#### Program output given to the frontend (Captured, CapturesOutput)

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Frontend that is not the terminal gets the programs' output (unwrapping PresetUi) | src/Ui/Captured.php:12-22; CapturesOutput.php:11-15 | partly ported: engine `ShellService::showOutputWith` collects output into NullUi notes (QuickSetup.php:897-899), shown live on stderr | |
| ANSI stripped, last version of a `\r`-rewritten line | ScriptUi.php:113-124; WebUi.php:69-81 | partly ported: CliReporter `pending` (run.php:72-78), `~` lines (libexec/setup:107-108) | |

#### Web wizard in a browser (WebUi, Mailbox, Page, WebSession, WizardLauncher, WizardServer)

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `opensim setup --web` / `--terminal` / asked at start | install/install.php:49, 56-70 (uses src/Web) | not ported | libexec/setup ignores unknown options (:49). Engine says its scenario is meant for "a terminal, a web page or an app" (QuickSetup.php:349) and has an HTTP server (src/Api/Server.php:19); no web client of `/setups/steps` exists in the w4os-site, grav-plugin-w4os or opensim-helpers branches |
| Mailbox: private 0700 temp folder exchanging screen.json/answer.json/log.jsonl | src/Web/Mailbox.php:13-26 | not ported | Despite the name, it is the browser exchange folder, not mail. Unrelated new mail feature: engine one-time avatar tokens (src/SignIn/AvatarTokens.php:14) |
| Atomic writes (write aside, rename) | Mailbox.php:184-191, 99-111 | not ported | |
| Answer erased once taken (it can hold a password); consumed screen takes no second answer | Mailbox.php:70-90 | not ported | |
| One-time URL token → session secret in a cookie (HttpOnly, SameSite=Strict) | Mailbox.php:132-162; WizardServer.php:59-69 | not ported | |
| Host header check (anti DNS rebinding) | WizardServer.php:11-14, 55-58 | not ported | |
| POST origin check (Sec-Fetch-Site / Origin) | WizardServer.php:101-108 | not ported | |
| Client IP allow-list, IPv4-mapped IPv6 normalised | WizardServer.php:35-40, 52-54 | not ported | |
| Stale or double answer ignored | WizardServer.php:111-119 | not ported | |
| Server waits up to 20 s for the next screen, else a "working" page that refreshes every second | WizardServer.php:114-118; Page.php:214-220, 254 | not ported | |
| Security headers (no-store, X-Frame-Options DENY, Referrer-Policy), favicon 204 | WizardServer.php:125-133, 78 | not ported | |
| Self-contained page (no external resources, dark mode) | Page.php:9-12, 306-348 | not ported | |
| Messages above the screen, earlier messages folded, output tail of 40 lines, progress line | Page.php:24-52, 228-249 | not ported | |
| Continue / Cancel / Quit-with-confirm navigation; menu Back/Quit as buttons | Page.php:96-122 | not ported | |
| Generated secret not sent to the page ("Made for you, leave empty to keep it") | WebUi.php:164-168; Page.php:181-182 | not ported | |
| Required marks, per-field errors, client-side conditions (JS), fields that appear are shown to be filled | Page.php:170-200, 274-304; WebUi.php:157-236 | not ported | |
| "This address cannot be used" page | Page.php:55-63 | not ported | |
| Final "done" page, server stops once it is seen (3 s max) | WebSession.php:32-50; Mailbox.php:164-173 | not ported | |
| `php -S` on a free port, `--port`, `--listen`, `--host`, start check with server log | WizardLauncher.php:20-50, 112-122 | not ported | |
| Browser opened (open/xdg-open), not over SSH | WizardLauncher.php:71-87 | not ported | |
| Server stopped on shutdown and on SIGINT/TERM/HUP | WizardLauncher.php:90-102 | not ported | |
| sudo kept alive every 240 s (`sudo -n -v`) | WizardLauncher.php:58-62, 105-110 | not ported | |
| Timeout of an unanswered screen; server-death detection | WebUi.php:29-35, 294-305; WizardLauncher.php:54-57 | not ported | |

#### System (CommandRunner, CommandSender, Elevated, Packages, SystemUser)

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Start/restart instances through `opensim start/restart` as the system user, output streamed and kept | src/System/CommandRunner.php:14-38; bootstrap.php:23 | replaced: the whole setup runs as the system user (opensim-cli:src/lib/common:87-139) and the engine starts the instances (QuickSetup.php:902, 919) | Engine hook still exists, unused: src/Provisioning/InstanceControl.php:26 |
| Console lines through `opensim command <instance> -` as the system user | CommandSender.php:11-29; bootstrap.php:22 | partly ported: engine sends directly, opensim-engine:src/Console/InstanceConsoleService.php:54 (hook `sendThrough` :26 unused) | The `opensim command` CLI is not ported (opensim-cli:README.md:64) |
| Elevated needed: install has a SystemUser, caller is neither it nor root | Elevated.php:22-30 | ported: common:73-76, 90-92; system user from run.php:205-208 (bin/opensim:107) | |
| Writing only as the system user; questions and database with the caller's rights (~/.my.cnf, root socket) | Elevated.php:9-13, 40-66 | partly ported: everything runs as the system user (common:136); DB credentials pass through PRESERVE_ENV (bin/opensim:45) | The caller's own DB access (~/.my.cnf) is lost |
| Payload with passwords on stdin, not in arguments | Elevated.php:33-36, 55-56 | ported: --apply over stdin (common:96-105); --config/--region-config copied to a 077 temp dir owned by the system user (common:108-138) | |
| Error "Could not run the writing as %s" | Elevated.php:50-54 | partly ported: sudo's own error, then exit (opensim-cli:tests/00-Local/elevation-test.sh:30-35) | |
| Output of the elevated part to the browser/script frontend | Elevated.php:42-62 | n/a | |
| SystemUser: `runuser -u` as root, `sudo -H -u` otherwise, with the caller's terminal | SystemUser.php:32-63 | partly ported: `sudo -u --preserve-env` only (common:79-80) | runuser branch commented out (common:77-78), no `-H` |
| whoami / isRoot fallbacks without posix | SystemUser.php:65-77 | ported: `id -un` (common:75) | |
| No sudo → clear error | — | ported: common:81-82 | |
| Packages abstraction: brew on macOS, apt on Linux | Packages.php:9-25 | partly ported: kickstart.sh:540-549 (apt), 750-755 (brew in clone mode) | Not available inside setup any more |
| `ensure(command, package)`: offer to install a missing tool, judge by the binary appearing | Packages.php:31-49; used src/Setup/Installer.php:112 (screen) | not ported as such | `screen` is installed by kickstart (:151, :760) but is not a dependency of opensim-cli or opensim-engine (packaging/opensim-cli.yaml:21-39); jq: `require jq` (common:22); dialog: dies (ui:32) |
| Package detection | — | new: kickstart.sh:551-559 (DB server), 535-538 (dotnet), 703-706 (bootstrap) | |
| System user created | (package) | engine: opensim-engine:src/Provisioning/SystemLayout.php:67-69 | |

#### Translations (I18n, locales)

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| gettext domain `opensim-kit`, language from LANGUAGE/LC_ALL/LC_MESSAGES/LANG, English fallback | src/I18n/I18n.php:5-26; bootstrap.php:25 | not ported for the CLI/setup | Engine setup strings use `_()`, but no textdomain is bound for the CLI; bash CLI is English only |
| No-gettext fallback | (INSTALLATION.md:212) | ported: opensim-engine:includes/functions-gettext.php:6-46 | |
| `locales/update-pot` (xgettext, msginit for a new language, msgmerge) | locales/update-pot:1-32; locales/README.md | not ported for CLI | Engine has its own for website texts: dev/translations.php, src/I18n/Catalog.php:28, share/languages/*.po (6 languages) |
| .mo compiled at build | packaging/tools (msgfmt) | not ported for CLI | |
| `opensim-kit.pot` (274 strings) | locales/opensim-kit.pot | not ported | |

#### setup.sh (one-command installer) vs kickstart.sh

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| `curl ... \| sudo bash` from the web | setup.sh:4-5 | ported: opensim-cli:kickstart.sh:5-6 | |
| From a clone (`./setup.sh`), detected by `bin/opensim` + `composer.json` | setup.sh:7-8, 59-65 | ported: kickstart.sh:9, 1227-1235 | |
| Packages only (`apt install opensim-kit && opensim setup`) | setup.sh:10 | partly ported: kickstart.sh:13 (`opensim opensim-cli opensim-helpers`) | kickstart names the packages; `opensim-kit` (this repository) installs opensim-cli and w4os-site |
| `-y/--yes` | setup.sh:15, 31 | partly ported: kickstart.sh:1149 | `ask()` (:204) is never called (:767 commented out), so nothing asks before installing; -y only matters for `--cleanup` (:1053) |
| `--no-setup` | setup.sh:16, 32, 80-83 | ported inverted: setup runs only with `--setup` or `RUN_SETUP=yes` (kickstart.sh:17, 161, 1157, 931) | |
| `-h/--help` from the header comment | setup.sh:33-36 | ported: kickstart.sh:1201-1203 | |
| Unknown option → exit 2 | setup.sh:37-40 | ported: kickstart.sh:1208-1210 | |
| `OPENSIM_KIT_DEBS=folder` | setup.sh:17, 158-163 | renamed: `APT_SOURCE_DIR` (kickstart.sh:31, 696-734, 561-585) | Now a local apt repository (dpkg-scanpackages, cache readable by `_apt`) instead of installing *.deb directly |
| Questions read from /dev/tty even when piped; "yes" when no tty | setup.sh:50-57 | defined but unused: kickstart.sh:204-210 | |
| as_root: root, else sudo, else die | setup.sh:67-75 | ported: kickstart.sh:212-220 | |
| Setup gets the keyboard when piped (`exec ... </dev/tty`), dialog frontend sourced | setup.sh:77-96 | partly ported: kickstart.sh:931-932 runs `bin/opensim setup` directly; `start_setup` (:483-502) is dead code | When piped, stdin is not a tty, so plain prompts on /dev/tty (ui:31, 57-58), no dialog |
| apt-get update/install, DEBIAN_FRONTEND=noninteractive | setup.sh:98-103 | ported: kickstart.sh:513-520, 540-549 | Also `dpkg --configure -a` recovery (:504-511) |
| .NET runtime via `opensim install-dotnet -y` | setup.sh:105-108, 147, 175 | ported differently: kickstart.sh:957-1003 (`phase_dotnet`); also `opensim install-dotnet` (bin/opensim:126-130) | |
| Unsupported OS → die | setup.sh:110-113 | ported: kickstart.sh:1242 | |
| macOS clone: Homebrew required; php, composer, screen | setup.sh:120-125 | ported: kickstart.sh:750-755 (adds jq) | No confirmation; README lists "[ ] support Homebrew" (README.md:50) |
| Linux clone: missing php-*/composer/curl/git/unzip/screen/mysql client | setup.sh:126-139 | ported: kickstart.sh:757-769 (adds jq) | Installs without asking |
| PHP ≥ 8.2 check | setup.sh:140 | ported: kickstart.sh:771, 1077 | |
| composer install when vendor/ is missing | setup.sh:142-145 | ported: kickstart.sh:772-775, 1078 | |
| Packages mode Linux/apt only; macOS told to clone | setup.sh:155-156 | ported: kickstart.sh:691-692 | The "clone it" hint is gone |
| Magiiic key + sources list (only if absent), install opensim-kit | setup.sh:21-22, 165-172 | ported: kickstart.sh:113-136, 739-742, 642-687 | Sources now merged/rewritten; distribution `charm` |
| "Ready. Run '...'" | setup.sh:81 | ported: kickstart.sh:926-930 | |
| Debug, --remote, --podman, --cleanup, --mariadb, --config, DB accounts in ~/.opensimrc | — | new: kickstart.sh:1100-1213, 849-889, 790-812 | |

#### Web index and OSSL scripts

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| Default site `index.php` hands to the helpers whatever has no file | share/web/index.php:1-7 | replaced: helpers served as docroot, opensim-engine:share/presets/webroot-helpers/option.conf (`docroot = /usr/share/opensim-helpers/public`); front controller opensim-helpers:public/index.php:1-17 | |
| `OPENSIM_HELPERS_ROOT` override | share/web/index.php:7 | not ported | |
| "Add your own files next to it" | share/web/index.php:3-4 | not ported | docroot is under /usr/share; alternative presets: webroot-website still points to old `opensim-web`; w4os-site |
| OSSL "fix parcel name" sources (7 files) | share/ossl-scripts/fix-parcel-name-src/* | ported, identical blobs: opensim-engine:share/ossl-scripts/fix-parcel-name-src/* | |
| Archive built at package time | (kit packaging/tools:27-43) | partly ported: packed at runtime into a temp file if no .oar ships (opensim-engine:src/Provisioning/SimulatorProvisioner.php:309-335) | |
| Loaded into each new region (`load oar --merge`, owner, displacement), parcel name checked | (INSTALLATION.md:91) | ported: SimulatorProvisioner.php:258-304 | |
| `opensim oar pack/info/check/unpack` | (kit CHANGELOG:130) | partly ported: engine Oar.php:40, 107, 127, 178 | No CLI command (opensim-cli:README.md:128 unchecked) |

#### Features described in README.md / INSTALLATION.md / FAQ.md

| Feature | Old (kit file:line) | Now (repo:file:line) or not ported | Note |
|---|---|---|---|
| opensim-tools replaced on upgrade | README.md:44 | ported: opensim-cli:packaging/opensim-cli.yaml:43-46 | |
| `opensim-kit` package (tools, latest release, modules, DB server, placeholder site) | README.md:33-42; INSTALLATION.md:41 | ported: packaging/opensim-kit.yaml of this repository (opensim-cli, w4os-site, the latest release, its modules, a database server); kickstart.sh:146-154 names the packages one by one | The placeholder site is replaced by w4os-site |
| Wizard levels (core, grids, grid, simulator, region), Back/Quit on every screen | INSTALLATION.md:57 | partly ported: one quick scenario (QuickSetup.php:358-527) | No hub menus |
| Setup in browser, SSH handling, unencrypted warning, `--listen/--allow/--host/--port` | INSTALLATION.md:61-75 | not ported | |
| Quick setup: one form, review of everything chosen, then Continue | INSTALLATION.md:28, 79 | ported: QuickSetup.php:646-761; review button "Install" | |
| "Edit config" | INSTALLATION.md:79 | not ported | `--config` prefill instead |
| Advanced setup (core, console kind, DB, web URL, helpers path, Hypergrid, names) | INSTALLATION.md:81 | partly ported: form has ports and DB driver (QuickSetup.php:1093-1126); options screen (:720) | |
| "Finish setup / Continue setup" with the login URI | INSTALLATION.md:81 | partly ported: final message with Grid, Login URI, Avatar (QuickSetup.php:958-973) | No continue |
| Keys of a terminal | INSTALLATION.md:85 | partly ported | See the prompts section |
| Simulator with its own DB, first region Welcome as default region, started and checked | INSTALLATION.md:89 | ported (engine SimulatorProvisioner) | Not audited in depth |
| Add region through the console without restart | INSTALLATION.md:91 | partly ported: `opensim region add` (opensim-cli:libexec/region:5) | |
| Pending restarts offered when quitting | INSTALLATION.md:91 | partly ported: engine src/Provisioning/PendingRestarts.php:12 | No quit prompt in the CLI |
| snake_case names | INSTALLATION.md:93 | engine src/Provisioning/Slug.php | Not audited |
| Connect to an external grid (get_grid_info, private port, region address) | INSTALLATION.md:97 | ported: QuickSetup.php:89-94, 1084-1090; RemoteGridPlanner.php:146, 203 | |
| Known grid presets (speculoos, osgrid) | INSTALLATION.md:101-105 | partly ported: QuickSetup.php:98-104 (offered only if the preset is found; "soon" in opensim-cli:README.md:145) | |
| Setup file import; setup.json kept in the grid folder | INSTALLATION.md:109 | ported: opensim-cli:libexec/import:6; opensim-engine:src/Provisioning/SetupFile.php:525-532 | |
| Web side: helpers routing, URLs in Robust config | INSTALLATION.md:113-115 | ported: webroot-helpers preset; helpers public/index.php | |
| `opensim web [show/check/snippet]`, files per web server in grids/<grid>/web | INSTALLATION.md:117-123 | ported: libexec/web:4; engine share/presets/webserver-{caddy,nginx,apache}/web/* | |
| `opensim enable/disable <grid> search/economy/profiles` | INSTALLATION.md:127-142; README.md:81-83 | ported: libexec/enable:4, disable:4; src/php/commands.php:51-76 | |
| Firewall `ports --ufw/--publish` | INSTALLATION.md:146-154 | ported: libexec/ports:4 | |
| Import a Robust ini made outside the kit | INSTALLATION.md:160 | partly ported: engine Import/RobustImporter.php exists | CLI only has `--simulator` / `--grid-settings` (libexec/import:6-8) |
| Bulk accounts import (CSV/JSON/YAML, generated passwords in a 600 file) | INSTALLATION.md:162 | partly ported: `users` of a setup file only (SetupFile.php:208; AccountList.php:8) | No standalone accounts import |
| Port blocks, `next port`, `next location` | INSTALLATION.md:168-170 | ported: libexec/next:4 | |
| Console: REST or screen | INSTALLATION.md:172; README.md:85-87 | ported: libexec/console:2; run.php:250-288 | |
| `opensim status/start/stop/restart [--simulators] [now]`, stop countdown | INSTALLATION.md:176-187; README.md:69-79 | ported: libexec/status:4, start:4, stop:4, restart:4 | |
| `opensim command <instance> <text>\|-` | INSTALLATION.md:181 | not ported | opensim-cli:README.md:64 |
| `opensim rest` | INSTALLATION.md:182-185 | ported: libexec/rest:4 | |
| Service starts enabled instances at boot; upgrades never restart them | INSTALLATION.md:189 | ported: packaging/opensim.service; packaging/opensim-cli.postinst:2-17 | |
| backup / save / load iar-oar | INSTALLATION.md:193-204 | ported: libexec/backup:4, save:4, load:4 | |
| `opensim profile add/list/default/remove` | INSTALLATION.md:208 | not ported | opensim-cli:README.md:127; engine bin/opensim-profiles.php is for package scripts only |
| From git: PHP extensions, composer, PATH, bash completion | INSTALLATION.md:212-243 | ported: opensim-cli:README.md:25-33; share/bash-completion/opensim | |
| Database: checks, minimal creation with admin, admin kept in memory only | INSTALLATION.md:45 | partly ported: DatabaseService.php:89; QuickSetup.php:871-893 | kickstart creates `opensim_admin` and writes the credentials to ~/.opensimrc (mode 600) (kickstart.sh:868-888, 790-812) |
| FHS paths, `opensim` account | INSTALLATION.md:47 | ported: engine SystemLayout / Layout | |
| .NET 8 + libgdiplus, launcher warning | INSTALLATION.md:49-53 | ported: engine Runtime/DotnetInstaller.php:51; RuntimeProbe.php:37; kickstart.sh:957 | |
| FAQ: OpenSimulator < 0.9 not supported | FAQ.md:3-5 | not ported (no such statement found) | |

### Packaging, container image, tests

#### Package definition (opensim-tools.yaml)

| Feature | Old | Now | Note |
|---|---|---|---|
| Depends adduser | 146b947:packaging/opensim-tools.yaml:29 | engine:src/Provisioning/SystemLayout.php:67-69 (adduser, else useradd) | Ported (useradd fallback) |
| Depends screen | opensim-tools.yaml:44 | not in cli:packaging/opensim-cli.yaml:21-39 or engine:packaging/opensim-engine.yaml:20-29; only cli:kickstart.sh:151 installs it | Partly: engine:src/Runtime/SystemLauncher.php:38 needs screen, but no package depends on it |
| Depends procps | opensim-tools.yaml:43 | not ported | The engine parses `ps` (engine:tests/Unit/RuntimeTest.php:163), and slim images lack procps |
| Depends sudo, curl, bsdextrautils, mysql client, php-* | opensim-tools.yaml:30-42,45 | cli:packaging/opensim-cli.yaml:22-39 | Ported. Adds bash-tools, dialog, jq, podman-compose |
| Depends engine / helpers / rest-php | opensim-tools.yaml:34-36 | cli:packaging/opensim-cli.yaml:30-31 (engine ≥ beta.13, rest-php); kit:packaging/opensim-kit.yaml:27-31 (cli, engine, w4os-site) | Changed: helpers is no longer a cli dependency |
| Recommends db server, uuid-runtime; suggests opensim | opensim-tools.yaml:47-51 | cli:packaging/opensim-cli.yaml:48-52 | Ported |
| Conflicts/Replaces opensim-tools | — | cli:packaging/opensim-cli.yaml:43-46 | New. The old prerm stops the grids during the switch (documented in kit DEVELOPERS.md) |
| Tree in /usr/share | opensim-tools.yaml:53-56 | cli:packaging/opensim-cli.yaml:55-57 | Ported |
| /usr/bin/opensim symlink | opensim-tools.yaml:57-59 | cli:packaging/opensim-cli.yaml:67-69 | Ported |
| Vendor symlinks to sibling packages | opensim-tools.yaml:60-68 | cli:packaging/opensim-cli.yaml:58-66; engine:packaging/opensim-engine.yaml:35-40; helpers:packaging/opensim-helpers.yaml:41-46 | Partly: cli:packaging/siblings:6 has rest-php commented out, but the yaml links it. bt `stage` (libexec/stage:55-59) will copy it as files at the same path |
| opensim.service file 0644 | opensim-tools.yaml:69-72 | cli:packaging/opensim-cli.yaml:72-73 | Ported (no explicit mode) |
| Bash completion 0644 | opensim-tools.yaml:73-76 | cli:packaging/opensim-cli.yaml:70-71 | Ported |
| /usr/share/doc copyright | opensim-tools.yaml:77-80 | none in the cli, engine, helpers or w4os-site yamls | Not ported |
| README.Debian | opensim-tools.yaml:81-84 | none | Not ported |
| Layout dirs owned by the package (etc, grids, presets, opensim.d, robust.d, var/lib, data, log, cache) | opensim-tools.yaml:85-104 | engine:src/Provisioning/SystemLayout.php:77-90 (mkdir at postinst, plus BackupRoot :80-82) | Partly: dpkg no longer owns or reports them |
| dpkg trigger interest_noawait /usr/share/opensim | opensim-tools.yaml:112-116 | engine:packaging/opensim-engine.yaml:46-50 | Ported |

#### preinst

| Feature | Old | Now | Note |
|---|---|---|---|
| Remove old bundled vendor dirs before unpack (install/upgrade) | 146b947:packaging/opensim-tools.preinst:8-17 | engine:packaging/opensim-engine.preinst:8-17 (rest-php); helpers:packaging/opensim-helpers.preinst:8-17; cli has none | Partly: the engine preinst does not cover its container-tools link (engine yaml:38-40). The cli package never bundled them |

#### postinst

| Feature | Old | Now | Note |
|---|---|---|---|
| opensim system account (group, home /var/lib/opensim, bash) | opensim-tools.postinst:24-26 | engine:packaging/opensim-engine.postinst:12-15 → engine:bin/opensim-layout.php:29-34 → engine:src/Provisioning/SystemLayout.php:62-76 | Ported. Uses SystemUser from the conf; a failure is fatal |
| complete-grids as opensim (missing MoneyServer.ini, random key) | opensim-tools.postinst:29-31 | engine:packaging/opensim-engine.postinst:19-21 → engine:bin/opensim-complete-grids.php:30-33 → engine:src/Provisioning/GridCompletion.php:26-46, MoneyServerConfig.php:77-80 | Ported. Extended with option repair; now also requires the opensim account to exist |
| opensim joins www-data | opensim-tools.postinst:35-36 | engine:src/Provisioning/SystemLayout.php:96-99 (usermod -aG) | Ported |
| chgrp www-data + 0640 on every *.ini of existing grids, at each configure | opensim-tools.postinst:37-40 | not in any postinst. engine:src/Config/GridFileService.php:30-54 runs only at setup/provision/import (GridProvisioner.php:47, SimulatorProvisioner.php:135,445, Import/SimulatorImport.php:273) | Partly: upgrades no longer re-protect grids. MoneyServer.ini from completion is 0640 but keeps group opensim (MoneyServerConfig.php:71), so the web server cannot read it |
| dpkg-statoverride opensim:opensim 755 on layout dirs, admin override kept | opensim-tools.postinst:44-47 | engine:src/Provisioning/SystemLayout.php:92 (chown/chmod :89-90) | Ported. Also covers presets and BackupRoot |
| Default opensim.conf ([Defaults] roots, SystemUser), chown opensim, only if absent | opensim-tools.postinst:50-63 | engine:src/Provisioning/SystemLayout.php:101-106, content :112-125 | Ported. Adds BackupRoot |
| Install profiles on configure (non-fatal) | opensim-tools.postinst:16-19,64 | engine:packaging/opensim-engine.postinst:16-17 → engine:bin/opensim-profiles.php:32-36 → engine:src/Config/PackagedProfiles.php:27-80 | Ported |
| `triggered`: update profiles | opensim-tools.postinst:66-68 | engine:packaging/opensim-engine.postinst:23-24 | Ported |
| Kit enables opensimsearch + gloebit once | 146b947:packaging/opensim-kit.postinst | kit:packaging/opensim-kit.postinst:10-13 → engine:bin/opensim-profiles.php:37-39, PackagedProfiles.php:88-101 | Ported |
| unmask; enable if was-enabled, else update-state (configure/abort-*) | opensim-tools.postinst:71-80 | cli:packaging/opensim-cli.postinst:6-14 | Ported verbatim |
| daemon-reload | opensim-tools.postinst:81-83 | cli:packaging/opensim-cli.postinst:15-17 | Ported |
| Service never restarted by postinst | opensim-tools.postinst:4-6 | cli:packaging/opensim-cli.postinst:2-3 | Ported |
| Backup timers enabled | — | cli:packaging/opensim-backup*.{service,timer}, cli yaml:74-81 | New. The postinst does not enable them |

#### prerm / postrm

| Feature | Old | Now | Note |
|---|---|---|---|
| On remove: deb-systemd-invoke stop opensim.service | opensim-tools.prerm:6-9 | cli:packaging/opensim-cli.prerm:6-9 | Ported |
| On remove: `opensim stop now` (instances started by hand) | opensim-tools.prerm:11 | cli:packaging/opensim-cli.prerm:11 | Ported |
| Upgrades leave instances running | opensim-tools.prerm:2-3 | cli:packaging/opensim-cli.prerm:2-3 | Ported |
| daemon-reload | opensim-tools.postrm:7-9 | cli:packaging/opensim-cli.postrm:5-7 | Ported |
| remove → mask | opensim-tools.postrm:13-15 | cli:packaging/opensim-cli.postrm:11-13 | Ported |
| purge → purge + unmask | opensim-tools.postrm:16-19 | cli:packaging/opensim-cli.postrm:14-17 | Ported |
| purge: rm -rf /var/cache/opensim | opensim-tools.postrm:23-24 | none (engine has no postrm) | Not ported |
| purge: dpkg-statoverride --remove on layout dirs | opensim-tools.postrm:27-32 | none | Not ported. Overrides from SystemLayout.php:92 stay forever |
| purge keeps config, data, logs, account | opensim-tools.postrm:2-4 | nothing removed; w4os-site:packaging/w4os-site.postrm:5-9 keeps the site | Ported by omission |

#### systemd unit (opensim.service)

| Feature | Old | Now | Note |
|---|---|---|---|
| Description / Documentation | 146b947:packaging/opensim.service:2-3 | cli:packaging/opensim.service:2-3 | Ported (new URL) |
| After network-online, mariadb, mysql; Wants network-online | opensim.service:4-5 | cli:packaging/opensim.service:4-5 | Ported |
| Type=oneshot, RemainAfterExit=yes | opensim.service:9-10 | cli:packaging/opensim.service:10-11 | Ported |
| User/Group opensim | opensim.service:11-12 | cli:packaging/opensim.service:12-13 | Ported. Hard-coded in both; a custom SystemUser is ignored |
| WorkingDirectory=~ | opensim.service:13 | cli:packaging/opensim.service:14 | Ported |
| ExecStart start / ExecStop stop now | opensim.service:14-15 | cli:packaging/opensim.service:15-16 | Ported |
| TimeoutStopSec=15min | opensim.service:17 | cli:packaging/opensim.service:18 | Ported |
| WantedBy=multi-user.target | opensim.service:20 | cli:packaging/opensim.service:21 | Ported |
| Limits, KillMode, Restart | absent | absent | n/a in both |

#### opensim-web / README.Debian

| Feature | Old | Now | Note |
|---|---|---|---|
| Default site /var/www/html, index.php hands unknown URLs to the helpers | 146b947:packaging/opensim-web.yaml:25-28 | w4os-site:packaging/w4os-site.yaml:38-50, postinst:12 (Grav site in /var/lib/opensim/www) | Replaced by a different site, not the same files |
| Depends helpers; suggests web server, php-fpm | opensim-web.yaml:19-23 | w4os-site:packaging/w4os-site.yaml:23-36; helpers:packaging/opensim-helpers.yaml:32-35 | Partly: w4os-site does not depend on opensim-helpers |
| Doc: helpers location; settings read from grid ini; OPENSIM_GRID per vhost | opensim-tools.README.Debian:4-23 | engine:src/Provisioning/Web/HelpersConfig.php:67-72 | Behaviour ported, doc not |
| Doc: `opensim web` / snippets caddy, nginx, apache | opensim-tools.README.Debian:25-31 | cli:libexec/web (USAGE); engine:src/Provisioning/Web/Snippets.php | Behaviour ported, doc not |
| Doc: ini files 640 for www-data | opensim-tools.README.Debian:33-34 | engine:src/Config/GridFileService.php:9-13 (docblock) | Doc not ported |

#### Kit build scripts (HEAD)

| Feature | Old | Now | Note |
|---|---|---|---|
| build-tools stage deb/zip | kit:packaging/tools:15 | cli:packaging/build:8; engine:packaging/build:8 | Ported |
| Siblings left out of the deb vendor | kit:packaging/siblings:3-5 | cli:packaging/siblings:3-7; engine:packaging/siblings:3-4; helpers:packaging/siblings:3-4 (bt libexec/stage:55-59) | Ported. See the rest-php issue above |
| msgfmt .po → .mo | kit:packaging/tools:17-25 | engine reads .po at runtime (engine:src/I18n/Catalog.php:1032); helpers commits .mo; cli has no locales | Changed |
| Build the OSSL archives (fix-parcel-name.oar), siblings linked temporarily | kit:packaging/tools:27-44 | engine:src/Provisioning/SimulatorProvisioner.php:309-335 packs it at runtime in tmp | Partly: no pre-built .oar is shipped |
| chmod -R go-w on the build tree | kit:packaging/tools:46-47 | bt:libexec/stage:81 | Ported in build-tools |
| Zip of the tools | kit:packaging/build-zip:8-13 | cli dev/build.sh (cli:tests/10-Build/build-test.sh:17) | Ported per project. kit build-zip still zips the old tools tree |
| remote-build: ssh BatchMode, tool checks, per-commit cache, SDK container + NuGet volume, rsync of the distribution | kit:packaging/remote-build:30-77 | kit-only, used by kit:packaging/build:156-160 | Not part of the new set (stays in kit) |

#### Container image (kit:packaging/container) vs cli share/podman and kickstart --podman

| Feature | Old | Now | Note |
|---|---|---|---|
| Ubuntu base, BASE override | kit:packaging/container/Containerfile:6-7; build:15 | cli:share/podman/debian/Containerfile:6-8; cli:share/podman/images:1-5; cli:libexec/podman-build:72 | Ported, more distros |
| Acquire::Retries 5 | Containerfile:11 | cli:share/podman/debian/Containerfile:25 | Ported |
| Fixed UID/GID 10001 opensim, no home created | Containerfile:13-16 | cli:share/podman/debian/Containerfile:10-13,29-32 (8002 by ARG, creates home); cli:libexec/podman-build:51-58 | Partly: different id |
| Admin user with NOPASSWD sudo | — | cli:share/podman/debian/Containerfile:15-18,34-37,72 | New. Less locked down |
| No database server in the image | Containerfile:18-21 | cli:share/podman/debian/Containerfile:39-42 | Partly: kickstart --podman installs mariadb-server in the container (cli:kickstart.sh:149) |
| Magiiic repo with signed key | Containerfile:22-26 | cli:share/podman/debian/Containerfile:44-53; cli:share/podman/debian/magiiic.list:1-2 | Ported. Adds a `trusted=yes` charm-tests line |
| .NET runtime | Containerfile:23 | cli:share/podman/debian/Containerfile:57 (`opensim install-dotnet`) | Ported differently |
| Local pkgs/*.deb from dist and sibling dist dirs | Containerfile:28-32; build:28-51 | cli:kickstart.sh:376-381 (APT_SOURCE_DIR) | Partly |
| Enable opensimsearch + gloebit, chown conf | Containerfile:30-31 | none (kickstart installs the modules, cli:kickstart.sh:148, but not opensim-kit) | Not ported |
| Entrypoint: start enabled instances, wait when none, TERM/INT → `opensim stop now` | kit:packaging/container/opensim-container:11-19 | ENTRYPOINT commented (cli:share/podman/debian/Containerfile:83); kickstart uses a systemd image (cli:kickstart.sh:271-272) | Partly: only via systemd |
| Instance logs on the container stdout, `[name]` prefix, Stats skipped, new logs followed | opensim-container:21-37 | none | Not ported |
| SCREENDIR=/var/cache/opensim/screen (mode 700) | Containerfile:36-38; opensim-container:9 | none | Not ported |
| USER opensim, WORKDIR /var/lib/opensim | Containerfile:40-41 | cli:share/podman/debian/Containerfile:78-79 | Ported |
| VOLUME etc / lib / log | Containerfile:43-44 | commented (cli:share/podman/debian/Containerfile:67); empty mount list (cli:libexec/podman-run:63-68) | Not ported |
| STOPSIGNAL SIGTERM | Containerfile:46 | cli:share/podman/debian/Containerfile:81 | Ported, but no entrypoint handles it |
| ENGINE=docker | build:9,14 | podman only (cli:libexec/podman-build:92) | Not ported |
| Build memory cap 700m; arch amd64/arm64; tag opensim-kit:dev | build:16,21-25,54 | tag opensim-<key> (cli:libexec/podman-build:77) | Partly: no memory cap, no arch handling |
| `:z` SELinux volume labels | kit:packaging/container/README.md:24-30 | none | Not ported |
| `opensim ports --publish` for -p | README.md:32-39 | cli:libexec/podman-run:57-58; cli:kickstart.sh:352-358 | Ported |
| Locked-down run (read-only, tmpfs, cap-drop ALL, no-new-privileges, pids-limit, memory) | README.md:67-78 | none | Not ported |
| --stop-timeout 120 | opensim-container:7; README.md:24 | none | Not ported |
| Rootless userns keep-id | — | cli:libexec/podman-run:97-101 | New |

#### Tests: drivers and infrastructure (kit:tests/Packaging)

| Feature | Old | Now | Note |
|---|---|---|---|
| `run`: systemd container (bookworm), memory cap, low-memory warning, tar stream, CONTAINER_CONNECTION, PREPARE image cache, KEEP, SCENARIO, STOP_AFTER | kit:tests/Packaging/run:1-107; scenario-image.Containerfile:1-8 | none (nearest: systemd image builder in cli:kickstart.sh:263-311, not a test) | Not ported |
| `check`: links to siblings, own vendor libs, command runs, rest client, OSSL archives, no tests/packaging/lock, upgrade from bundled leaves links, zip complete | kit:tests/Packaging/check:71-87 | engine:tests/Packaging/check:55-68; helpers:tests/Packaging/check:74-125; cli:tests/10-Build/build-test.sh:9-26 (build only) | Partly: the opensim-cli deb is never installed in a test |
| PackagingTest.php: Debian 12 + Ubuntu 24.04, distro-pest, web, container-image | kit:tests/Packaging/PackagingTest.php:48-84 | engine:tests/Packaging/PackagingTest.php:35-51 (Debian 12); helpers:tests/Packaging/PackagingTest.php | Partly |
| distro-pest: every suite with distro PHP and only the packaged extensions | kit:tests/Packaging/distro-pest:1-64; pest-image.Containerfile:1-6 | engine:tests/Environment/PhpCompatibilityTest.php; helpers:tests/Environment/PhpCompatibilityTest.php | Partly: static version check only |
| ScriptedUi, newgrid/newsimulator/account/standalone drivers | kit:tests/Packaging/ScriptedUi.php, *-driver.php | cli:tests/00-Local/setup-test.sh:108-556; ui-test.sh:43-67 (against a fake engine) | Partly. Kit drivers still `require /usr/share/opensim-tools/bootstrap.php` |
| Search fakes/fixtures (simulator-fake, collector-*.xml, tables SQL) | kit:tests/Packaging/search/* | engine:tests/Unit/SearchTest.php:259 (parser) | Partly |
| search-module-contract | kit:tests/Packaging/search/search-module-contract.php | kit:tests/Unit/SearchModuleContractTest.php only | Not in the new projects |
| Economy fakes (region-fake, site-fake, settings-fixture) | kit:tests/Packaging/economy/* | engine:tests/Unit/CurrencyTest.php:101-302 | Partly (unit) |
| mail-fake, xml-probe, hypevents-fake, image-probe | kit:tests/Packaging/offline/*, pages/* | engine:tests/Unit/OfflineMessagesTest.php:69; SearchTest.php:443; TextRendererTest.php:12 | Partly (unit) |
| dotnet-fake, status-probe | kit:tests/Packaging/lifecycle/* | engine:tests/Unit/LifecycleTest.php (fakes) | Partly |

#### Tests: package-scenario.sh (systemd lifecycle)

| Feature | Old | Now | Note |
|---|---|---|---|
| Core alone: installed, no tools, no /etc/opensim | kit:tests/Packaging/package-scenario.sh:25-28 | none | Not ported |
| Tools install: account, profile, DefaultProfile, service enabled | package-scenario.sh:30-35 | engine:tests/Unit/SystemLayoutTest.php:34-91 (unit with fakes); profiles only in kit:tests/Unit/PackagedProfilesTest.php:105 (targets kit libexec/update-profiles.php) | Partly: no engine test of PackagedProfiles, no systemd check |
| Metapackages install | package-scenario.sh:36-37 | none | Not ported |
| install-dotnet usable by opensim | package-scenario.sh:39-41 | engine:tests/Unit/DotnetInstallerTest.php:131-324; cli:tests/00-Local/quick-install-test.sh:228,246,301 | Partly (unit) |
| Helpers installed without own config; default site present | package-scenario.sh:45-48 | helpers:tests/Packaging/check:74-77; w4os-site:tests/tree-test.sh:33 | Partly |
| Grid from wizard: robust.d link, MoneyServer.ini key 640 opensim:www-data, Helpers.ini extras only, every ini 640 www-data, Robust service URLs, one web snippet | package-scenario.sh:50-66 | engine:tests/Unit/EnablableTest.php; WebTest.php:56,146; GridOptionsTest.php:326 | Partly: no test of 640/www-data on a made grid |
| Helpers read settings as www-data; `opensim web` + snippets; default site, /welcome, /helpers/motd.php | package-scenario.sh:67-80 | helpers:tests/Packaging/check:88-101 (chgrps by hand at :88); cli:tests/00-Local/cli-test.sh:132,137; helpers:tests/Feature/AppTest.php:28 | Partly |
| No CRLF in grid files | package-scenario.sh:81 | engine:tests/Unit/TextFileServiceTest.php:22 | Ported (unit) |
| DB checks: wrong password writes nothing; missing DB prints commands; password kept in session; root creates only what is missing; files owned by opensim; account+DB created; failed creation ends the setup | package-scenario.sh:86-127 | engine:tests/Unit/SetupApplyTest.php:122,136; DatabasePasswordsTest.php:8-26; SqlRunnerTest.php | Partly (unit, no MariaDB) |
| Own DB admin, no sudo to root; outdated ~/.my.cnf; admin asked, never shown | package-scenario.sh:129-166 | cli:tests/00-Local/setup-test.sh:353-407; elevation-test.sh:23-38; engine:tests/Unit/SetupApplyTest.php:122 | Partly |
| Setup as root starts the grid; unknown instance fails; no-HG Robust ready; failed services named | package-scenario.sh:168-199 | engine:tests/Unit/LifecycleTest.php:29,55,426 | Ported (unit) |
| `systemctl start opensim`: Robust runs on the packaged core, ready, nothing denied | package-scenario.sh:202-209 | none | Not ported |
| Simulator joins: online, registered, OfflineMessageURL/DATA_SRV, -inimaster, bakes/groups/estates, default region flags, owner home, own DB, password hash with special characters, modules loaded, core untouched, native libs | package-scenario.sh:214-261 | engine:tests/Unit/ConfigureKeepsTest.php:228; DefaultRegionTest.php:89-113; AccountsTest.php:96,116; RuntimeTest.php:79-117; CoreTest.php:87 | Partly (unit, no real start) |
| Region added to a running simulator, parcel named | package-scenario.sh:264-271 | engine:tests/Unit/SetupApplyTest.php:36; cli:tests/00-Local/import-test.sh:178 | Partly |
| Account via Robust console; CSV import (dry-run, apply, inventory, hash, home, 600 result, existing skipped) | package-scenario.sh:272-291 | engine:tests/Unit/AccountsTest.php:30-252 | Partly: no CLI command for CSV import found (cli `opensim import` takes setup files) |
| Region moved in place (UUID kept); next location | package-scenario.sh:313-322 | engine:tests/Unit/RegionChangeTest.php; cli:tests/00-Local/cli-test.sh:123 | Partly |
| save/load oar, backup archive, next port, remote region ports skipped | package-scenario.sh:323-337 | engine:tests/Unit/BackupRestoreTest.php:180; OarTest.php; cli:tests/00-Local/cli-test.sh:115,190-202 | Partly |
| enable/disable search, economy, gloebit, profiles; URL prefix; idempotent; unknown grid | package-scenario.sh:340-376 | engine:tests/Unit/GridOptionsTest.php:123,217; cli:tests/00-Local/cli-test.sh:268 | Partly |
| Ports in blocks of ten, UDP regions, private port on 127.0.0.1 | package-scenario.sh:377-384 | engine:tests/Unit/PortsTest.php:61,80; cli:tests/00-Local/ports-test.sh:43,56 | Ported |
| Command to a console, fails when not running, attach leaves instance running | package-scenario.sh:385-389 | cli:tests/00-Local/cli-test.sh:93,210 | Partly |
| Stopping a grid leaves a simulator with the same name prefix alone | package-scenario.sh:391-395 | engine:tests/Unit/LifecycleTest.php:361 | Partly: the screen-prefix case is not tested |
| Upgrade does not restart Robust or the simulator | package-scenario.sh:399-403 | none | Not ported |
| Unstable build profile, modules in their own folders, grid moved to another core, services counted | package-scenario.sh:405-426 | engine:tests/Unit/CoreTest.php:23; LifecycleTest.php:29 | Partly |
| Removing a build stops only its instances | package-scenario.sh:428-447 | none | Not ported |
| REST-console simulator: port x4, generated credentials, no screen, wrong password refused, stop/start through the port | package-scenario.sh:449-471 | engine:tests/Unit/LifecycleTest.php:123; SimulatorPlannerTest.php:57; cli:tests/00-Local/cli-test.sh:210-233 | Partly |
| Simulator of a grid elsewhere: Remote conf, SYSTEMIP, placed next to the others | package-scenario.sh:473-495 | engine:tests/Unit/RemoteGridPlannerTest.php:39 | Partly |
| Modules linked in addins, core untouched, unlinked when disabled; libgdiplus refusal; core depends on libgdiplus | package-scenario.sh:497-526 | engine:tests/Unit/RuntimeTest.php:104-146; DotnetInstallerTest.php:308-324 | Partly |
| Remove tools with the grid started by the service: clean Quitting, simulator stops, core kept | package-scenario.sh:548-559 | none | Not ported |
| Reinstall adds the missing MoneyServer.ini (640 opensim:www-data); service enabled again | package-scenario.sh:561-568 | engine:tests/Unit/GridCompletionTest.php:17-34 (options only) | Not ported. The group would now fail (see postinst) |
| Remove tools with the grid started by hand: clean stop | package-scenario.sh:570-576 | none | Not ported |
| Remove core: profile removed, custom-core instances untouched, modules kept, default falls back, tools work without a core | package-scenario.sh:578-593 | kit:tests/Unit/PackagedProfilesTest.php only | Not in the engine |
| Purge: cache removed; config, data, logs kept | package-scenario.sh:595-598 | none | Not ported |

#### Tests: the other scenarios

| Feature | Old | Now | Note |
|---|---|---|---|
| lifecycle (fake dotnet): status, start/restart/--simulators/stop now, idempotent, unknown fails, service starts and stops all | kit:tests/Packaging/lifecycle-scenario.sh:63-99 | engine:tests/Unit/LifecycleTest.php:29-478; InstanceControlApiTest.php:51-67; cli:tests/00-Local/cli-test.sh:89 | Partly: no real systemd, screen or runuser |
| cli-check/cli-scenario (real core): grid from a setup file answers, DB made, owner signs in, status/stop/start/restart, monitor, standalone | kit:tests/Packaging/cli-scenario.sh:36-117 | cli:tests/00-Local/import-test.sh:163; quick-install-test.sh:106; engine:tests/Unit/MonitorTest.php | Partly: no real core |
| configure: operator settings kept, `~` backup, web search copied to both settings, MoneyServer key kept, 640 www-data kept | kit:tests/Packaging/configure-scenario.sh:23-30 | engine:tests/Unit/ConfigureKeepsTest.php:151 | Partly: mode and group not checked |
| setup.sh on a bare machine: packages, dotnet, libgdiplus, profile + modules, idempotent, bad option exits 2, missing folder | kit:tests/Packaging/setup-scenario.sh:16-35 | cli:tests/00-Local/quick-install-test.sh:63-338 | Partly: no real machine. "No bash-tools" is reversed (cli yaml:23) |
| setupfile: grid and simulator from a file | kit:tests/Packaging/setupfile-scenario.sh:13-58 | engine:tests/Unit/SetupApplyTest.php:36-136; SetupFileTest.php; cli:tests/00-Local/import-test.sh:163,203 | Partly |
| simulator-name: name proposed from the region | kit:tests/Packaging/simulator-name-scenario.sh:10-14 | engine:tests/Unit/SimulatorNameTest.php:15 | Ported (unit) |
| no-region: not started, launcher refuses, starts once a region is added | kit:tests/Packaging/no-region-scenario.sh:13-22 | engine:tests/Unit/LifecycleTest.php:165 | Ported (unit) |
| person: cannot read the 640 files, sudo to opensim adds a region in the right port block, no sudo stops at once | kit:tests/Packaging/person-scenario.sh:11-25 | cli:tests/00-Local/elevation-test.sh:23-38; engine:tests/Unit/GridFilesTest.php:43; PortsTest.php:72 | Ported (unit-ish) |
| ban: account and HG visitor found, statements written, nothing changed until run, then applied, idempotent | kit:tests/Packaging/ban-scenario.sh:17-47 | engine:tests/Unit/AvatarBanTest.php:74-163; cli:tests/00-Local/cli-test.sh:181-185 | Partly: statements never executed |
| search: register, snapshot parse, tables, popular places, retry/backoff, forget after 10 failures, unregister, real simulator, people, profile box | kit:tests/Packaging/search-scenario.sh:39-174 | engine:tests/Unit/SearchTest.php:132-762; helpers:tests/Packaging/check:99-100 | Partly (no real simulator) |
| offline: save/retrieve, markup kept, once only, mails by kind/opt-out, HG gatekeeper message | kit:tests/Packaging/offline-scenario.sh:50-142 | engine:tests/Unit/OfflineMessagesTest.php:69-207 | Partly: the HG gatekeeper path has no test |
| economy: grid key from MoneyServer.ini, helpers alone, money server, unreachable region, Gloebit, Podex | kit:tests/Packaging/economy-scenario.sh:26-116 | engine:tests/Unit/CurrencyTest.php:101-302; EconomyModuleTest.php:21-67 | Partly (unit) |
| pages: motd, layout/blocks, viewer vs browser, theme, guide, textgen, events, routes, health, refusal codes, web search, profiles, directory | kit:tests/Packaging/pages-scenario.sh:29-334 | engine:tests/Unit/MotdTextTest.php:16; DestinationListTest.php; TextRendererTest.php; SearchTest.php:443; HealthTest.php; helpers:tests/Feature/AppTest.php, BaseTest.php:39-134, Unit/*Block*; w4os-site:tests/helpers-routes-test.sh, health-test.sh, standalone-test.sh | Partly: no directory-page test found |
| web: nginx, Apache, Caddy accept the snippet with FPM/mod_php; home, helper, search DB, register, offline, router 404 | kit:tests/Packaging/web-scenario.sh:9-73 | helpers:tests/Packaging/check:88-101 (Caddy + FPM only); engine:tests/Unit/WebTest.php:146-177; w4os-site:tests/w4os-site-test.sh:99-122 | Partly: nginx and Apache never tried for real |
| wizardweb / wizardweb-grid: browser setup, one-time cookie, host check, SSH opens only to the client address, few pages, grid made | kit:tests/Packaging/wizardweb-scenario.sh, wizardweb-grid-scenario.sh | none (cli has no web setup) | Not ported |
| wizardscript-grid: questions one at a time, password asked once, output reaches the script, no pipes left, grid running | kit:tests/Packaging/wizardscript-grid-scenario.sh:57-76 | cli:tests/00-Local/setup-test.sh:108-556; ui-test.sh | Partly (fake engine) |
| standalone: no Robust, master, storage, owner signs in, second simulator refused, monitor, stop/start, welcome, helpers + HG + offline V2 | kit:tests/Packaging/standalone-scenario.sh:20-106 | engine:tests/Unit/StandaloneProvisioningTest.php:79-608; StandaloneTest.php:37; w4os-site:tests/standalone-test.sh:52-70 | Partly (no real core) |
| container-image: stays up empty, UID 10001, no DB server, grid + sim from inside, logs on console, console reachable, clean stop under 100 s, instances back after restart, locked-down run, nothing writable | kit:tests/Packaging/container-image:83-120 | engine:tests/Environment/PodmanIntegrationTest.php (create/start only); cli:tests/00-Local/podman-setup-test.sh:26 | Not ported |
