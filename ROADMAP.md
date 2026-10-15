# Roadmap

## Milestones

- A release goes out when its list is done and tested
- What is not on it waits for the next one, even half done
- One line is one task: nothing grouped to look shorter
- The sections below the milestones are the backlog: an item belongs to a milestone only if named here

Principles:

- The engine is the only one that reads the database, the files and the consoles of a grid
    - it knows what a list of simulators is, where the records are, in which tables
    - CLI, manager (Laravel) and plugin (WordPress) receive arrays and show them their own way (a table, a Blade view, a shortcode)
- No feature of w4os goes away
    - the suite takes over all that is not specific to WordPress
    - the plugin becomes the bridge (`W4OS-PARITY.md`: package and milestone of each feature)
    - the plugin's port to the 3.0 of the helpers and the engine follows the suite's 3.0
    - a new integration should then take two or three days

Dates (2026):

- 10-18 11:00 PST: OSGrid's last public meeting (announce something concrete if there is; not required)
- 12-05/06: OSCC, a session presenting 3.0 (registrations open now)
- 12-31: OSGrid closes, most of its region owners move before

**What is needed first, and what it needs:**

| Need                                           | Needs                                                              |
| ---------------------------------------------- | ------------------------------------------------------------------ |
| bulk import of avatars for the OSCC organisers | a stable release; the accounts tried with a viewer                 |
| regions of OSGrid refugees on Speculoos        | a beta advanced enough, and a written procedure                    |
| the same on other grids                        | a stable release; security measures; able to take external regions |
| the 5 crashed simulators of hal                | a new stable beta                                                  |
| 3.0 before OSCC                                | all of the above (the manager can stay a beta)                     |

### 3.0.0-beta.7 (target: before 10-18, for Speculoos and hal)

- [ ] state of the refactoring frozen as a coherent beta of the family (the Unreleased list below)
    - [ ] `opensim setup`: install wizard from scratch to live grid with basic/default features (terminal CLI)
    - [ ] `opensim setup --web`) open wizard in a secured temporary website for web setup
    - [ ] `opensim import <setup file> --apply` full setup from YAML file, with no questions
    - [ ] `lifecycle` start / stop / restart / status of instances
    - [ ] `package`: relase as .deb on apt repo and as ready to use .zip (no composer install required)
- [ ] simplified procedure to move a region from OSGrid
    - [ ] written documentation: for manual operation by region owner
    - [ ] through admin tools below
    - [ ] through user-end form
- [ ] admin import tools
    - [x] add region
    - [ ] add region owner by regular avatar (not grid owner)
    - [x] import oar

- hal: one simulator migrated per beta, the others are tests
    - the problem met was probably config and compatibility, not investigated apart from the new installer
    - [x] Grand Place is back (beta.4)
    - [ ] one more simulator migrated with beta.7
    - [ ] importing a simulator from its database without an OAR: `opensim import` of a simulator (config and data)
    - [ ] `opensim restore`

### 3.0.0 (stable; CLI, Debian packages, web app)

Security, for everything that is hosted for others:

- [x] `opensim ban`
- [ ] Grief/Attacks detection
    - [ ] frequency of asset creation (moved up from Less urgent): count the assets an account creates over a period
    - [ ] threshold per grid, simulator, region, owner, avatar, asset type
    - [ ] report
    - [ ] automatic actions (notify, suspend until review, ban...)

OSGrid exploit (an `iar` load flooding random assets):

- [ ] find the description of the exploit used
- [ ] make sure at least one line of defence covers that exploit
- [ ] known OpenSim exploits: public advisories, forums, mailing lists
    - [ ] one line of defence per exploit

External simulators (regions connected directly to the grid):

- private ports are required for any simulator connecting regions directly (HG services are on the public ports)
- [ ] own servers only: firewall open to the planned servers only
- [ ] external simulators: restriction system
    - [ ] pre-registration
    - [ ] firewall open to the authorized IPs only
    - [ ] dynamic IPs (problem)

Services isolation, to reduce the attack surface (analysis first: security, performance; combinable):

- [ ] AssetService
    - [ ] separate database: `[AssetService] ConnectionString`
    - [ ] separate Robust instance (multiple Robust instances per grid): `[ServiceList] AssetServiceConnector`
    - [ ] filesystem instead of database: `[AssetService] LocalServiceModule = OpenSim.Services.FSAssetService.dll:FSAssetConnector`
- [ ] InventoryService
    - [ ] separate database: `[InventoryService] ConnectionString`
    - [ ] separate Robust instance: `[ServiceList] InventoryInConnector`
- [ ] UserAccountService
    - [ ] separate database: `[UserAccountService] ConnectionString`
    - [ ] separate Robust instance: `[ServiceList] UserAccountServiceConnector`
- the grid imposes its asset service on the simulators
- other services: not separated unless the analysis says so

Tests that are missing:

- [ ] `opensim import` tests suite
    - [ ] users (tried with viewer login)
    - [ ] inventory
    - [ ] default outfit
    - [ ] numbers of the system folders (`Settings` 56, `Material` 57)
- [ ] try `opensim import` on real grids: the config of a grid
    - [ ] simulator config (from existing grid)
    - [ ] setup file (from previous setup execution)
    - [ ] list of accounts (bulk import)
- [ ] try live: `opensim restore` of a backup on a new machine
- [ ] try live: `opensim save oar` for every region (cron backup)
    - [ ] oar backup rotation (`--keep N`)
