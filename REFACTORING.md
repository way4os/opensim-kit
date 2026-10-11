# Refactoring plan

Reference for where code and files go, and in what order it moves there. Draft 2, written after the freeze of
v3.0.0-beta.6 (engine, helpers, kit). New code is written directly at its final place, existing code moves one step at a
time (see [Order](#order), with where each step stands), each step leaving everything working and tested.

## Goal

One application, several front-ends that call the same code:

- the CLI of the kit (`opensim ...`), which only gives a direct access to features of the application;
- the web administration (not written yet);
- the WordPress plugin w4os, which already uses the engine and the helpers;
- the helpers, the web services the viewers and the simulators call.

`opensim start` is a shortcut: the command finds what it is asked to start and calls the engine, which starts it. The
administration and w4os call the same method, with the same result:

```php
OpenSim::start('speculoos');                       // the services of the grid, its Robust
OpenSim::start('speculoos', simulators: true);     // and the simulators that depend on it
OpenSim::start('speculoos_welcome');               // one simulator
// which call
$grid->start();                                    // OpenSim\Models\Grid
$simulator->start();                               // OpenSim\Models\Simulator
```

A grid is its services: restarting Robust does not need its simulators to restart, so they join only when asked
(`--sims` on the command line).

Where it stood before: the logic of start, stop, status, console and port mapping was in bash (`bin/opensim`, 929 lines,
`libexec/os-helpers`, 1017 lines); the PHP of the kit was a set of independent scripts over installer classes that lived in a
namespace of the engine (`OpenSim\Installer`). This plan is about not having to write it all again for the administration
and w4os.

## Principles

- Dependencies only go one way: `opensim-rest-php` ← `opensim-engine` ← `opensim-helpers` ← `opensim-kit`. `OpenSimKit`
  never appears in the other projects. The engine and the helpers are also used by w4os and 2do.directory: what they have
  stays compatible (see [Legacy names](#legacy-names)).
- The engine owns the model of OpenSimulator and what is done to it: read and write its config, start and stop its
  instances, their ports and their port mapping, their consoles, their archives, their health. Whatever a front-end can
  ask is a method of the engine.
- The kit is the application: the CLI, the interactive setup (the screens, not what they decide), the packaging, what is
  specific to a Debian system (system user, packages, systemd). It holds no logic a front-end other than the CLI would
  need.
- A command of the CLI parses its arguments, calls the engine, prints the result. No logic in a command.
- Bash only where it has to be: the maintainer scripts of the packages, the systemd unit, the bootstrap of a machine that
  has no PHP yet. `bin/opensim` is the PHP entry point of the CLI.
- Namespaces: `OpenSim\` is the engine (the core: grids, simulators, regions, avatars, services, settings, runtime).
  `OpenSimKit\` is the kit, `OpenSimKit\Console` its CLI, `OpenSimKit\Setup` only the screens of the wizard. `OpenSim\`
  never holds code of the kit (today `OpenSim\Installer` does: it moves).
- Code of the kit and of the engine follows PSR-12 and PSR-4 (composer autoload, one class per file). The helpers keep
  their tolerance for their community. All the projects require PHP 8.2.
- Config files: the `.ini` files hold only what OpenSim reads, `Helpers.ini` what the helpers add, `<grid>/<grid>.conf`
  what is specific to the kit (core, version, preset, spacing, center, port mapping). All of them are read with the
  settings classes of the engine, once, resolved (includes, constants), kept in one array: no `crudget`, no `parse_ini_file`
  in new code.
- Files generated while running (state, caches, pids, port mapping state) go in the var root of the install, never in the
  config.

## Target layout

### opensim-engine (`OpenSim\`, PSR-4 in `src/`)

| Namespace | What | From |
|---|---|---|
| `OpenSim\OpenSim` | The entry point of the front-ends: `start()`, `stop()`, `restart()`, `status()` of a target (all, a grid, a simulator), resolved to models | new; the static helpers of `class-opensim.php` (grid_info, sanitize_uri...) |
| `OpenSim\Models\` | `Grid`, `Simulator`, `Region`, `Avatar`, `Robust` (a server of a grid), `Estate`... with the find/save methods of a model (see Class names) | `class-grid.php`, `class-region.php`, `class-avatar.php`, `class-robust.php`; the state of the kit (`GridState`, `SimState`, `RegionState`, `GridInfo`) |
| `OpenSim\Config\` | Reading (the tree of `OpenSim_Config`, includes and constants), writing (the line editor that keeps comments, `layered()`, `overlay()`), the merge of a folder of grid or of preset, the layout of an install (where etc, var, cores are) | `class-config.php`, `class-ini.php`, `class-engine-settings.php`; kit `Ini`, `TextFile`, `GridShared`, `Import\IniReader`, `Layout`, `Config` (opensim.conf) |
| `OpenSim\Provisioning\` | What makes or changes a grid, a simulator, a region: their config, their files, their database, a preset, a feature (search, economy, profiles), accounts | kit `NewGrid`/`NewSim` (what they decide, not their screens), `SimConfig`, `RobustConfig`, `MoneyServerConfig`, `GridConf`, `Presets`, `Features`, `GridAccounts`, `AccountWriter`, `Database`, `LocationFinder`, `Slug`, `RegionName`, `SimName` |
| `OpenSim\Runtime\` | The instances as processes: the core and its runtime (dotnet, mono), its native libraries, the modules of a region, launching detached, running or not, waiting for ready, stopping with warnings, forcing | `bin/opensim` (startSim, stopSim, restartSim, status), `os-helpers` (osResolveRuntime, osNativeLibs, osSyncModules, osInstanceRunning, osWaitExit, osForceStop, osCountdown, osCountUsers...); kit `Cores`, `Runtime`, `Distribution`, `Releases` |
| `OpenSim\Console\` | The console of an instance: REST (opensim-rest-php), screen as a fallback, one command, a session | `os-helpers` (osConsole, osRest, osConsoleRead...), `bin/opensim` (commandSim, consoleSim), kit `Console` |
| `OpenSim\Network\` | Ports (of an instance, next free, in use), port mapping | kit `InstancePorts`, `Ports`, `GridRegistry::knownPorts`, `src/PortMapping` |
| `OpenSim\Archives\` | OAR, IAR, backup, rotation | `class-oar.php`, kit `Archive\*` |
| `OpenSim\Health\`, `OpenSim\Search\`, `OpenSim\Economy\`, `OpenSim\Database\` | as today | `class-health.php`, `class-search.php`, `class-economy.php`, `class-currency.php`, `class-database.php` |

### opensim-kit (`OpenSimKit\`, PSR-4 in `src/`)

| Namespace | What |
|---|---|
| `OpenSimKit\Console\Application` | The CLI: `bin/opensim` runs it. symfony/console, the base of Laravel's commands, so the commands move to the web administration as they are if it is a Laravel app |
| `OpenSimKit\Console\Commands\` | One class per command: `StartCommand`, `StopCommand`, `StatusCommand`, `ConsoleCommand`, `SetupCommand`, `EnableCommand`, `PortsCommand`, `NextCommand`, `ImportCommand`, `WebCommand`, `ArchiveCommand`, `BackupCommand`, `ProfileCommand`, `OarCommand`, `RestCommand`... |
| `OpenSimKit\Setup\` | The interactive wizard: the hub, the screens, the forms, the quick setup, the setup file runner; it asks, then calls `OpenSim\Provisioning` |
| `OpenSimKit\Ui\` | The interfaces of the wizard (prompts, preset answers, quiet) |
| `OpenSimKit\System\` | What is Debian's: the system user and the elevated writing, the packages of the system, the .NET install, systemd |
| `OpenSimKit\I18n\` | as today |

### What stays in bash

`packaging/*.postinst` and the other maintainer scripts, `packaging/opensim.service`, `libexec/install-dotnet` until it is
PHP, the bash completion. `libexec/os-helpers`, `libexec/newgrid`, `libexec/newsim`, `libexec/newregion`,
`libexec/load-helpers` disappear.

## The CLI and the engine

| Command | Engine |
|---|---|
| `start [target...]` | `OpenSim::start($target)` → `Grid::start()` (Robust, then its simulators) or `Simulator::start()` |
| `stop|restart [now] [target...]` | `OpenSim::stop($target, $now)`, `OpenSim::restart()` |
| `status` | `OpenSim::status()`: the instances, running or not, their users, their ports |
| `console|screen <instance>`, `command <instance> <text>` | `Simulator::console()`, `Console::command()` |
| `enable|disable <instance>` | `Simulator::enable()`, `Grid::enable()` |
| `enable|disable <grid> search|economy|profiles` | `Provisioning\Features` |
| `ports`, `next port|location` | `Network\Ports`, `Provisioning\LocationFinder` |
| `setup`, `import` | `OpenSimKit\Setup` (screens) → `OpenSim\Provisioning` |
| `web` | `Provisioning\Web` (helpers config, snippets) |
| `save|load iar|oar`, `backup`, `oar` | `OpenSim\Archives` |
| `profile` | `Config\Layout` (the installs of opensim.conf) |
| `rest` | opensim-rest-php |

## Port mapping

In the engine, `OpenSim\Network\PortMapping`:

- `PortMapperInterface`: `prepare()`, `open($port, $proto)`, `close($port, $proto)`, `address()`.
- `KickInsideMapper`: the default, the library `magicoli/kick-inside` used as classes (UPnP IGD in PHP, no command).
- `UpnpcMapper`: the alternative, the `upnpc` command of miniupnpc, kept in its own class.
- `PortMappingService`: the ports of an instance, what it mapped (state in the var root), opened before it starts and closed
  after it stops. `Simulator::start()` and `Robust::start()` call it, as `stop()` does: no `libexec/portmapping.php`, no
  call from bash.
- `PortMappingModule = kick-inside|upnpc` in the `[Grid]` of `<grid>/<grid>.conf`, `kick-inside` when not set;
  `PortMappingEnabled` as today.

## Legacy names

The helpers use `OpenSim_Health`, `OpenSim_Config`, `OSPDO`; w4os uses `Engine_Settings` (117 times), `OSPDO`,
`OpenSim_Search`, `OpenSim_Rest`, `OpenSim_Ini`, `OpenSim::empty()`, `OpenSim_Robust`, `OpenSim_Form`... When a class
moves to its namespace, its old global name stays, as a `class_alias()` (or a thin subclass when the methods change), in
`legacy/` of the engine, marked deprecated. The helpers move to the new names when they are touched; w4os when it is
ported to the helpers 3. The old names go away after that, not before.

## Class names

The code is prepared for Laravel, which the web app will be built with.

- A model is a class that has a counterpart in the database or in the files of the environment (a simulator has its config
  file). It keeps the plain name (`Grid`, `Simulator`, `Region`, `Avatar`) and lives in a `Models/` folder.
- Every other kind of class has a suffix that tells what it is: `Service`, `Command`, `Trait`, `Interface`, `Enum`,
  `Exception`, `Controller`, and so on. The file has the name of the class.
- A capability shared by models is an interface named `<Capability>Interface` (`OnlineStatusInterface`), in
  `Interfaces/`; a trait is `<Name>Trait`, in `Traits/`. These are the standard names; the conventions of Laravel (`Has...`,
  `Concerns/`, `Contracts/`) are for when the code is really Laravel's.
- A class that was named before this rule gets its suffix when it moves, decided by reading it.

## Moving a class to the engine: reconcile, never replace

The engine comes from 15 years of helpers: its classes carry solutions to problems already met. A class of the kit that
looks like one of the engine is not its replacement. For each one:

1. Read the engine class, its tests and its consumers (helpers, w4os, 2do). Read the kit class.
2. List what each does. What the engine does stays as it is.
3. What only the kit does is added to the engine in a compatible way (same names, same results for the current consumers),
   or stays in the kit when it is not general.
4. The kit class becomes a consumer of the engine class, or disappears.
5. Tests for both.

Same name does not mean same thing, and two names can be one thing: check in the OpenSim source. `RegionFlags` of the kit
and `OpenSim_Region::match_flags()` both interpret `regions.flags` (see the modelling notes): `Region_<Name> =
"DefaultRegion, ..."` in Robust is the text form of that column (`ParseFlags` in `GridService.cs`). `Ini` of the kit,
`OpenSim_Ini` and `OpenSim_Config` of the engine, `IniReader` of the importer and `crudget` in bash all read ini files.

## Modelling notes for the engine

- Robust is a server (a service provider), not a grid. A grid can have several of them (login, users, inventory, assets,
  presence), on several hosts or ports: OSGrid has one host per service, all on port 80. `OpenSim_Robust` is today an alias
  of `OpenSim`, waiting for that.
- Ports belong to every service: simulators, Robust, MoneyServer, consoles, the web server. `InstancePorts` is not a
  simulator thing.
- `flags` is a column, not a concept with a class of its own: `Region->flags` is the property, `Region->isOnline()` is
  `flags & 4`, `Region->isDefaultHG()` is `flags & 1024`. Many tables have such a column (regions, avatars, parcels,
  events, classifieds...), sometimes with several concerns mixed in it, and some other columns are bitwise too. So: no
  `SomethingFlags` class, one unified `flags` property on every model that has the column, and methods that interpret it,
  named the same wherever the meaning is the same. The text form (`+Name`, `-Name`, comma separated, as `ParseFlags` reads
  it in the Robust config) is a method of the model that owns the column, not a class.
- A capability shared by several models is an interface the models implement, each in its own way: `Region->isOnline()`
  and `Avatar->isOnline()` both come from `OnlineStatusInterface`, and the caller does not know how it is stored.
- Whatever has a counterpart in the database is a model (as in Laravel): the same find, save and delete methods, one base.
  `OpenSim_Region` and `OpenSim_Avatar` already are close to it.
- Avatar, account, user and agent are confused in OpenSimulator itself. Define the words first (what each one is in the
  tables), then the classes.
- Starting an instance is local: it runs a process on this machine. A front-end on another machine (w4os on a web server)
  reaches what is running through the REST console (stop, restart, commands), not a start; starting from elsewhere needs an
  agent on the machine, which is for later.

## The configuration of a simulator

OpenSimulator (0.7.5, 0.9.2.2 and 0.9.3.0 alike) reads `-inimaster` (the dist's OpenSimDefaults.ini when not given,
replaced when given), then `-inifile`, then the files they include, queued in the order they are found (so they win over
both), then the files of `-inidirectory`. Robust has no master: only `-inifile` (Robust.ini or Robust.HG.ini), which stays
as it is; this is about simulators.

Today: `-inimaster` is the dist's OpenSimDefaults.ini (implicit), `-inifile` the launcher file of the simulator, which
includes the grid's OpenSim.ini: fragile (the order of the includes has to be right) and unlike the standard config.

Decided (2026-10-11), the standard shape, two files at run time:

    -inimaster=<grid>/<master>.ini     generated: the defaults of the simulators of this grid
    -inifile=<grid>/sims/<sim>/OpenSim.ini   the simulator's own settings only (its name, ports, database, console, estate)

- The master is generated by the engine, never edited: the dist's OpenSimDefaults.ini (which stays as it is, a copy kept to
  be read), our corrections (the folders in /etc and /var, the paths of the read-only core), the settings the setup
  decided for the grid (its services, web URL, search, economy, profiles...), then the grid's own OpenSim.ini.
- `<grid>/OpenSim.ini` is what the administrator edits for every simulator of the grid, in the standard format, with only
  what differs. For a grid joined by a preset, it is the preset's OpenSim.ini (`/etc/opensim/presets/<name>/OpenSim.ini`).
- The architecture files (`config-include/`) are included from the master, as in the standard config: they come after the
  simulator's OpenSim.ini, so what is grid-wide there (the services) is not overridden by a simulator, by design.
- The master is made again whenever one of its sources is newer (a new core, an edited OpenSim.ini of the grid): by the
  setup, and by `Simulator::start()` before it starts, so nobody has to remember it.
- To decide: the name of the master (not `OpenSimDefaults.ini`, which is the dist's), and the conversion of the existing
  grids (vonda, the speculoos layout where each simulator includes the grid's OpenSim.ini).

## Order

Each step: the code at its final place, the old one removed (or left as a legacy alias), tests and the package scenario
green, then the next one.

1. **Skeleton.** Done. The engine has `src/` and the `OpenSim\` namespace; the kit has `OpenSimKit\Console\Application`
   (symfony/console) behind `bin/opensim`.
2. **Lifecycle.** Done, scenario `tests/Packaging/lifecycle-scenario.sh` green. `OpenSim::start/stop/restart/status`,
   `Models\Grid|Robust|Simulator|Instance|Core`, `Runtime\*`, `Console\*` (REST and screen), the port mapping with
   kick-inside called by start and stop. The commands `start`, `stop`, `restart`, `status`, `console`, `command`, `enable`,
   `disable` are commands of the application; the test doubles are `OpenSim\Testing\*` for the front-ends' tests.
3. **Reading the config.** Partly. `OpenSim\Config\{Layout, IniEditor, IniReader, TextFileService, GridFileService}` are
   the engine's; `crudget` is gone from the commands (it stays in `libexec/os-helpers`, for `install/*.sh` and `banuser`).
   Still to do: the configuration of a simulator (a generated master and the simulator's own OpenSim.ini, see above), and
   one reader for `OpenSim_Config`, `IniReader` and `InstancePorts`.
4. **Writing the config.** Mostly. `OpenSim\Provisioning\*` holds what decides and writes: `SimConfig`, `RobustConfig`,
   `GridShared`, `Presets`, `Features`, `MoneyServerConfig`, `DatabaseService`, `GridAccounts`, `AccountWriter`, the
   importers, `GridRegistry`, `Places`, `LocationFinder`... Still to do: `NewGrid` and `NewSim` (OpenSimKit\Setup) hold the
   screens and the apply together, the apply goes to `OpenSim\Provisioning`.
5. **The other commands.** Done: `ports`, `next`, `web`, `profile`, `oar`, `save`, `load`, `backup`, `rest`,
   `install-dotnet`, `setup`, `import` are commands. `setup`, `rest`, `import` and `install-dotnet` hand over to the program
   they come from (`DelegateCommand`), `libexec/import*.php` become classes next.
6. **The rest of `OpenSim\Installer`** moved: `OpenSimKit\Setup` (the wizard, its plan, its config), `OpenSimKit\Ui`,
   `OpenSimKit\System`. The namespace `OpenSim\Installer` is gone.
7. **Legacy names** of the engine in `legacy/`, the helpers on the new names. To do.
8. **Bash removed.** Done for the commands: `libexec/opensim-legacy`, `newgrid`, `newsim`, `newregion` are gone. Left:
   `libexec/os-helpers`, `load-helpers`, `venv-setup` (used by `install/*.sh` and `banuser`), until `banuser` is a command and
   `install/install.sh` is replaced by the package.

## Decisions (2026-10-11)

- The CLI on symfony/console.
- Writing the config in the engine (`OpenSim\Provisioning`), so that the administration and w4os can make a simulator too.
- The entry point of the front-ends: `OpenSim\OpenSim`, the global `OpenSim` of today becoming its legacy alias.
- The configuration of a simulator: a generated master and the simulator's own OpenSim.ini (see above).
- (2026-10-12) A grid name alone is its services (its Robust); its simulators join with `--sims`.