- [ ] libgdiplus on Linux: what the regions do with the 6.1 of the distributions
- [ ] libgdiplus on Linux: what the packages should say
- [ ] helpers confirmed on the real sites: the 500
    - [ ] search with a viewer
    - [ ] offline messages

The engine gives the data (the manager, the plugin and the CLI only show it):

- [ ] all database access through `OSPDO` (PDO), never the `mysql` client
    - [ ] replace the `mysql` client calls of `DatabaseService`
    - [ ] queries checked for sqlite3 and pgsql (mysql only supported until then, not by choice)
- [ ] databases the engine knows
    - [ ] Robust services
    - [ ] assets
    - [ ] profiles
    - [ ] region store of each simulator (parcels, estates)
    - [ ] search index
    - [ ] economy
- [ ] standard model contract: `OpenSimModelInterface`
    - [ ] properties: id, name, slug, uuid, uri
    - [ ] methods: detail, update, create, delete, enabled, enable, disable, owner, group, flags, lastSeen
    - [ ] column names mapped to the standard names, per model
    - [ ] writes through a channel (database, console, ini file), refused when unavailable
    - [ ] relations
        - [ ] grid: simulators, regions, places, avatars
        - [ ] simulator: regions
        - [ ] region: places
        - [ ] avatar: places
- [ ] `Collection` returned by every list
    - [ ] first, last, count, array, json, csv
    - [ ] total, columns, filters available, sort, search, pages
- [ ] constants for flags: the bitwise value, named as in the OpenSim doc or code (and OSSL/LSL when it means the same)
    - in the class, or global when used often (like `TPLINK_*`)
    - false friends: the flags of `llGetRegionFlags()` are other flags
    - [ ] region: `DEFAULT_REGION` 1, `FALLBACK_REGION` 2, `REGION_ONLINE` 4, `NO_DIRECT_LOGIN` 8, `PERSISTENT` 16, `LOCKED_OUT` 32, `NO_MOVE` 64, `RESERVATION` 128, `AUTHENTICATE` 256, `HYPERLINK` 512, `DEFAULT_HGREGION` 1024 (opensimulator.org/wiki/Regions_(database_table))
    - [ ] remove the `RegionFlags` class
        - [ ] `Region->flags()` replaces it
        - [ ] text roles of the Robust config (`Region_<Name> = "DefaultRegion, ..."`) in the region and grid models
        - [ ] its uses (`GridInfo`, `RegionPlanner`, `SimulatorProvisioner`, tests of the engine and the kit)
    - [ ] parcel
    - [ ] user
- [ ] models: grid, simulator, region, parcel, avatar
    - [ ] parcel: bitmap of 4x4 m squares, which parcel holds x,y
    - [ ] region: is default, fallback, online
    - [ ] avatar: is god (level 200 and above)
- [ ] legacy classes (`OpenSim_Avatar`, `OpenSim_Region`, `OpenSim_Service`) folded into the models, aliases in `legacy/`
- [ ] function that reads the config aligned on Laravel's `config()`, `get_option` kept as an adapter for w4os
- [ ] avatars list
    - [ ] search, sort, created, last seen
    - [ ] filters by type (user, model, technical accounts), active, online
- [ ] regions list
    - [ ] owner, simulator, presence, control, internal port, size, last seen
    - [ ] filters by owner and by simulator
- [ ] simulators list
    - [ ] state (from the files and the processes), last seen
- [ ] grids list
- [ ] grid info, grid status
- [ ] record details (avatar, parcel, region, simulator)
- [ ] stats that leave out models, technical accounts
- [ ] other grids visitors are not excluded, they are counted appart (local residents only vs all visitors)
- [ ] a grid on another machine: config and database credentials read through the console of its Robust (as w4os 2.9.1 and speculoos do). To confirm: needed for the Speculoos site if it is not on the machine of the Robust
- [ ] manager uses it: its models of avatars, regions and simulators and its database connections to the grids are replaced by calls to the engine
- [ ] plugin uses it, instead of its own queries

What w4os has and the suite lacks (`W4OS-PARITY.md`):

- [ ] web assets server: serve `/assets/<uuid>.<format>`
    - [ ] textures of the grid converted for the web, read from the asset service of Robust
    - [ ] a cache
- [ ] picture of the avatar profile (needs the web assets)
- [ ] avatar models: the rule that makes a model (a name pattern, first or last name, or a fixed list of avatars)
    - [ ] list to choose from at registration, with name and profile picture
    - [ ] copy of the model's appearance and inventory into a new avatar
- [ ] SSRF of `Guide::read()` in the helpers (it takes any file or URL)
- [ ] rules of the avatar names: accents out, letters and digits only, first letter capital
    - [ ] restricted names (Default, Test, Admin, the model pattern)
    - [ ] a taken name is refused
- [ ] changing the password of an avatar
- [ ] new-users block
- [ ] translations w4os already has (cy, de, fr, it, nl, pt_BR) carried to the engine and the helpers for the strings that move (the new ones are translated as they are written)
- [ ] payments, conditional actions in the engine: restrict some actions of features based on conditions (payment, role...)
    - [ ] usable by consumer apps like w4os or the manager app
    - [ ] calling the engine's actions when a payment comes in or stops
- [ ] selling the grid's services: the catalogue of what can be sold
    - [ ] actions an order triggers in the engine (make a region, set its limits, suspend, restore), by hand at first

Documentation:

- [ ] engine API, for someone who integrates the suite in another framework
- [ ] helpers API, the same
- [ ] w4os bridge as its proof: what a new integration needs, in a page

W4os plugin update:

- [ ] port all features to the included 3.0 engine and helpers, leave only to the plugin what truly belongs to WordPress integration/bridge:
    - [ ] avatar creation and the avatar models
    - [ ] avatar profile and the web assets
    - [ ] lists of avatars and regions
    - [ ] grid info, grid status, popular places and web search contents
    - [ ] search, the destinations guide, the offline messages
    - [ ] currency
    - [ ] status of the grid and the checks of its URLs
    - [ ] authentication with the credentials of an avatar
- [ ] migration of the settings of a 2.x install (WordPress options, `config.php`) to the settings of the engine; the helpers still read `config.php` as before
- [ ] check if new version is still compliant with WP repository requirements, otherwise publish a last version with notice to new hosting

OpenSim Manager implementation:

- the manager can stay a beta for the author's own use, and ship when mature with 3.0.x or 3.1; 3.0.0 does not wait for it
- the management interface is rethought here, to be reapplied to w4os and to the CLI

- [ ] agent: a mailbox of requests to a process with the rights
    - [ ] start
    - [ ] stop
    - [ ] restart
    - [ ] status
- [ ] avatars
    - [ ] create (with the name rules and the choice of a model)
    - [ ] edit
    - [ ] change the password
    - [ ] bulk import
    - [ ] ban
- [ ] regions
    - [ ] add (on a simulator of the pool)
    - [ ] load an OAR
    - [ ] save an OAR
- [ ] simulators
    - [ ] add
    - [ ] modify
- [ ] grids
    - [ ] add
    - [ ] modify
- [ ] settings the models define
    - [ ] read
    - [ ] changed (through the agent)
- [ ] general editor of the `.ini` settings
- [ ] limits before public use: the details of a simulator only to its owner
    - [ ] only the public regions
    - [ ] personal data (emails...) only to the administrator and to the person
    - [ ] precautions of SECURITY.md (the attack that closed OSGrid), to study

### 3.1 (useful, not needed for a first release)

- [ ] world maps (navigable map on the web)
- [ ] avatar picker
- [ ] user inventory management (provided by engine, for UI), provided ready to display as a tree (folders, subfolders, items...)
- [ ] shared library (pick folders from grid owners inventory and add to grid library)
- [ ] sections of the search database (places, events, classifieds)
- [ ] finer rights of the members of a grid
- [ ] SSL management for the OpenSim servers
- [ ] docker/podman installation in the setup
- [ ] macOS (the clone, libgdiplus)

OpenSim Manager

    - [ ] grid info block
    - [ ] grid status block
    - [ ] new users block

w4os plugin

    - [ ] implement or port the new features above

### 3.2 (more, later)

- [ ] display names
- [ ] centralized NPC chatbot (avoid full chat script baked into NPC image, uneditable)
- [ ] AI integration: chatbot
- [ ] AI integration: MCP
- [ ] profile stream, see `opensim-flux` project
- [ ] additional currency modules: MoneyServer forks
- [ ] additional currency modules: PayPal, and the settings of Podex and PayPal, after an analysis (the Podex model trusts the grid owner, see `W4OS-PARITY.md`)
- [ ] true CMS (page builder, themes, ...): a viable alternative to WordPress or similar frameworks, for a basic website, we don't try to compete with full-featured CMS':
    - [ ] order of the pages in the menus
    - [ ] a summary of a page for the search engines
    - [ ] a page in other places than a menu (footer, sidebar...)
    - [ ] page builder
    - [ ] themes

### In the backlog, not in a milestone (decide where)

- [ ] parcel naming object in the library of the grid
    - the object is the script of `share/ossl-scripts` that names the parcel after the region
    - [ ] part of the library of the grid too: the original stays available to the users, a copy is rezzed in each new region
    - [ ] the operator chooses what a new region gets at its start (name of the parcel, music, media, flags): the script is the place for it
- [ ] OSSL functions
    - the standard config enables the functions with the defaults of the core
    - [ ] the operator customizes them: which functions, for whom

## Fixes

- [ ] PRIORITY: refactor as a true PHP application (PSR-4, consistent namespaces and classes, the CLI only a shortcut for the main features), see REFACTORING.md
    - [x] lifecycle and every command in the engine and the kit
    - [x] one reader of ini files (`OpenSim\Config\ConfigReader`, `GridConfig`, `ConnectionString`)
        - [ ] kit: `InstallConfig` (opensim.conf)
    - [x] `libexec/banuser` is `opensim ban`
    - [x] `os-helpers`, `venv-setup`, `load-helpers`, `install/install.sh` gone
    - [x] dependencies on crudini and bash-tools gone: `setup.sh` prepares the machine, `opensim install-dotnet` and the postinst scripts are PHP
    - [ ] configuration of a simulator
        - [ ] master generated per grid (`OpenSimGridDefaults.ini`, on top of `OpenSimKitDefaults.ini` of the kit), given with `-inimaster`
        - [ ] the simulator's own ini holds only what differs
        - [ ] conversion of the grids made the old way: part of the import
    - [ ] setup as steps described as data in the engine (fields, validation, conditions, plan), shown by the terminal and by a web page
        - [x] grid, simulator and its regions: `Grid::fields()`, `Simulator::fields()`, `Region::fields()`, `GridPlanner`, `SimPlanner`, `RegionPlanner`, `FieldForm`
        - [x] remote grid description (`RemoteGridPlanner`)
        - [x] setup file by field names (`SetupFile::gridValues()`...); `PresetUi` only answers the operational prompts
        - [x] web view for the whole flow (`opensim setup --web`: `WebUi`, `Web\Mailbox`, `php -S` on 127.0.0.1, address that works once)
        - [ ] machine install screens as data
        - [ ] operational prompts of `PresetUi` (enable, start, database administrator) as options of the calls
        - [ ] mode where the app is cloned in the folder of the site and locks itself once configured (WordPress-like)
        - [ ] web view beyond the quick setup (`tests/Packaging/wizardweb-grid-scenario.sh` makes a grid, its simulator and its region in the browser, as a person who is not the user of the install, and starts them)
            - [ ] advanced setup
            - [ ] core installed from the page
            - [ ] more regions and simulators
            - [ ] remote grid
    - [ ] old global names of the engine as aliases (`legacy/`), helpers and w4os on the new names
    - [ ] `install/iar2library`, `install/inv2library`: fill the common library that is in the inventory of the avatars
        - keep the logic
        - [ ] adapt as commands of the application (they still load the gone bash helpers)
    - [ ] importing a simulator from its host (reference: `install/transfer-remote-sim.sh`)
        - [ ] connect to the host
        - [ ] fetch the config and the data
        - [ ] make the simulator here: an OAR when it can be reached, the database only when it cannot
    - [ ] `setup.sh` in a clone builds the packages and installs them by default (developers' mode, with the scenarios of the tests)
        - [ ] running from the clone with composer becomes an option
- [x] Separation of concern is not respected in child libraries. Each library must remain strictly independent and never rely on data, classes or concept defined in the apps/projects/libraries consuming it.
- [x] The port-based location must only be used to set the initial center of the grild. Once it has been set, it is not overridable by the rule.
- [x] Wrong syntax for `opensim load iar|oar` and `opensim save iar|oar`: action and save must stay together, `opensim save iar [instance]`, not `opensim save [instance] iar`
- [x] Could not get load/save oar/iar to work
- [ ] helpers: web returns 500
    - [x] answer with the new `config.php` on a test grid
    - [x] say when `helpers.ini` is unreadable by the web user, instead of a 500
    - [x] packaged helpers answer behind real nginx, Apache and Caddy (`web-scenario.sh`)
    - [ ] confirm on the real site: what does the error log say
- [ ] `screen` is not a dependency (to review with the whole setup)
    - without it the consoles are the REST ones only (the default anyway)
    - today `Installer` requires it and `setup.sh` installs it
- [ ] libgdiplus: dependency of OpenSim on every platform ([wiki](http://opensimulator.org/wiki/Dependencies))
    - [x] Linux: `apt`
    - [ ] macOS: Homebrew `mono-libgdiplus`, its `libgdiplus.dylib` copied next to the binaries if not found
        - [ ] engine looks for it and installs it on Linux only (`DotnetInstaller`, `RuntimeProbe`, `StartService`: `ldconfig`, `.so`)
        - [ ] `setup.sh` installs it
        - [ ] clone on macOS tried to the end
    - [ ] version on Linux: the wiki advises 6.0.5 (mono repository, with `libtiff5`, held) and says to avoid 6.1 (broken or leaking memory)
        - 6.1+dfsg is the distributions' (Ubuntu), so the one our packages depend on
        - [ ] check what the regions do with it
        - [ ] decide what the packages should do
- [ ] setup from a clone: the archive of the core belongs in the cache
    - today downloaded into `src/` of the clone (`src/opensim-0.9.3.0.tar.gz`), the folder of the PHP code
- [ ] setup: work from regions, not from grid/sims (a design flaw)
    - from the user's point of view: a grid, with regions
    - simulators are a pool of servers providing the regions
    - [x] grid screen lists regions
    - [x] "Add region" asks the simulator of the pool
    - [x] `opensim save|load oar` and `opensim backup` take a sim or a region
    - [ ] setup proposes creating regions first, with the choice of a new simulator or one from the pool
    - [ ] command-line tools allow both sims and regions as second argument (the other commands)
- [x] Changelog is not a user guide, keep it as a SHORT list of changes, no explanations!
- [x] Tests output is truncated, KEEP descriptions and notifications SHORT
- [x] (Test fail) opensim save and load → refuse a file that exists a simulator that does not run a missing password
- [x] (Test fail) opensim backup → holds the configuration the data and a dump of each database of a grid and nothing the users backed up
- [x] (Test fail) opensim backup → takes the archives of the users and the logs when asked
- [x] (Test fail) opensim backup → is the one of a simulator without the other ones
- [x] (Test fail) opensim import the config of a grid → tells the plan and the settings it keeps and writes nothing without apply
- [x] Architectural misconception: opensim-engine include codes referencing the class `OpenSim_Kit`, which is a nonsense: Engine is the a standalone library, the Kit consumes it, not the other way around
- [ ] `opensim stop` on a simulator with an NPC
    - [x] real users counted from `show users`, NPCs (`NPC Root`) skipped
    - [x] rows of `show users` read on a screen console too, NPCs left out
    - [ ] confirm with a real NPC (only the format was checked on a real core; it does not seem to skip the NPCs)
- [x] dependencies packages must be added to the release assets in their own repositories (done: build-tools, `dev/release.sh` in each project)
- [x] opensim status "down" count should not be displayed when none of the instances are down
- [x] a new region is named after itself without a restart of the simulator (the object of `share/ossl-scripts`, loaded through the console)
- [x] firewall
    - [x] `opensim ports --ufw` tells the rules
    - [x] `INSTALLATION.md` has a Firewall section
    - the setup does not apply the rules
- [x] the simulators of a grid with helpers know where `offline.php` (`[Messaging]`) and `register.php` (`DATA_SRV_<Grid>` of `[DataSnapshot]`) are
- [x] message of the day
    - `[LoginService] MessageUrl`: URL whose text is shown at login (read by Robust when it starts, `WelcomeMessage` when it cannot be), found in the code of the core (`LLLoginService`)
    - `motd.php` of opensim-helpers serves the `motd` of `helpers.ini`
- [x] the destination guide is `guide.php` of the helpers, `DestinationGuide` of the Robust config points to it
- [x] search page of the web site
    - `search` page of the helpers (`/helpers/search`, `?q=`, `/<topic>/`) reads what `parser.php` indexes
    - `search` of `[GridInfoService]` points to it (see below)
- [ ] packaging scenario with docker (podman done: full scenario on Debian 12 and Ubuntu 24.04, packages from `apt-package`)
- [x] try the web server examples written by the setup (`<grid>.caddyfile`, `<grid>-nginx.conf`, `<grid>-apache.conf`) on a real Caddy, nginx and Apache (`tests/Packaging/web-scenario.sh`; nginx needed a fix)

## Critical improvements

- [x] `opensim save|load iar|oar` and `opensim backup`: the archives of the users in `<data of the grid>/backups`, the backup of the administrator (configuration, data, databases)
- [ ] `opensim restore` of a backup of the administrator on a new machine
    - [ ] the install
    - [ ] the databases
    - [ ] the data
    - [ ] check what the archive holds
- [ ] archives of the users
    - [ ] `opensim save oar` for every region of a grid (rotation, `--keep N`)
    - [ ] OAR of a region that belongs to a user who is not the owner of the grid (what the owners of regions can ask for themselves)
- [x] backup: rotation of the old backups (`--keep N`), same for the archives
- [x] bulk avatar creation, as `opensim import [grid] FILE --users`
    - from a list provided by a third-party source: CSV, JSON or YAML
    - directly in the database
    - relies on the install the profile gives, with or without the kit
- [ ] accounts made in the database, tried with a viewer on a test grid
    - [ ] login
    - [ ] inventory
    - [ ] default outfit
    - [ ] numbers of the system folders (`Settings` 56 and `Material` 57) checked against the core
- [ ] `opensim import` tested on real grids: what works, what is missing
    - [ ] config of a grid
    - [ ] config of a simulator
    - [ ] setup file
    - [ ] list of accounts
- [x] add opensim-helpers package and examples of configuration as alias/subfolder in caddy, nginx and apache2
- [x] `unstable` suite in the apt repository for the pre-releases, `stable` for the releases
    - [x] suite in `conf/distributions`, `APT_REPO_PRERELEASE_DIST`
    - [x] `apt-package` chooses the suite of each package: a `~` or `-dev|alpha|beta|rc` makes a pre-release, a Debian revision or a `0.4+git…` snapshot does not
- [ ] `build-tools release` refuses a pre-release when the apt repository has no suite for it
    - [ ] betas published in `stable` to move to `unstable` (cleanup, later)
- [ ] add docker/podman installation support in setup
- [ ] SSL on the OpenSim servers (0.9.3 supports it)
    - simulator: `http_listener_ssl` and a PKCS12 certificate; `EnableCertConverter` makes it from the PEM files of the CA (`fullchain.pem`, `privkey.pem`) at each start
    - Robust: `[Network] https_*` and `cert_path`, no converter (a `.p12` made with openssl)
    - [ ] base it on certbot, not on the certificate of Caddy (the `ExternalHostName` of a region is not always a site)
    - [ ] deploy hook: copy the PEM where `opensim` reads them (`/etc/letsencrypt` is root's), restart the instances (the conversion only happens at start)
    - [ ] check: the `SSL/` folders are made in the working directory of the process, and the cores are read-only
    - [ ] try first on a public host with podman
    - [ ] section in the setup of a grid
    - [ ] `opensim ssl` command
- [ ] rename composer packages to match github account GuduleLapointe

## Next steps

- [ ] full import procedure for existing grids/simulators
    - [x] config (`opensim import`)
    - [ ] database
    - [ ] assets
    - [ ] other data
    - first use: importing a region from OSGrid to another grid (local or a preset): OAR saved from the sim of its owner, region made here, OAR loaded
    - OSGrid closes on 2026-12-31 (the OSGrid preset is obsolete, kept for now)
- [x] add/update bash completion
- [ ] zip packages for simple download, in the release assets alongside the source code and the apt package
    - [x] built by `dev/release.sh` with every release
    - [ ] ready to use: no composer install required
- [x] simplified installation script: `setup.sh` (`curl … | sudo bash`, or `./setup.sh` in a clone)
    - [ ] test the clone mode
    - [ ] test macOS (Homebrew), which the container scenario does not cover
- [x] add basic ready to use website (the placeholder site of the `opensim-web` package)
- [ ] add ready to use default avatars
- [ ] add default Inventory package
- [ ] add nat/port forwarding instructions or presets (with standard tools or third-party provides like ngrok, cloudflare...)
- [ ] make most opensim-tools features installation-agnostic (kit installs and custom/standard OpenSim installs)
    - `/etc/opensim/opensim.conf` becomes only a reference pointing to the user's actual core installation and configuration files location (e.g. `/opt/osgrid`, `~/diva`, `~/opensim/opensim-0.9.3.0/bin`...)
    - [x] `opensim profile list|add|default|remove` registers an install made by hand (core, config and data directories)
    - [ ] a grid follows the profile of its own install, the default profile is only the default of the new grids
    - [ ] the system user to run the instances as is per profile, not only `SystemUser` of `[Defaults]`
    - [ ] the setup offers to register an install (the core menu)
    - [ ] no script assumes `/etc/opensim`, `/var/lib/opensim` or `/usr/share/opensim` (review, one fix per file, with a test)
- [x] search tested on the grid of the package scenario (`STOP_AFTER=search tests/Packaging/run`, `tests/Packaging/search-scenario.sh`)
    - registering, snapshot, places, popular places, land, events, classifieds, people, with the keys the module sends
- [x] the real simulator registers by itself when it starts and tells a parcel shown in search (`ShowDirectory`, read at start), which the places and the popular places give (`tests/Packaging/search-scenario.sh`)
- [x] search: a simulator that cannot be reached, or answers "try later" (503: one request a minute per address)
    - asked again later, more and more late
    - forgotten after ten failures (it was asked at every run)
- [x] tested on the grid of the package scenario
    - [x] offline messages (`offline-scenario.sh`)
    - [x] economy: currency, land tool (`economy-scenario.sh`)
    - [x] helpers pages: message of the day, guide, images of text, events, directory (`pages-scenario.sh`)
- [ ] offline messages on a grid without helpers
    - the setup writes no `[Messaging]`
    - the `OfflineIMService` of Robust, which the V2 module needs, is not enabled
    - options: helpers (mails, no limit) / V2 module with the Robust service (no mails, 25 messages) / both
- [x] a simulator without a region hangs at its first start (OpenSim asks the questions of a first region on a console nobody reads)
    - [x] the setup does not start it
    - [x] `opensim start` refuses it and says to add a region
    - [x] `opensim stop` forces what does not stop
    - seen on vonda with `vonda_back_office`
- [ ] delete a region (later)
    - checks: users in it, neighbours, estate, parcels and objects
    - [ ] backup first
    - [ ] registry of the grid cleaned
    - [ ] registry of the search cleaned
    - today: the console (`delete-region`), its file and a row of the grid database, by hand
- [ ] helpers: the guide reads `source` from the request when the grid sets none, so any file or URL the server can read
    - [ ] ignore it, or accept only URLs
- [ ] helpers: style, script and picture of the guide and style of the directory page are linked with the path of the files (`/usr/share/opensim-helpers/...`)
    - [ ] the router should serve them
- [ ] helpers: an empty calendar keeps the old events (`eventsparser.php` takes `[]` for an invalid answer)
- [x] helpers: the Gloebit sandbox is `[Gloebit] GLBEnvironment`, read by `addons/gloebit.php` in the ini files of the grid (including `config-include/`)
- [ ] helpers: without a money server they write `transactions` with other columns (`sourceId`, `destId`...) than with one (`sender`, `receiver`...)
    - [ ] check which module makes which
    - Gloebit makes neither (its tables: `GloebitUsers`, `GloebitTransactions`, `GloebitSubscriptions`): for the MoneyServer review
- [ ] the engine has its own `OpenSim_HypEvents`, with the faults the helpers had: which one is the source?
    - [x] done for `functions-economy.php`, now only the engine's (`make_random_guid` and `opensim_get_region_info` included)
- [x] helpers: one layout for every page (left sidebar, main content, right sidebar)
    - blocks (grid info, grid status, message of the day) that templates use as variables
    - the content alone for a viewer, except on the splash page
    - `/health` page listing the addresses of the grid with their status (`pages-scenario.sh`)
- [ ] helpers
    - [ ] `news` block of the right sidebar: what does it show?
    - [ ] shortcode form `[grid_info]` for texts
    - [ ] Bootstrap or Tailwind for the looks
- [ ] helpers: next blocks and pages
    - [x] popular places
    - [x] web search (`?q=`, `/<topic>/`)
    - [x] avatar profile with its stream (the term of Firestorm)
    - [ ] search categories as sub-paths
    - [ ] web assets (`<prefix>/assets/<uuid>.<format>`): textures of the grid converted for the web, read from the asset service of Robust
    - [ ] the stream itself (see opensim-flux)
- [ ] `opensim-flux` (later): a project of its own, to be added as a submodule when it starts (the plan is in its README)
    - optional social stream by avatar, on ActivityPub
    - installed with the kit, enabled apart (`Flux.ini` and/or `[Flux]`)
- [x] helpers, URLs: each service is where the official settings put it (addresses on this site only), `<path>/<script>.php` is the default, a route answers its sub-paths
    - [ ] guide topics as sub-paths (`/guide/<topic>/`)
    - [ ] search categories as sub-paths
- [ ] search does not work on vonda
    - symptoms: places and land for sale "Searching" for ever; classifieds of the profile "Loading"; a profile edit not kept
    - causes, found by reading its config:
        - the simulators have no `[Search] Module = OpenSimSearch` and `SearchURL`
        - no `[UserProfiles] ProfileServiceURL`
        - `[DataSnapshot]` of the grid-wide `OpenSim.ini` points `DATA_SRV_Vonda` to `query.php`, which made the helpers answer the search with `register.php`
        - the setup never wrote them (the search scenario calls `query.php` as the module does, it never ran a simulator with the module)
    - [x] fixed: the script an address names answers it
    - [x] the simulators are made with them
    - [x] `opensim enable|disable <grid> search|economy|profiles` writes them in the ones that exist (`FeaturesTest`, `package-scenario.sh`)
    - [x] the parser no longer needs the web server to fetch its own address (the helpers parse the registered simulators in-process, at registration and every ten minutes by a query)
    - [x] on vonda: the places query answers, the simulators restarted have the module and the full profiles
    - [ ] confirm on vonda with a viewer
        - [ ] places
        - [ ] land for sale (nothing is for sale there, the list is empty)
        - [ ] classifieds of the profile
        - [ ] a profile edit kept
- [ ] OpenSimSearch 0.5 beta: the "show in search" box of the profile
    - OpenSimulator ignores it (`UpdateAvatarProperties` in `MySQLUserProfilesData` never writes `profileAllowPublish`)
    - the module tells it to the helpers with the secure session of the avatar (`avatar_publish_update`)
    - [ ] test with Firestorm on vonda
    - [ ] when OpenSimulator keeps the box itself (patch to submit): the module must check it before it applies the change
    - the DLLs are in `contrib/OpenSimSearch` until a release holds them
- [x] presets of the grids that are elsewhere: `share/presets/<name>` (speculoos, osgrid)
    - the structure of the folder of a grid, with only the files that have settings
    - copied into `/etc/opensim/presets` when a simulator joins by one, read from there
    - `grids/` stays for the grids of this machine
- [ ] a region that exists already on the grid (Grand Place of speculoos.world, a region registered at osgrid.org)
    - today the wizard refuses its name
    - [ ] take its UUID, position and size from the grid (Robust through the private port, or `link_region` of the gatekeeper)
    - [ ] write them in the region file, do not create it
    - `opensim load oar` already restores the content
- [ ] the settings of a grid are in the config of each of its simulators (`[Search]`, `[DataSnapshot]`, `[Messaging]`, `[UserProfiles]`, the includes of the architecture...)
    - the sim.ini must hold only what is the simulator's own (as the sims of speculoos: [Launch], its port, its database)
    - the grid's OpenSim.ini holds what is common
    - OpenSimulator (0.7.5, 0.9.2.2 and 0.9.3.0 alike) takes, in this order: `-inimaster` (default OpenSimDefaults.ini, replaced when given), `-inifile`, the files they include (which win over the config), the files of `-inidirectory` (the last, over everything)
    - [ ] decide how the grid's OpenSim.ini and the sim.ini take these places, without the defaults being lost
- [ ] presets
    - [ ] the grid tells the rest of its description in `setup.json` (as a grid of this machine does)
    - [ ] presets of complete grids (the whole folder)
    - [ ] the private ports to be allowed by the grid (speculoos.world and osgrid.org have them open for now: the servers of the simulators will have to be registered)
    - [ ] the in-world search of speculoos.world is the 2do directory with `gk=`: check it
- [ ] OSGrid runs a development OpenSimulator (commit `ef8a36b`, 2026-04-17, 0.9.3.1)
    - the unstable core of the kit is newer (`04ca1d9`, 2026-09-26)
    - [ ] check a simulator of the kit against OSGrid before announcing it
    - their OpenSimSearch (`modules-dotnet8`) is the source our fork started from, without a change
- [ ] custom display names (a display name that is not the account name)
    - OSGrid publishes a closed module (`DisplayNameSimModule`, `contrib/osgrid`)
        - serves the viewer capabilities (`SetDisplayName`, `GetDisplayNames`)
        - asks a web service of OSGrid (`/get?id=`, `/set`, `/reset`, answers with `display_name`, `display_name_next_update`...)
    - for our grids, one of:
        - [ ] a service in the helpers and a module of our own
        - [ ] the same API, so that their module works with ours
- [ ] one place for the OpenSimulator cores
    - the release ones (`core/`) and the development one (`upstream/opensim`, a submodule) all go to `core/`
    - OpenSimulator is not an upstream of the kit, the kit includes it
    - files: `packaging/build`, `packaging/remote-build`, `.gitmodules`, `DEVELOPERS.md`
- [ ] web search settings, to confirm with each viewer
    - tested with Firestorm (default mode)
        - `[GridInfoService] search` reaches it (its web search tab and the parameters of the grid); the standard search uses `query.php` with its own interface
        - `[LoginService] SearchURL` or `[LoginService] search` alone do not, though `SearchURL` is the documented one (the core sends it in the login response and as `search-server-url` of the grid features)
    - [ ] who reads `SearchURL`: other viewers, or Firestorm in another mode?
    - [ ] test the other modes of Firestorm (Phoenix, Viewer 7, hybrid, text)
    - [ ] test other viewers
    - the setup gives the same value to both settings, `search` is the one the helpers test
    - a simulator's own `[SimulatorFeatures] SearchServerURI` is for a complete management interface (w4os), not the helpers
- [ ] helpers: `GridStatus` and `GridStatusRSS` of `[GridInfoService]`
    - Firestorm reads them, in the formats of Second Life, from the OpenSimExtras of the simulator features, where `GridService.SetExtraServiceURLs` of Robust puts them from `[GridInfoService]` (`get_grid_info` says them too)
    - `GridStatus`: a light HTML or plain text page, the viewer takes one line of global status from it by keyword (ONLINE green, OFFLINE red, DEGRADED or MAINTENANCE orange)
        - [x] `/helpers/grid_status` (ONLINE, OFFLINE) and `/helpers/grid_info` are public pages
        - [x] `GridStatus = "${Const|WebURL}/helpers/grid_status"` is all a grid needs
        - [ ] DEGRADED
        - [ ] MAINTENANCE
    - `GridStatusRSS`: a standard RSS 2.0 feed (dates RFC 822: title, link, guid, pubDate, description in CDATA, everything in English)
        - [ ] first only the moments the grid changes state (the parsers have part of it)
        - [ ] later real messages (another RSS source, a social feed)
    - examples: status.secondlifegrid.net (`history.rss`, `history.atom`, and the page, like health but for the public)
- [ ] engine: `OpenSim_Config` (reads an environment into a tree that follows the ini files, with includes and constants)
    - the machine's grids (`opensim.conf`, `/etc/opensim/grids`, `OPENSIM_GRID`) are still found by the helpers
    - `OpenSim_Ini` (one file, used by the wizard of the plugin) is a second reader
    - [ ] make them one
- [ ] helpers: lists for LSL scripts
    - the guide and the search give the list as text: title and URL separated by a pipe, a text alone is a title, a dash alone a new column, a pipe alone an empty row
    - [ ] a guide source can be this format too
- [x] helpers, currency: the key that ties a quote to its purchase
    - `MoneyScriptAccessKey` of `[MoneyServer]` in `MoneyServer.ini` beside Robust.ini
    - the original file of the MoneyServer whole (contrib/MoneyServer), completed by the setup with a random key
    - no default key anywhere: without it the currency helpers answer a bad configuration
    - installing the package adds the file to a grid that lacks it
- [ ] the setup merges the distribution into the files of a grid (new settings are taken)
    - installing a package adds missing files only (MoneyServer.ini for now)
    - a new release of the distribution does not reach the files of existing grids until `opensim setup` configures them
    - [ ] decide whether the package should merge too
    - [ ] decide what to do of the comments an operator wrote in a file that is merged
- [ ] read the configuration loader of OpenSimulator (the code of the core): the real order in which an ini file, the files it includes and OpenSimDefaults.ini are merged
    - the helpers read a file, then what it includes, the included values replacing the earlier ones: a guess, which does not matter to them
- [x] helpers, currency, Gloebit (read in `contrib/Gloebit/src`)
    - the module keeps its balance and its transfers at Gloebit
    - its own tables are in `[Gloebit] GLBSpecificConnectionString`, else the `[DatabaseService]` of the simulator (not Robust's)
    - it makes no `balances` or `transactions`: the helpers never need them with it
    - it answers the quote, buy and land calls itself when `economy` points at the simulator, and sends the rest to the grid: currency.php and landtool.php
- [ ] currency modules (later)
    - MoneyServer (Podex needs it, so does a game currency without real value): an old version, unmaintained, its site is gone
        - [ ] compare the forks (DEVELOPERS.md)
        - [ ] keep a stable one, or make one that reads its settings like the other modules (its ini is looked for next to its executable, its credentials are not dotNet connection strings)
    - PayPal: the original module is outdated
        - [ ] compare the recent forks
    - until then the helpers keep the Podex and PayPal code they have, with no addon and no setting
- [ ] helpers: still to port from the references (speculoos, 2do, w4os.org); nothing of the economy or of the scripts of the root is missing
    - [ ] `TROUBLESHOOTING.md` and `INSTALLATION.md` (the text, for the README and the kit)
    - [ ] `class-helpers-migration-2to3.php` (the move of the old `config.php`, or `opensim import`)
    - [ ] HYPEvents client class of speculoos (`class-helpers-hypevents.php`) against `events.php`
    - [ ] checks of the install wizard (`is_robust_ini_file`, `robust_test_config`, `getTestCurrencyQuoteParams`) in `opensim import` and `opensim web check`
    - the WordPress glue (`wp-load.php`, `config.wp.php`) stays in the w4os plugin
- [ ] add to the README of opensim-helpers (and of the engine) a short section on how to use them with the OpenSim kit
- [ ] install instructions in the README of `lsl-ossl-zed`
    - rust, clone, `zed: install dev extension`
    - the prebuilt LSP binaries only cover Linux x86_64 and macOS

## Less urgent

- [ ] `opensim-admin` package: the complete admin interface (web)
    - same procedures as the CLI
    - the simplified web app, WordPress (w4os)
    - the architecture is being decided (engine actions, front-ends as adapters)
- [ ] the web setup serves HTTPS
    - why: some browsers refuse forms over plain HTTP, and the passwords must not cross the network in clear
    - self-signed is fine for this temporary step
    - options: the server of the Symfony CLI (the method the user knows), or a better one (Caddy `tls internal` in front of `php -S`)
- [ ] detect abuse by the frequency of asset creation, not by a total quota: moved up to 3.0 (Security)
    - an account can have thousands of assets for good reasons
    - an account, or its scripts, creating a suspicious number of assets over a period
    - complements `opensim ban` (OSGrid closes after an `iar` flood of random assets, announcement of 2026-10-12)
- [ ] services split (assets, inventory, users...) in their own Robust and database, to back them up apart: moved up to 3.0 (Security, services isolation)
- [x] localize the setup with gettext (a requirement of the project): the messages of the PHP setup are translatable (`locales/`, `locales/update-pot`)
- [ ] localize the launcher and the bash scripts with gettext
- [ ] a first translation of the setup (`locales/fr.po`)
- [ ] localize helpers
- [ ] `opensim online`: show who's online

## Could be great

- [ ] Memory/CPU usage monitoring
    - notify admin and/or restart Sim above given thresholds
    - maybe better to suggest a dedicated monitoring tool to handle that
