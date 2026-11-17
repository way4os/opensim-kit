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

### Before OSCC (12-05/06): the order of the work

The kit itself (`opensim-kit`, its items below) is suspended: it comes back after all of this.

Priorities, in this order:

- [ ] 1. engine and CLI working, to move every simulator of Speculoos to v3/podman (grandplace already runs v3)
    - [ ] read-only inventory of the simulators to move: redoutable, residences, grandplace, the welcome simulator of grid
    - [ ] a first test simulator, a backup before each one
    - [ ] restore command, `opensim ports --ufw --from`, systemd supervision (`opensim.service`)
- [ ] 2. helpers working, to move the Robust server grid.speculoos.world
    - [x] the v3 helpers run on watta.world (live)
    - [ ] w4os 2.x keeps working: same database, same helper URLs (contract test on a copy of the grid database)
    - [ ] what watta.world does not cover: a grid with real users, w4os 2.x as the client, the data of an existing grid
- [ ] 3. minimal web UI: a small website of its own (not the helpers, not the manager)
    - it is a website: registration, one or two presentation/help pages (`.md`), user management for the managers
    - criterion: universality, the lightest system and the easiest install for an ordinary user on an ordinary server
    - a flat-file `.md` system, no admin UI for the pages, no page builder; Grav is the author's preference, not a requirement: the choice stays open until the prototype shows it
    - built on the engine API and the blocks of the helpers (splash, profile, search)
    - the manager (Laravel) is the big kit app, for 3.1/3.2 or later
    - [ ] the bridge of the chosen system: a thin bridge between the engine API and the site
- [ ] 4. w4os migrated to the new engine and the new helpers, for OSCC: a simplification and a cleanup of the plugin, not a new build
    - [ ] the plugin is a thin bridge between the engine API and WordPress

The rule: look at w4os first.

- the w4os plugin (`~/Projects/w4os-wp`, branch `speculoos`) is the reference for the methods: a lot is already developed there, even if it is no longer what provides it
- before coding a feature, look at how w4os does it and follow that method, unless the engine already has a better one
- example: the avatar models (the patterns, where the picture comes from) in `class-w4os3-model.php` and `v2/class-avatar-model.php`

The rule: the engine API is the single source.

- the CLI, the site bridge and the WordPress plugin only consume it: no logic of their own, very quick to prototype
- whatever one of them needs and the engine does not give is added to the engine API, never to a bridge
- the CLI itself is meant to start and stop the simulators through the API (the target; for OSCC the CLI does it directly)
- a person's avatars are linked by the email of the account, as today: nothing to decide
- exists in the API: avatars (list, get, make, change with password, email, level, home, profile fields, delete), regions, simulators and grids (enable, disable), parcels, estates, archives (read), destinations, assets, tasks (list, run), addresses, info, monitor, health, blocks, profile, search, grid status, setups (quick)
- what the engine API still lacks, for OSCC:
    - [x] sign in (check a name and a password): in the engine (`OpenSim\SignIn`), to expose in the API
    - [x] choose an avatar model (outfit) at registration
    - [x] confirmation of a registration by email, usable from any site and from the CLI
    - [x] forgotten password, the same
- later: a matrix of compatibility tests, a v3 simulator working with a v2 Robust and the other way round
    - [ ] simulators × Robust, each on 0.9.2 and 0.9.3
    - [ ] helpers and installs: vanilla (by the OpenSimulator book), v2, v3
    - [ ] for each pair: profiles, groups, search, economy, offline messages
    - the case that asks for it: on Speculoos, the profiles come back empty seen from the v3 simulators on the v2 Robust, and work from the v2 Residences (being diagnosed)
- what the engine API lacks, after OSCC:
    - [ ] w4os 3.x: the picks of an avatar as a list of destinations, in the lines of the Gudz Teleport Board (`DestinationList::format()`, `format=list`: "Name|Region URL|x,y,z", a category by its name alone), at an address of its own, so that it can be the `GuideSource` of a guide or the source of a board; built after the current queue
    - [ ] start, stop, restart (the CLI uses the API for it)
    - [ ] make a region in an existing grid, write estates and parcels, make and restore a backup
    - [ ] where a simulator runs: a `runtime` attribute (`host`, `podman`, `docker`), told by the cgroup of its process (`/proc/<pid>/cgroup` names libpod or docker), a file read for each running simulator of a list, nothing asked of podman; Linux only, `host` elsewhere
    - [ ] a simulator of another machine: an `ssh` connection (`connections.ssh`, none, configured, ok, failed), configured when an SSH host of the install names its machine, tried on its page alone
    - [ ] the ban of an avatar, as a whole: a plan, then its apply (as an import or a restore: `mode` plan by default, a god's, asked first), in the engine API; the CLI and the sites only consume it
        - [ ] whom: a name or a UUID, the account of the grid and the visitors of other grids (`First.Last@grid`), a UUID nobody here has (`Avatar::lookup`, `unknown`, `visitor`: in the engine already)
        - [ ] how: a hard ban (an account of the grid: status `banned`, in the engine already) or a ban by estate (an account of the grid or a visitor), with the choice of the estates (`estateBans`, `Estate::ban`: in the engine already)
        - [ ] the objects: return or delete what they own in the regions, what they made (the prims by OwnerID in the database of each simulator, the assets by CreatorID: `Avatar::leftovers` finds them already), by the console of a running simulator or by statements
        - [ ] the land and the regions: their parcels back to the owner of the estate, the regions they own given to another
        - [ ] their data: the statements that delete it, for review (`Avatar::purgeSql`, `travelingData`: in the engine already); a hard ban keeps the account, its name and its UUID
        - [ ] the base to port, adapted to the conventions of the engine (models, plan and apply, notices): `opensim ban` of the CLI (`~/Projects/opensim-cli`, `libexec/ban`, `src/php/BanPlan.php`) and of the kit (`src/Console/BanPlan.php`, `tests/Unit/BanPlanTest.php`, `BanCommandTest.php`, `tests/Packaging/ban-scenario.sh`): reviewable SQL, nothing changed until it is run

The w4os names:

- w4os-app: the full-featured app (Laravel, rich, PWA, native...), for later; what `opensim-manager` becomes
- the light site anyone can install on any server: its own project, with its own apt package and installer
    - [ ] its name: `w4os-site`, `w4os-minisite` or `w4os-basic-ui` (open)
- w4os is the main brand: it cannot be the name of the WordPress plugin
- the plugins: `grav-plugin-w4os` (`~/Projects/grav-plugin-w4os`) and the new WordPress plugin `w4os-wp`
    - `w4os-wp` is the same name everywhere (wordpress.org and direct distribution), and says that it is the WordPress one
    - wordpress.org refuses "wordpress" and "plugin" in a slug and may flag "wp": accepted
    - the current WordPress plugin, `w4os-opensim-web-interface`, stays on v2 and shows an admin notice that points to `w4os-wp`
- the suite: `w4os-app` (later), the light site (name open), `grav-plugin-w4os`, `w4os-wp`

In parallel, once 3 starts:

- [ ] A. engine and tools on an existing install, without converting to the `/etc` `/var` `/usr/share` structure
- [ ] B. import of a region from an OAR, or from a database backup and its config (the OSGrid case)

### Rules, from now on (not waiting for a release)

The whole ecosystem speaks standards, no part is left out (the helpers included), and no format is made for the need of the day:

- [ ] services belong to the grid, served by controllers
    - [x] what a grid says of itself is the engine's (`Grid::gridInfo()`: the running Robust first, its config when it does not answer); the helpers and the pages use it, the live values win over a config changed since
    - [x] every address of a grid in one call, wherever it is said (`Grid::addresses()`, `helpersUrl()`, `searchApi()`...: for the code, no endpoint): the running grid first, then the config, then where the helpers answer
    - [x] assets: `Asset` for every type, `Image` (every type of picture) converted for the web, resized when asked, and cached (`WebAssets`)
        - [ ] JPEG 2000 on the development machine: the ImageMagick of this Mac has no JPEG 2000 coder, the real conversion of a texture is tested where it has (the test image of the containers)
        - [ ] the other types as the web needs them (the text of a notecard, the source of a script), as classes that extend `Asset`
    - [ ] the block "Grid Info" of the helpers (name and login) keeps its label and takes a key of its own, so that it is not taken for the /get_grid_info of Robust
    - [x] `Service` model: `Grid::services()`, `Simulator::service('profile')` (its own config, else its grid's)
    - [x] a Robust is a controller (`RobustController`), so is a simulator (`OpenSimController`); `Grid::robusts()` lists the Robust instances of a grid
    - [ ] several Robust instances in a grid folder (`Robust.<role>.ini`), each with its own launcher name, its own connectors: two asset servers, a mixed local and external grid
    - [ ] the MoneyServer as a controller (`MoneyServerController`)
    - [ ] a service served somewhere else is declared with its address in the config of the grid, and the health page checks it
- [ ] the order of the work: the engine, then the helpers, then the installation scripts in pure bash (helpers and engine only, no other PHP); the PHP classes of the setup in the kit go
- [ ] the engine: OpenAPI contracts, JSON:API resources, RFC 9457 errors, PSR-7 messages (with PSR-15 and PSR-17), PSR-4 autoloading
    - [ ] one layer of use cases; the command line, the HTTP API and the PHP library are adapters of it
        - [x] a first generic slice: `Api` lists and reads the grids, the simulators and the services as JSON:API, the errors are `Problem` (RFC 9457)
        - [x] the other resources: avatars, regions, parcels, estates (`/grids/{grid}/...`), tasks, forms; the writes that exist (an avatar is made and changed, what is enabled is enabled)
        - [x] the health of a grid as an endpoint (`GET /grids/{grid}/health`)
        - [ ] backups and archives as resources
        - [ ] `Health` still has the function style of the helpers: classes
        - [x] the standard of the web services whose format is the engine's own (`GET /motd`, `GET /destinations`, `GET /text-images`, the tasks `search-parse` and `events-parse`): the handlers, and `Legacy` for the old parameters of `textgen.php`
        - [x] `register` (`GET /search/register`) and `offline` (`POST /offline-messages/SaveMessage/`, `RetrieveMessages/`) are of a fixed format, like `query`, `currency` and `landtool`: the engine serves them
        - [x] a grid with no guide has its popular places and its places shown in search (ten of each), as sections or as the lines the boards read
        - [x] the fixed ones (`query`, `currency`, `landtool`) are in the catalog of `Service` with their format fixed and their address configurable
        - [x] a PSR-7 / PSR-15 adapter (`Api\Handler`: the application brings the implementation of the messages and calls `terminate()` once the response is sent)
        - [x] the command line (`opensim api`) and the PHP library (`Api\Router`) as the other two adapters
        - [x] the API is closed: global API keys (`opensim api-key create|list|revoke`, `Authorization: Bearer`), what the viewers, simulators and visitors call is open, the contract says which and a test checks the router agrees; `docs/api/try.html` tries it with a key
        - [ ] keys restricted to categories, models or users (later: a global key is enough for now)
        - [ ] the keys as a resource of the API (made and revoked from the manager)
    - [ ] the OpenAPI document is the contract, checked against the code by tests
        - [x] `Contract::document()` for the first slice, checked against `detail()` of the models
        - [x] the endpoints the core and the viewers call (`query`, `register`, `offline`, `currency`, `landtool`, `get_grid_info`) and the pages of the helpers, described as endpoints of their own with their fixed format (`docs/api/`)
        - [x] the documentation is made from it (`composer docs`: `docs/api/`), a test fails when it is not the one of the contract
    - [x] what the core of OpenSimulator and the viewers call (`query.php`, `register.php`, `offline.php`, `currency.php`, `landtool.php`, `get_grid_info`...) keeps the format they expect: described in the contract as endpoints of their own, not reshaped
    - [ ] to bring to it first: `Collection::json()`, the errors of the helpers, the fields of the setup, the line protocol of `ScriptUi` (the questions of the setup become a resource of the API)
    - the engine is cleaned first, as a library on its own; its consumers (the helpers, the kit, the manager) are put in order after
        - [x] the classes and functions of the WordPress plugin that nothing here uses are gone (the plugin has its own copy of the engine)
        - [ ] `OSPDO` and `OpenSim_Health` (root files) into PSR-4 namespaces, the helpers follow
        - [ ] the `legacy/` aliases (`OpenSim_Config`, `OpenSim_Oar`) go, the helpers use `GridConfig` and `ConfigReader`
        - [ ] the global functions of `includes/` (`osDie`, `osXmlDie`, `osXmlResponse`, `currency_*`, `opensim_*`...) become namespaced code in `src/`, their errors problem details (RFC 9457)
        - [ ] what is the toolset's (`Provisioning`, `Console`, `Runtime`, `Archives`, `Testing`) and what is the library's (`Config`, `Data`, `Models`, `SignIn`, `Network`): decide where to cut, then the dependencies of each
        - [ ] the autoload is `psr-4` and `files` for what has to be global (`_()`, the xmlrpc polyfill, the constants), no `classmap` of root files
        - [x] one way to enable, disable and tell the state of a grid, a simulator, a region (no `GridState`, `SimState`, `RegionState` beside the models)
            - [x] `Enablable` (`isEnabled()`, `enable()`, `disable()`) for `Robust`, `Simulator` (both already have them from `Instance`), `Grid` (delegates to its Robust) and `Region`
            - [x] `GridState` and `SimState` go: their callers (setup, commands, backup, provisioners) ask the model
            - [x] a region has its file in its simulator (enabled while it ends in `.ini`) and its row in the grid (`Region`): one model, `Region::configured()` lists the files
        - [ ] one class per concept, whatever the source it is read from (the grid, the config, the search tables)
            - [x] `Region`
            - [x] `Simulator`: the config of a simulator and the instance that runs it are one class (ports, console, database and estate are asked of it, by the planners and the backups)
            - [x] `detail()` on `Grid` and `Simulator`, as `Region` has it
            - [x] a Robust tells what its own config says (names, ports, console, database, services, roles of regions), as a simulator does: the grid and the planner of a grid ask it
            - [x] each database is asked as itself: the one the service has in its section, else `[DatabaseService]`, as the core chooses it (checked against its source: the estates of Robust, the mute list...)
                - [x] the accounts, the registry of regions, the backups, the bans and the rows of the simulators ask the database of their service
                - [x] the regions of the search engine are read in the database of the search (`regionsregister` when it shares the one of Robust)
                - [ ] the account import asks the engine for both ends: `Avatar::all()` on the source server (a temporary config of its databases) gives the accounts, `Avatar::create()` makes them on the new one
                    - [ ] `Avatar::create()` writes each table in the database of its service (accounts, authentication, inventory, users) or goes through the console of Robust; no transaction across databases
                    - [ ] a list file (CSV, JSON) is another source of accounts for the same creation
                    - [ ] until then the list import is refused when these tables are not in one database
                - [ ] the helpers, the plugin and the currency functions choose their databases through the same registry (`CURRENCY_DB_*`, `db_for()`)
            - [ ] a grid has services, not a Robust: login, assets, inventory, profiles, groups, offline messages...
                - [ ] short term: two asset servers, the key to secure a grid open to the hypergrid
                - [ ] each service has a provider, here or elsewhere, and a grid mixes them (Robust, search and simulators on several servers: Speculoos runs on five or six)
                - [ ] `Grid::services()`, and `Grid::serves()` answered by the providers; the Robust that gives the identity of the grid (login, gatekeeper, economy, search) is not the only one
                - [ ] the database of a service comes from `Database` (Robust, Assets, Profiles, Offline...), not from one connection string: `Grid::databaseHost()` and the others assume a single Robust
                - [ ] the address of an external service is read where the simulators read it (`GridServerURI`, `AssetServerURI`... of their config) and in what the grid announces
            - [x] every model follows the contract (`ModelInterface`), lists with `all()` and finds with `find()`, the process being its own: `OpenSimModelInterface` for the ones read in the databases of a grid (given its config), `InstalledModelInterface` for the ones of the machine (given its environment): `Grid`, `Simulator`, `Robust`; `Grid::all()` gives a `Collection`
            - [x] `Grid`: `GridInfo` goes into it, whatever the source (its folder, a plan, a description, what it says at `get_grid_info`)
                - [ ] the other sources give the same properties: the xmlrpc calls of the hypergrid, the tables of OpenSimSearch, the console, the database
                - [ ] the helpers read a grid through `Grid`, not through their own functions
            - [ ] `Models` holds models only (`Instance`, `Robust`, `Core` and the plans go where they belong)
            - [x] `Estate`: its settings, managers, users, groups, regions and ban list, read from the estate database wherever the grid keeps it (the grid's, or each simulator's); a region knows its estate
            - [x] the bans leave `Moderation` (`BanPlan`, `BannedUser`, `UserBan` are gone: the command makes its files from the models)
                - [x] the ban list belongs to the estate (`Estate::bans()`, `isBanned()`, `banSql()`, `ban()`, `unban()`), the access list to the parcel
                - [x] who is banned is an `Avatar`, local or foreign (`Avatar::lookup()`, `visitor()`, `unknown()`)
                - [x] a ban on the whole grid is a ban in every estate of the grid (`Grid::estates()`)
                - [ ] the core has a slot for a ban list of the whole grid (`BansService` in `[GatekeeperService]`, interface `IBansService`, no implementation shipped): a later option
                - [x] deleting what a user left in the databases is the avatar's (`leftovers()`, `purgeSql()`, each thing in the database of its service), the statements stay for the administrator to review
            - [ ] `Simulator` and not `Sim` in what the user types or has already written: `--sims`, `opensim import sim`, the `sim` field of the region form
        - [x] a pseudo cron in the engine: the mechanics only, the consumers say what to schedule (`Scheduling\Scheduler`, `Task`)
            - [x] tasks with a name and an interval, run when due by whoever comes first: `tick()` after the answer of a request, `runDue()` from a real cron
            - [x] a task never runs twice at once (a lock), and a task that fails is told, not retried in a loop
            - [x] the last run and the next of each task are known (`status()`, files in the state folder), for the health page
            - [ ] the helpers register their tasks (the parser of the hosts, of the events) and call `tick()` / a cron script
            - [ ] the health page lists the tasks and their last run
    - the addresses follow the Robust config, not the other way round
        - `currency.php` and `landtool.php` are the `economy` address of `[GridInfoService]` (the helper URI) plus a name the viewers fix: only the prefix changes
        - `register.php`, `offline.php`, `query.php` are old habits, the default: the grid can say others in its `Robust.HG.ini`, and the helpers serve the addresses it says
        - `get_grid_info` is a service of Robust; a page that answers the same address is a convention of ours, which can be changed
        - [ ] so the contract of these endpoints is made from the config of the grid (the prefix and the names it says), not written once
- [ ] the helpers, the web setup and the mini website: a light framework, easy for a beginner to read, PSR-7 and PSR-15 (Slim 4; Tiny, `ranaroussi/tiny`, is not: no composer package, no namespace, no PSR, PHP 8.3 at least while the project runs on 8.2)
- [ ] the layouts: every HTML object has business classes (of its own, named for what it is), and the application that uses it decides the style
    - [ ] nothing written in place (no `style=`), no CSS imposed: the application maps the classes to Bootstrap, to Tailwind or to its own
    - [ ] the applications are a PHP micro-framework, WordPress, later Laravel
- [ ] installable as composer libraries, Debian packages, Homebrew formulas, or from a clone

### 3.0.0-beta.7 (target: before 10-18, for Speculoos and hal)

Bugs (found in use; no beta goes out with them):

Kit:

- [ ] `opensim start|restart` never rewrites the config
    - [ ] the config is written only after an install or an upgrade
    - [ ] and only with confirmation (as Debian does)
- [ ] Hypergrid setup complete: the manual fixes it took to get watta.world working (7 steps, `tmp/watta.world/etc/opensim/grids/watta_world`, `git log`) are done by the setup
    - [x] `ExternalHostNameForLSL` of a simulator on the machine of its grid is the host of the grid (it was the name of the machine, `watta`)
    - [x] `[GridInfoService] gatekeeper` is written for a hypergrid
    - [ ] [?] `Region_Welcome = "DefaultRegion, DefaultHGRegion, ..."` was missing in the Robust.HG.ini of the setup, though the setup file had the roles
        - the package scenario checks it and passes: find the build or the path (quick setup, web, import) that left it out
    - [ ] `FallbackRegion` for the first region (the fix added it)
    - [ ] no `127.0.0.1` in `CertHostIp` and `RobustCertHostIp`
    - connectors of Robust: a connector is served only with its counterpart in the simulators (the grid layer), in a grid the setup makes
        - [x] bakes: `BakedTextureService` and `[XBakes] URL`, by default
        - [x] estates: `EstateDataService` and `EstateDataRemoteConnector` (GridCommon.ini), the estates are the grid's, in its database
        - [x] groups: `GroupsServiceConnector` (with the helpers or a hypergrid) and `[Groups]` V2
        - [x] hypergrid groups: `HGGroupsServiceConnector` and the HG service connector of `[Groups]`
        - [x] profiles: `UserProfilesServiceConnector` and `[UserProfiles]`
        - [ ] estates of a grid that exists: move the estates of the simulators to the grid (until then the grid keeps them where they are)
        - [x] offline messages: the setup offers the helpers (module V1), Robust (module V2 and `OfflineIMServiceConnector`) or both; `opensim enable|disable <grid> offline [helpers|robust]`
        - [ ] the package scenario run with all this (the machine of the containers was not reachable)
    - [ ] map tiles: `GenerateMaptiles`, `MapImageModule`, `MaptileRefresh`, `TextureOnMapTile`, `DrawPrimOnMapTile`, `TexturePrims`
        - [ ] `MapImageModule = "Warp3DImageModule"` by default: it crashed on watta.world, find why (libgdiplus 6.1 of the distribution?) with a scenario that makes a tile
    - [ ] `OutboundDisallowForUserScripts` blocks the calls of the scripts to the local network (the helpers of the grid, the other machines of the operator)
    - [x] `AllowOSFunctions = true` in the `osslEnable.ini` of the grid (the setup made for Watta was older)
    - [ ] [?] `RegionType` in the region files (`Mainland`, `Private`...)
    - [x] a grid whose files do not say its name is named by the words of its nick ("Watta World"), not "Watta_world"
        - [ ] [?] find where the name was lost: a first install with the packages of the repository whose conf had no `GridName`?
    - [x] `helpers.ini` (first setups) and `Helpers.ini` side by side broke the clone on macOS (case-insensitive): the setup renames the first, or keeps it aside as `helpers.ini~` when both exist
        - [ ] the same check for every file the setup writes (two names that differ by the case only)
    - the web server snippet of watta.world (`handle` per route, imported from the site) is the model for "Caddy config compatible with an existing website" below
- [ ] no `127.0.0.1` left in the config (unusable beyond tests)
- [ ] options are ini files, included or combined: changing an option never needs a change in an install script
    - [ ] an option is a folder: its ini files, the packages it needs (apt), the DLLs it adds, its questions
    - [ ] options: OpenSimSearch, Gloebit, NPC, helpers, mini website (`opensim-web`)...
    - [ ] OSSL variants are options too
        - [ ] default / strict (the standard config enables the functions with the defaults of the core)
        - [ ] relaxed / NPC / animations (the default one refuses very common usages, e.g. NPC and animated objects)
        - [ ] the operator customizes them: which functions, for whom
- [ ] Caddy config compatible with an existing website
    - [ ] only the tiny website (`opensim-web`) is a fully handled website
    - [ ] the Caddy config is a snippet, updatable
    - [ ] its routes follow the URLs of the Robust ini (`/welcome`, `/search`, `/guide`...), even without handling `/*`
- [ ] `opensim start --verbose` or `--show-command`: show the command actually run, for a manual intervention in case of issues
    - a region created without a user needs a manual start to fix its owner and estate
- [ ] a simulator of a grid joined by a preset (OSGrid) cannot be configured: the folder of a preset has the structure of the folder of a grid, and takes what it takes
    - [x] everything that applies to all the simulators of a preset is set in the preset: its OpenSim.ini, config-include and OSSL are read by the master
    - [x] the simulators of a preset have a master, made like the one of a grid from the OpenSim.ini of the preset: a change there is taken at the next start of each
    - [x] `opensim enable|disable <grid> <feature>` on such a grid edits the OpenSim.ini of the preset, not each simulator's
    - [ ] the simulators made before keep their whole config until their next `opensim setup` (it gives them the master): move them with a command
    - [ ] the options of the setup (search, currency, OSSL...) for a simulator that joins a grid
    - [ ] try with a real simulator on OSGrid (only the unit tests cover it)
    - [ ] the presets of the kit are the base (read-only), the ones of the machine are the administrator's: say it in the documentation
- [ ] port mapping does not work from a local network
    - [ ] not included or enabled in the package as is
    - [ ] works from a local network (more work)

Helpers:

- [ ] the picks and the classifieds of an avatar made, changed and deleted from the web, as the viewer does (3.0.0: the edit page of a site lists them, read only)
- [ ] popular places block asks the search URL of the grid, never fetches the results itself
    - the search service can be external, it is even the recommended setup (2do directory)
- [ ] popular places block has the details and the layout of the w4os block (the reference)
- [ ] links in the pages shown in a viewer use the `TPLINK_HG` form (Firestorm does not handle `hop://` from inside a page, though the browser sends them to it)

The beta:

- [ ] state of the refactoring frozen as a coherent beta of the family (the Unreleased list below)
    - [ ] `opensim setup` is intuitive and says nothing superfluous: the same questions whoever shows them (the terminal, `setup.sh`)
        - [ ] first who runs OpenSim: the dedicated users `opensim` and `www-data` (recommended), or the person who runs the setup, for everything
        - [ ] then what to set up: one list, whether it makes a grid or joins one: grid, standalone, then the presets (Speculoos, OSGrid, the custom ones)
            - the engine does not treat them as presets yet (grid and standalone make, a preset joins): to align later, or not
        - [ ] then the basics, which depend on the choice
            - the rules, their messages and the corrections are the models' (Avatar, Region, Grid, Simulator): the setup, the API and the other apps ask their fields, none has a rule of its own
                - names are Latin letters without accents, in every alphabet: the model transliterates, the person validates the change
                    - why: the login box and the start location of the viewers take ASCII only, and Firestorm drops the accents of a destination and of the link in the chat; the console accepts them
                - Avatar: name of two words of letters, digits and hyphens, 63 characters at most each (the login box takes 128 for both); no `.` nor `@` (hypergrid: First.Last@grid), no `_` (taken for a space in many places of OpenSimulator), and the viewers take space, `.` and `_` for what separates the first name from the last
                - Avatar: password of 6 to 16 characters (6 is ours, to make configurable in opensim.conf; 16 is the password box of the login panel of the viewers), no double quote; email without space, quote nor backslash
                - Region: letters and digits, three words at most (Second Life, Linden Lab naming private regions), 3 characters at least (the map search of the viewers and of the core needs 3), 128 at most (what the database holds; Second Life has 25: not applied); no punctuation: the chat links of the viewers (`secondlife:///app/region/[A-Za-z0-9()_%]+`) stop at it, the map search of the core gives `. ! + | : %` a meaning, and Robust takes `_` for a space (Ma Region and Ma_Region would be the same `Region_Ma_Region`)
                - Parcel: name of 63 bytes at most and description of 255 (the land panel of the viewers; the core limits nothing, the database holds 255); an accent takes two bytes
                - Grid: letters, digits, space, `.`, `-` and `'`; the grid of a standalone gives its simulator and its region its name with the dots as spaces (a region has no dot), and that name follows the rule of a region
                - login URI: with or without the scheme (http only), host in lower case, ASCII only (the viewers refuse any other character in the address of a grid; the punycode conversion will be looked at if someone needs it); web URL http or https
                - source: the code of Firestorm (`phoenix-firestorm`: `panel_login.xml`, `llpanellogin.cpp`, `llurlentry.cpp`, `fsgridhandler.cpp`, `floater_about_land.xml`) and `MapSearchModule.cs` of the core
            - [ ] grid: name of the grid, login URI, Hypergrid (yes), avatar (name, password, email)
            - [ ] standalone: name of the simulator (it is the grid), login URI, Hypergrid (yes), avatar (name, password, email)
            - [ ] preset: name of the simulator, login URI and Hypergrid shown but read-only (the preset gives them), the avatar that owns its estate there (it exists)
                - later: its password, to check the avatar is theirs (to think over: who may do it, the admin or not)
        - [ ] then the options, one screen of check boxes: SSL, Hypergrid, Cross-grid Search, Economy: Gloebit, Extended OSSL (the engine lists them, with what it does not apply yet)
            - the modules are named, not their function: Gloebit is one money module among others, OpenSimSearch one search module
            - everything else is on in a quick setup: the helpers, the OpenSimSearch module, the offline messages, the profiles, the destination guide, the grid status
            - cross-grid search on: the URLs are the ones of 2do.directory; off: the ones of the domain of the grid
            - the report to confirm lists all of it, the chosen and the on by default, in the form (it scrolls)
        - [ ] advanced config: the helpers are a choice, none | provide | external
            - choosing Gloebit or the OpenSimSearch module disables none, the next one is chosen
            - a screen of settings for the cross-grid search (external: 2do.directory by default, with its query and web search URLs) and one for Gloebit (the defaults of the grid until advanced is on)
        - [x] a Simulator that joins another grid and the presets are in the first screen of the setup and work
            - the presets are in `share/presets` of the kit: the engine, run by the CLI, does not find them (the application says where its root is, see `ResourceRegistry`)
        - [ ] the options of the setup are defined in one place (`QuickSetup`), what reads the config does not know them
            - today an option is also in the fields of `Grid`, the plan, the planner, the setup file and the provisioner: changing the type of one touches about thirty places
        - [ ] then quick setup (the defaults for the rest) or advanced config (every question, the answers proposed)
            - [x] the quick setup is the scenario of the engine (`POST /setups/steps`, `QuickSetup::next`): the screens, the checks, the corrections, Back and Quit; a client draws a screen, sends it back filled and does the action it gets
            - [x] Install checks and makes the databases (the administrator of the server is asked only when the setup cannot reach it), writes the grid and its first simulator, starts them and makes the account of the avatar; tried for real on a grid made and removed (Robust and OpenSim up, region ready, account made)
            - [x] the assets and the library of the core are copied into the data folder of the grid (`var`) and the config of Robust and of the simulators reads them there
            - [x] a simulator can join the grid of a preset (Speculoos, OSGrid: shipped with the engine in `share/presets`) or a grid given by its address; tried with stand-ins, not against a real grid
            - [ ] the presets are still in `share/presets` of the kit too: one copy to remove
            - [ ] the .NET MySQL connector failed once to authenticate over SSL on the local server at the first start of Robust (an I/O error), then worked: to watch, `SslMode` of the connection string if it comes back
            - [ ] SSL is not implemented (3.1); Cross-grid Search, Gloebit and Extended OSSL write their files, Gloebit still needs its keys (the settings screen)
            - [x] each option is a preset, one place for all their settings: a folder with an `option.conf` and the ini files it changes, laid over the files of the grid that have the same name (`GridOptions`, `OptionTemplate`)
                - [x] `share/presets/<name>/` for the ones of the kit, `<etc>/presets/<name>/` for the administrator's (wins); a new folder is a new option, offered by the setup when it says `Offer = true`
                - [x] shipped: search, helpers (what the viewers are told), bakes, groups (four variants), profiles, offline-helpers, offline-robust, economy-beta, economy-gloebit, ossl-extended
                - [x] the ones that are not listed in the setup are the base of the grid (`Offer = false`), applied when the grid can have them, the same mechanism
                - [x] offline messages: the module V2 is the base and the default (`Fallback = true`), the helpers (V1) take its place when they are asked; never both
                - [ ] the offline messages reviewed (V2 works on speculoos but for the mail relay, which is not implemented): then the default is chosen again
                - [x] at the setup and later (`opensim enable|disable <grid> <option>`); a group has one option at a time; disabling comments the settings out; the options of a grid are in its description (`Options`)
                - [x] the dependencies are in the `option.conf`: modules (OpenSimSearch, Gloebit), the helpers, the settings to give (the Gloebit keys)
                - [ ] a module added to `EnabledModules` after the first install (with the option that needs it, or a command of its own): today it is written only the first time (`PackagedProfiles`), so the operator edits `opensim.conf` by hand (OpenSimSearch on ursull, 2026-11-16)
                - [ ] the visibility of a profile, after 3.0.0 (3.0.0: a new avatar is public): the same on the web site, the profile page the viewer streams and the search of the viewer, compared with Second Life, the reference
                    - the viewer shows "Show in search" unchecked when a published profile is opened again, and the flag changes only after several saves (Olivier, 2026-11-16, Bart Simpson); what the code says (read 2026-11-16, no fix before 3.0.0):
                        - one bit only, bit 0 of `UserFlags`: the viewer's `AVATAR_ALLOW_PUBLISH = 0x1` (`AVATAR_MATURE_PUBLISH = 0x2`, Firestorm `llavatarpropertiesprocessor.h:37`); the core sends `UserFlags & 0xff` as the flags of the profile (`UserProfileModule.cs:136`); our OpenSimSearch sends `allow_publish` (`OpenSearch.cs:155`) and the engine writes bit 0 alone (`SearchQuery::publish()`). This bit is the "robots" one of the OpenSim documentation (below). The core also keeps `PublishProfile` in `userprofile.profileAllowPublish`, which nothing reads for the viewer
                        - two caches keep the old value: the account cache of the simulator (our module removes the account after its write) and the cache of the profiles of the core module, which keeps the `flags` of its last answer (`UserProfileModule.cs` ~173, `uce.flags`, until `PROFILECACHEEXPIRE`); when the core module handles the update before ours, it answers from the account as it was, and that answer is kept
                        - the clean fix: write the bit through the user account service (`StoreUserAccount`, its cache follows; Robust needs `AllowSetAccount` for a simulator), or have the core profile module keep `PublishProfile` in `UserFlags` itself, and forget its own cache of the flags
                        - "cannot save when only the box changed" is the viewer's: ticking the box sends it at once through the `AgentProfile` capability (Firestorm `llpanelprofile.cpp`, `onShowInSearchCallback()`), which only Second Life has; on OpenSim nothing is sent then, the box goes with the next Save (`apply()`, UDP), and Save waits for another change. Whether the grid should offer that capability is to decide
                        - one bit, two meanings in the documentations: the OpenSim wiki (`UserAccounts`) gives bits 0-7 of `UserFlags` as flags, 1 the robots bit, "allow external services like Google to index the user profile", 2 mature, bits 8-11 the account type, 12-15 unused (our `PENDING_FLAG`, 1 << 12, is there); the same bit is the viewer's `AVATAR_ALLOW_PUBLISH`, "Show in search", which the documentation of Second Life tells of its own search results in the world. The bit we write is the right one, what it means is to untangle after 3.0.0 (Olivier):
                            - what the box controls in Second Life today: its search in the world, the web profile, the indexing by external engines
                            - whether Second Life has, or had, a setting of its own for the indexing by the web
                            - what each of ours follows: the web profile, the search of people of the helpers, a robots meta tag on the profile page
                        - Second Life has a second place for the same choice, Preferences > Privacy > General > "Show my profile info in Search results"; Firestorm keeps it and the box of the profile in step, and both apply at once, without saving the profile (a choice of the UI, not a bug); the box of the profile shows ticked a moment after it opens: Firestorm asks the server when the profile opens
                        - both send at once through the `AgentProfile` capability only (the preferences: `saveAvatarProperties()` in Firestorm `llfloaterpreference.cpp`, an HTTP PUT of `allow_publish`), which OpenSimulator does not offer: on OpenSim neither sends anything when the box is ticked, the profile's goes with the next Save (UDP `AvatarPropertiesUpdate`, which our OpenSimSearch handles and removes the account from the cache), the preferences' never goes. `UpdateUserInfo` carries the IM to email and the visibility of the online status (`DirectoryVisibility`), not this. To decide: the grid offers `AgentProfile` (the change goes at once, the caches follow), or the viewer is told otherwise
                        - Second Life, to check (Olivier is not sure of his source): with the box off, the search of Second Life still shows the name, the picture and the maturity of the profile; this is its own search, not the external engines
                    - what each place shows of a profile that is not public, and in which words (the helpers say "This profile is not public.")
                    - whether the viewers of today still show the Interests tab (wants, skills, languages) for OpenSim grids: Firestorm, and the SL viewer since the profiles it redesigned in 2023 (Olivier does not find these fields in his viewer any more)
                        - Olivier's Firestorm for OpenSim (2026-11-16) has the tabs Vie SL, Flux, Favoris, Annonces, Vie RL, Notes: no Interests
                        - the viewers no longer show it since 2023 (the sites say so, `interests_removed`); it stays a feature of w4os, which the manager of a grid can turn off (later: for 3.0.0 the note only, Olivier)
                - [x] `Helpers.ini` and the files of the web server (Caddy, nginx, Apache) are presets too: copied with their variables when the grid has none, so the text to change is in the presets folder
                - [x] everything the setup writes is a preset, the code collects the choices and puts the values in: the description of the grid, the config of Robust (names, hypergrid, connectors, log), the corrections of the folders copied from the core, the simulator and its region, the master of the simulators, a grid that runs elsewhere
                - [ ] the presets of an administrator tried on a real install (enable at the setup, enable later, disable)
                - [ ] the settings screens: the Gloebit keys, the search provider
        - [ ] the report at the end tells how to reach the engine from another machine: create an API key (`opensim api-key create <label>`)
        - the engine knows how to configure: it provides the questions and processes the answers (one source of truth, also for w4os, mini-web, the manager)
        - `setup.sh` shows the questions with `dialog` or plain prompts (`libexec/setup-frontend.sh`), the engine gives them through two pipes (internal: nothing to explain to the person)
        - [x] the scenario of packages that goes through the setup with the script (`wizardscript-grid-scenario.sh`): a grid made from the first question to a running region, with the real Hub
        - [ ] the assets and the library of the core are copied for the grid when it has none or when they are empty, and the config points to them
            - why: the library shared with the users (a later release) is added to these copies, the core stays untouched
            - where: `var`, not `etc`: it grows, and the engine will maintain it (rules to follow, references to carry over)
            - assets: only a Robust serves them, no override in the simulators; Robust says where they are in `AssetLoaderArgs` of `[AssetService]` (`./assets/AssetSets.xml`)
            - library: Robust (`[LibraryService]` `DefaultLibrary`, `./inventory/Libraries.xml`) gives the library skeleton at login; the simulator (the same section in the config-include of its architecture, with `LibraryModule = true`) serves its content to the viewer; both read their own, they must agree (to settle: the simulators of another server need the same files, or the library module off)
            - `./` is the folder the program is launched from (the `bin` of the core), the copies need absolute paths
            - [ ] test the library with three different versions: one for Robust, one for each of two simulators on separate servers
                - what the viewer shows at login (the skeleton comes from Robust) and after a teleport from one simulator to the other (the content comes from the simulator it is in)
                - what a simulator that is not ours does when its library is not Robust's (a grid with guest simulators)
        - [ ] the options are ini files merged or included (see above), the same whoever asks
    - parked: the setup in the browser (`opensim setup --web`), it comes back later and changes
    - [ ] `opensim import <setup file> --apply` full setup from YAML file, with no questions
    - [ ] `lifecycle` start / stop / restart / status of instances
    - [ ] `package`: release as .deb on apt repo and as ready to use .zip (no composer install required)
- [ ] simplified procedure to move a region from OSGrid
    - [ ] written documentation: for manual operation by region owner
    - [ ] through admin tools below
    - [ ] through user-end form
    - [ ] a region that exists already on the grid (Grand Place of speculoos.world, a region registered at osgrid.org) is not refused by the wizard
        - [ ] take its UUID, position and size from the grid (Robust through the private port, or `link_region` of the gatekeeper)
        - [ ] write them in the region file, do not create it
        - `opensim load oar` already restores the content
    - [ ] check a simulator of the kit against the development OpenSimulator OSGrid runs (commit `ef8a36b`, 2026-04-17, 0.9.3.1) before announcing it
        - the unstable core of the kit is newer (`04ca1d9`, 2026-09-26)
        - their OpenSimSearch (`modules-dotnet8`) is the source our fork started from, without a change
- [ ] admin import tools
    - [ ] add region owner by regular avatar (not grid owner)
- hal: one simulator migrated per beta, the others are tests
    - the problem met was probably config and compatibility, not investigated apart from the new installer
    - [ ] one more simulator migrated with beta.7
    - [ ] importing a simulator from its database without an OAR: `opensim import` of a simulator (config and data)
        - [ ] from its host (reference: `install/transfer-remote-simulator.sh`)
            - [ ] connect to the host
            - [ ] fetch the config and the data
            - [ ] make the simulator here: an OAR when it can be reached, the database only when it cannot
    - [ ] `opensim restore`

### 3.0.0-beta.8 (safe for other grids)

- [ ] Mac: all dependencies check and Homebrew receipts

Tests:

- [ ] the 10 test files that name the SQLite driver in their code take their database from `tests/.env` (`test_pdo()`), like the newer ones
    - not clean, not blocking

Resources/CPU usage protection:

- [ ] Refuse to try starting a new simulator if CPU usage is critical
- [ ] Log error and send mail notification when global CPU usage is critical, or when specific simulator usage is abnormal

Security, for everything that is hosted for others:

- [ ] Grief/Attacks detection
    - [ ] frequency of asset creation: count the assets an account creates over a period (an account can have thousands of assets for good reasons)
    - [ ] threshold per grid, simulator, region, owner, avatar, asset type
    - [ ] report
    - [ ] automatic actions (notify, suspend until review, ban...)
- [ ] `Guide::read()` of the helpers takes any file or URL (w4os fixed the same SSRF in `fb665fcf`)
    - [ ] ignore the `source` of the request, or accept only URLs

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
- [ ] the private ports to be allowed by the grid (speculoos.world and osgrid.org have them open for now: the servers of the simulators will have to be registered)

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
- the grid imposes its asset service on the simulators (a fact: it changes the strategies, not the code)
- other services: not separated unless the analysis says so
- [ ] two grids that share the map and the users and keep their asset service apart (even their inventory)
    - example: a main grid (`mygrid.org:8002`) with a restricted private port, internal regions only; a public grid (`guests.mygrid.org:8002` or `mygrid.org:8005`) with an open private port, for the simulators of others
    - [ ] what the two grids share (map, users) and what they keep apart (assets, inventory)
    - [ ] how the services of each grid are told apart (ports, Robust instances, databases)
    - [ ] what a visitor of the public grid can reach of the main one
    - the core has a setting for it: `[HGInventoryAccessModule] CheckSeparateAssets` and `RegionHGAssetServerURI` (GridCommon.ini): grids share user services, inventory included, and keep the assets of the regions apart from those of the users (transfer between the two in the manner of the hypergrid)
- [ ] authentication of the private port
    - [ ] how the protocol works (it seems to be one user/key pair for the grid, not one per authorized party)
        - Robust: `[Network] AuthType = "BasicHttpAuthentication"`, `HttpAuthUsername`, `HttpAuthPassword`; a service section can say `AuthType = "None"`
    - [ ] whether it can protect the external simulators (one key per simulator or per operator)

Import and restore:

- [ ] full import procedure for existing grids/simulators
    - [ ] database
    - [ ] assets
    - [ ] other data
    - first use: importing a region from OSGrid to another grid (local or a preset): OAR saved from the sim of its owner, region made here, OAR loaded
    - OSGrid closes on 2026-12-31 (the OSGrid preset is obsolete, kept for now)
- [ ] `opensim import` tests suite
    - [ ] users (tried with viewer login)
    - [ ] inventory
    - [ ] default outfit
    - [ ] numbers of the system folders (`Settings` 56, `Material` 57) checked against the core
- [ ] try `opensim import` on real grids: what works, what is missing
    - [ ] the config of a grid
    - [ ] simulator config (from existing grid)
    - [ ] setup file (from previous setup execution)
    - [ ] list of accounts (bulk import)
- [ ] try live: `opensim restore` of a backup on a new machine
    - [ ] the install
    - [ ] the databases
    - [ ] the data
    - [ ] check what the archive holds
- [x] try live: `opensim save oar` for every region (cron backup)
    - [x] oar backup rotation (`--keep N`)
- [ ] OAR of a region that belongs to a user who is not the owner of the grid (what the owners of regions can ask for themselves)

Checks on real installs:

- [ ] helpers confirmed on the real sites
    - [ ] the 500 of the web: what does the error log say
    - [ ] search with a viewer on vonda
        - [ ] places
        - [ ] land for sale (nothing is for sale there, the list is empty)
        - [ ] classifieds of the profile
        - [ ] a profile edit kept
    - [ ] offline messages
- [ ] "show in search" box of the profile (OpenSimSearch 0.5 beta)
    - OpenSimulator ignores it (`UpdateAvatarProperties` in `MySQLUserProfilesData` never writes `profileAllowPublish`)
    - the module tells it to the helpers with the secure session of the avatar (`avatar_publish_update`)
    - [ ] test with Firestorm on vonda
    - [ ] when OpenSimulator keeps the box itself (patch to submit): the module must check it before it applies the change
    - the DLLs are in `contrib/OpenSimSearch` until a release holds them
- [ ] `opensim stop` on a simulator with an NPC: confirm with a real NPC (only the format was checked on a real core; it does not seem to skip the NPCs)
- [ ] libgdiplus on Linux: what the regions do with the 6.1 of the distributions
    - the wiki advises 6.0.5 (mono repository, with `libtiff5`, held) and says to avoid 6.1 (broken or leaking memory)
    - 6.1+dfsg is the distributions' (Ubuntu), so the one our packages depend on
    - [ ] check what the regions do with it
    - [ ] decide what the packages should say and do

### 3.0.0 (stable; CLI, Debian packages, web app)

Kit, setup and configuration:

- [ ] setup: work from regions, not from grid/sims (a design flaw)
    - from the user's point of view: a grid, with regions
    - simulators are a pool of servers providing the regions
    - [ ] setup proposes creating regions first, with the choice of a new simulator or one from the pool
    - [ ] command-line tools allow both sims and regions as second argument (the other commands)
- [ ] configuration of a simulator
    - [ ] master generated per grid (`OpenSimGridDefaults.ini`, on top of `OpenSimKitDefaults.ini` of the kit), given with `-inimaster`
    - [ ] the simulator's own ini holds only what differs
    - [ ] conversion of the grids made the old way: part of the import
- [ ] the settings of a grid are in the config of each of its simulators (`[Search]`, `[DataSnapshot]`, `[Messaging]`, `[UserProfiles]`, the includes of the architecture...)
    - the sim.ini must hold only what is the simulator's own (as the sims of speculoos: [Launch], its port, its database)
    - the grid's OpenSim.ini holds what is common
    - OpenSimulator (0.7.5, 0.9.2.2 and 0.9.3.0 alike) takes, in this order: `-inimaster` (default OpenSimDefaults.ini, replaced when given), `-inifile`, the files they include (which win over the config), the files of `-inidirectory` (the last, over everything)
    - [ ] decide how the grid's OpenSim.ini and the sim.ini take these places, without the defaults being lost
    - [ ] read the configuration loader of OpenSimulator (the code of the core): the real order in which an ini file, the files it includes and OpenSimDefaults.ini are merged
- [ ] the setup merges the distribution into the files of a grid (new settings are taken)
    - installing a package adds missing files only (MoneyServer.ini for now)
    - a new release of the distribution does not reach the files of existing grids until `opensim setup` configures them
    - [ ] decide whether the package should merge too
    - [ ] decide what to do of the comments an operator wrote in a file that is merged
- [ ] kit: `InstallConfig` (opensim.conf) on the one reader of ini files
- [ ] operational prompts of `PresetUi` (enable, start, database administrator) as options of the calls
- [ ] `screen` is not a dependency (to review with the whole setup)
    - without it the consoles are the REST ones only (the default anyway)
    - today `Installer` requires it and `setup.sh` installs it
- [ ] standalone: one simulator with its own services (no Robust), made by the setup like a grid
    - [x] the engine knows it: `Standalone = true` in `<nick>.conf`, `Grid::isStandalone()`, run through its simulator, its services served by it
    - [x] made by the setup: the folder, `<nick>.conf` (database included), the simulator with `Include-Architecture`, `StandaloneCommon.ini`, tested on a container with the core (`tests/Packaging/standalone-check.sh`)
    - [x] the architecture of the core for it (`StandaloneHypergrid.ini`), its database (the one of the grid), its owner (made by OpenSim at its first start), its estate, its roles
    - [x] a second simulator is refused
    - [x] the helpers and the options as for a grid: economy, search, profile, motd, guide, welcome and gatekeeper in the `StandaloneCommon.ini` of the grid (`GridServices`, the same code as `RobustConfig`), `opensim enable|disable` search, economy, profiles, offline write there
    - [x] groups and offline messages (module V2) kept by the simulator itself, no address to give
    - [x] the questions: `standalone` is a field of the grid, what a Robust only has (private port, console of the grid) is not asked, nor the port and the database of its simulator, `offline` refuses `both`, a file with two simulators is refused
    - [ ] the flows (terminal, `setup.sh`) ask it
    - [x] `opensim start|stop` of a standalone by the name of the grid, and its monitor (its regions are the ones its simulator describes), tried on the container
- [x] offline messages on a grid without helpers
    - the choice is in the setup (`offline`), with what each gives: helpers (mails on request, no limit) / V2 module with the Robust service (no mails, 25 messages per avatar) / both
    - [ ] test with a viewer on each
- [ ] old global names of the engine as aliases (`legacy/`), helpers and w4os on the new names
- [ ] test the clone mode of `setup.sh`

The engine gives the data (the manager, the plugin and the CLI only show it):

- [ ] standard model contract: `OpenSimModelInterface`
    - [ ] column names mapped to the standard names, per model
    - [ ] relations
        - [ ] grid: simulators, regions, places, avatars
        - [ ] simulator: regions, places, current users
        - [ ] region: places
        - [ ] avatar: places, regions, grids (ownership)
- [ ] constants for flags: the bitwise value, named as in the OpenSim doc or code (and OSSL/LSL when it means the same)
    - in the model class (`Region::FALLBACK_REGION`); global only if a need shows up
    - false friends: the flags of `llGetRegionFlags()` are other flags
    - [ ] user
    - [ ] `FOR_SALE` and `SHOW_DIRECTORY` of the parcel are the protocol's: to confirm in libomv
- [ ] models: grid, simulator, region, parcel, avatar
    - [ ] parcel: search goes through the API of `query.php` (same base as in-world), never its tables
    - [ ] parcel: index of the parcels of all simulators, to list without asking each simulator (later)
- [ ] legacy classes (`OpenSim_Avatar`, `OpenSim_Region`, `OpenSim_Service`) folded into the models, aliases in `legacy/`
- [ ] `OpenSim_Config` and `OpenSim_Ini` (the wizard of the plugin) made one reader
    - the machine's grids (`opensim.conf`, `/etc/opensim/grids`, `OPENSIM_GRID`) are still found by the helpers
- [ ] function that reads the config aligned on Laravel's `config()`, `get_option` kept as an adapter for w4os
- [ ] avatars list
    - [ ] filters by type (user, model, technical accounts): active, online and the account of the services are done, models are not
- [ ] regions list
    - [ ] owner, simulator, presence, control, internal port, size, last seen (owner, port, size and last seen are done)
    - [ ] filters by owner
- [ ] simulators list
    - [ ] state (from the files and the processes), last seen
- [ ] grids list
- [ ] grid info, grid status
- [ ] record details (avatar, parcel, region, simulator)
- [ ] stats that leave out models, technical accounts
- [ ] other grids visitors are not excluded, they are counted appart (local residents only vs all visitors)
- [ ] a grid on another machine: config and database credentials read through the console of its Robust (as w4os 2.9.1 and speculoos do)
    - to confirm: needed for the Speculoos site if it is not on the machine of the Robust
- [ ] helpers use it
    - [ ] search of places, land and events (through the API of `query.php`, not the tables)
    - [ ] currency and economy functions of the engine (`functions-economy.php`) and the legacy classes
- [ ] the engine has its own `OpenSim_HypEvents`, with the faults the helpers had: which one is the source?

What w4os has and the suite lacks (`W4OS-PARITY.md`):

- [x] web assets server: serve `/assets/<uuid>.<format>`
    - [x] textures of the grid converted for the web, read from the asset service of Robust
    - [x] a cache
- [x] picture of the avatar profile (needs the web assets)
- [x] avatar models: the rule that makes a model (a name pattern, first or last name, or a fixed list of avatars)
    - [x] list to choose from at registration, with name and profile picture
    - [x] copy of the model's appearance and inventory into a new avatar
- [x] rules of the avatar names: accents out, letters and digits only, first letter capital
    - [x] restricted names (Default, Test, Admin, the model pattern)
    - [x] a taken name is refused
- [x] changing the password of an avatar
- [x] new-users block
- [x] translations w4os already has (cy, de, fr, it, nl, pt_BR) carried to the engine and the helpers for the strings that move (the new ones are translated as they are written)
- [ ] payments, conditional actions in the engine: restrict some actions of features based on conditions (payment, role...)
    - [ ] usable by consumer apps like w4os or the manager app
    - [ ] calling the engine's actions when a payment comes in or stops
- [ ] selling the grid's services: the catalogue of what can be sold
    - [ ] actions an order triggers in the engine (make a region, set its limits, suspend, restore), by hand at first

Helpers:

- [ ] the guide: style, script and picture of the guide and style of the directory page are linked with the path of the files (`/usr/share/opensim-helpers/...`)
    - [ ] the router should serve them
- [ ] an empty calendar keeps the old events (`eventsparser.php` takes `[]` for an invalid answer)
- [ ] without a money server they write `transactions` with other columns (`sourceId`, `destId`...) than with one (`sender`, `receiver`...)
    - [ ] check which module makes which
    - Gloebit makes neither (its tables: `GloebitUsers`, `GloebitTransactions`, `GloebitSubscriptions`): for the MoneyServer review
- [ ] still to port from the references (speculoos, 2do, w4os.org); nothing of the economy or of the scripts of the root is missing
    - [ ] `TROUBLESHOOTING.md` and `INSTALLATION.md` (the text, for the README and the kit)
    - [ ] `class-helpers-migration-2to3.php` (the move of the old `config.php`, or `opensim import`)
    - [ ] HYPEvents client class of speculoos (`class-helpers-hypevents.php`) against `events.php`
    - [ ] checks of the install wizard (`is_robust_ini_file`, `robust_test_config`, `getTestCurrencyQuoteParams`) in `opensim import` and `opensim web check`
    - the WordPress glue (`wp-load.php`, `config.wp.php`) stays in the w4os plugin

Documentation:

- [ ] engine API, for someone who integrates the suite in another framework
- [ ] helpers API, the same
- [ ] w4os bridge as its proof: what a new integration needs, in a page
- [ ] a short section in the README of opensim-helpers (and of the engine) on how to use them with the OpenSim kit

w4os plugin, once the features above are covered by the engine and the helpers:

- [ ] port all features to the included 3.0 engine and helpers, leave only to the plugin what truly belongs to WordPress integration/bridge:
    - [ ] avatar creation and the avatar models
    - [ ] avatar profile and the web assets
    - [ ] lists of avatars and regions
    - [ ] grid info, grid status, popular places and web search contents
    - [ ] search, the destinations guide, the offline messages
    - [ ] currency
    - [ ] status of the grid and the checks of its URLs
    - [ ] authentication with the credentials of an avatar
- [ ] plugin uses the engine, instead of its own queries
- [ ] migration of the settings of a 2.x install (WordPress options, `config.php`) to the settings of the engine; the helpers still read `config.php` as before
- [ ] check if new version is still compliant with WP repository requirements, otherwise publish a last version with notice to new hosting

### OpenSim Manager (a track of its own: it can stay a beta, 3.0.0 does not wait for it)

- the manager can stay a beta for the author's own use, and ship when mature with 3.0.x or 3.1
- the management interface is rethought here, to be reapplied to w4os and to the CLI
- `opensim-admin`: the complete admin interface (web), same procedures as the CLI; the architecture is being decided (engine actions, front-ends as adapters)
- the web interface for what needs authentication (admin, registration, profile editing, servers) is a layer above the helpers, which give everything public: after the helpers
    - [ ] name: "OpenSim Maui" (Management Admin and User Interface) is a candidate, to think over (.NET MAUI exists; no OpenSim project with this name found)

- [ ] uses the engine instead of its own models
    - [ ] simulators and grids from the engine models
    - [ ] parcels list
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

Features:

- [ ] world maps (navigable map on the web)
- [ ] avatar picker
- [ ] user inventory management (provided by engine, for UI), provided ready to display as a tree (folders, subfolders, items...)
- [ ] shared library (pick folders from grid owners inventory and add to grid library)
    - [ ] `install/iar2library`, `install/inv2library`: fill the common library that is in the inventory of the avatars
        - keep the logic
        - [ ] adapt as commands of the application (they still load the gone bash helpers)
    - [ ] parcel naming object in the library of the grid
        - the object is the script of `share/ossl-scripts` that names the parcel after the region
        - [ ] part of the library of the grid too: the original stays available to the users, a copy is rezzed in each new region
        - [ ] the operator chooses what a new region gets at its start (name of the parcel, music, media, flags): the script is the place for it
- [ ] sections of the search database (places, events, classifieds)
- [ ] finer rights of the members of a grid
- [ ] registration types of a grid
    - [ ] free and open: everyone can register
    - [ ] by invitation
        - [ ] by any member
        - [ ] reserved to some categories of members
    - [ ] subscription
        - [ ] linked to a payment module
        - [ ] linked to a third-party service
- [ ] delete a region
    - checks: users in it, neighbours, estate, parcels and objects
    - [ ] backup first
    - [ ] registry of the grid cleaned
    - [ ] registry of the search cleaned
    - today: the console (`delete-region`), its file and a row of the grid database, by hand
- [ ] ready to use default avatars
- [ ] default Inventory package
- [ ] nat/port forwarding instructions or presets (with standard tools or third-party provides like ngrok, cloudflare...)
- [ ] test a simulator behind a firewall or on a VPS with `ResolveAddress = True`
    - the console says it resolves the hostname to an IP once, at start (it exists for Docker), it is not a DNS question at each request
    - [ ] which setups it makes possible (a simulator whose public name is a host name, behind NAT or on a VPS)
- [ ] opensim tools installation-agnostic (kit installs and custom/standard OpenSim installs)
    - `/etc/opensim/opensim.conf` becomes only a reference pointing to the user's actual core installation and configuration files location (e.g. `/opt/osgrid`, `~/diva`, `~/opensim/opensim-0.9.3.0/bin`...)
    - [ ] a grid follows the profile of its own install, the default profile is only the default of the new grids
    - [ ] the system user to run the instances as is per profile, not only `SystemUser` of `[Defaults]`
    - [ ] the setup offers to register an install (the core menu)
    - [ ] no script assumes `/etc/opensim`, `/var/lib/opensim` or `/usr/share/opensim` (review, one fix per file, with a test)

Installation:

- [ ] SSL management for the OpenSim servers (0.9.3 supports it)
    - simulator: `http_listener_ssl` and a PKCS12 certificate; `EnableCertConverter` makes it from the PEM files of the CA (`fullchain.pem`, `privkey.pem`) at each start
        - added on 2024-09-16 (commit `52c77b2f12` of the core, in `OpenSimBase.cs`, so the simulator only): 0.9.3.0 has it, 0.9.2 does not, there the `.p12` is made with openssl
    - Robust: `[Network] https_listener`, `https_port`, `https_main`, `cert_path` and `cert_pass` (a PKCS12), no converter for the certificate of a CA
        - [ ] the engine makes the `.p12` for every version and every instance, Robust and simulators (no instance is started to convert): one code path, `EnableCertConverter` stays off [?]
        - [ ] test that the .NET of each core loads the `.p12` (openssl 3 changed the default encryption)
        - [ ] test whether a running instance serves a new `.p12` without a restart: that decides between the converter of 0.9.3 and ours (the code says no: `BaseHttpServer` loads the certificate when the server is made and gives it to the listener at start, the converter only runs at start)
        - [ ] one certificate for all the hostnames of the grid (several names, or a wildcard through DNS-01), so that the `.p12` is shared
    - changes the `BaseURL`, the URI of the hypergrid, the login URL and the behavior of Robust of a grid that exists: for 3.1, easy only for a new grid
        - [ ] the old `http://` login URI of the viewers
            - `GatekeeperURIAlias` and `HomeURIAlias` (given in get_grid_info as `gatekeeperalias` and `homealias`) are for the hypergrid side, the gatekeeper knows itself by them (`GridInfo.cs`, `GatekeeperService.cs`); whether they ignore the scheme: not read
            - they do nothing for a viewer whose stored login URI is `http://host:8002/`: with `https_main` the main port answers https only, the viewer cannot even fetch get_grid_info there, refreshing does not help
            - [ ] keep http on the old port and add https beside it (`https_listener`, `https_port`): does that second server serve the services of Robust? handlers are registered per port, not read
            - [ ] test with a viewer still on the old URI, without refreshing the grid info
    - [ ] our own certificate from certbot, never the one of the web server (Caddy keeps it elsewhere than certbot): `--webroot` with the aliases wanted, `--allow-subset-of-names` so that an alias that cannot be verified does not fail the others
    - [ ] `ExternalHostName` of the regions: `SYSTEMIP` is the recommended value (a name forces DNS lookups, slower), but with `http_listener_ssl` the core checks it against the certificate for each region and refuses the region otherwise (`OpenSimBase.cs`, `BaseHttpServer.CheckSSLCertHost`)
        - the check compares an IPv4 address with the DNS names and the CN of the certificate, never with its IP addresses (read for IPv6 only): a named certificate refuses `SYSTEMIP`, and an IP certificate would not pass either (reading of the code, to confirm by a test)
        - [ ] decide: simulators over https with the hostname as `ExternalHostName`, or only Robust over https (login, gatekeeper) and the simulators on http with `SYSTEMIP`
        - [ ] a certificate on a bare IP (Let's Encrypt, short-lived): a separate subject, if the core can use it at all
    - [ ] deploy hook: copy the PEM where `opensim` reads them (`/etc/letsencrypt` is root's), restart the instances (the conversion only happens at start)
    - [ ] fully automated letsencrypt integration: the grid admin does nothing once it is enabled (obtain, renew, convert, restart)
        - the engine owns it: it knows the instances, their hostnames, their config, the restarts and the health; certbot is behind an interface, so it can move to a package of its own or be replaced
        - [ ] the challenge is chosen for each hostname, by who answers port 80 for it
            - the common case: `www.yourgrid.org` and `login.yourgrid.org:8002` on one server; port 80 is the web server's, which does not know `login`: standalone is impossible, and webroot does not work as it is
            - [ ] webroot: not trivial, the host of the instance has no web server of its own, so the web server needs a special configuration for it (a location `/.well-known/acme-challenge/` for that host, before any redirect to https), permanent or only for the renewal, written and removed by the engine for Caddy, nginx and Apache
            - [ ] DNS-01: no port, no web server, but not trivial either: the grid must own the domain (not a dynamic DNS) and give a token of its DNS provider; also a wildcard (one certificate for every prefix); a solid solution to allow and to document
            - [ ] standalone: when no web server runs on the machine
            - rare: Robust itself on port 80 (OSGrid): its instance is stopped for the challenge (the restart that loads the certificate is needed anyway)
            - [?] the process on port 80 answers the challenge itself: a module that serves `/.well-known/acme-challenge/` from a folder
        - [ ] `opensim ssl enable <grid|simulator> <hostname>` gets the certificate once, the timer of certbot renews it
        - [ ] `opensim ssl deploy` is its deploy hook: PEM copied where the instances read them, settings written, instances that use the hostname restarted
            - simulator: `EnableCertConverter`, `PemCertPublicKey`, `PemCertPrivateKey`, `outputCertPassword` (the core converts at each start)
            - Robust: the `.p12` made with openssl
        - [ ] the expiry of each certificate in the health and the monitor
        - [?] a custom OpenSim module doing the same inside OpenSim: independent of the kit, would popularize SSL, reload without restart; after the engine does it, which is its specification
    - [ ] check: the `SSL/` folders are made in the working directory of the process, and the cores are read-only
    - [ ] try first on a public host with podman
    - [ ] section in the setup of a grid
    - [ ] `opensim ssl` command
- [ ] docker/podman installation in the setup
    - [ ] packaging scenario with docker (podman done: full scenario on Debian 12 and Ubuntu 24.04, packages from `apt-package`)
- [ ] macOS (the clone, libgdiplus)
    - [ ] libgdiplus: Homebrew `mono-libgdiplus`, its `libgdiplus.dylib` copied next to the binaries if not found
        - [ ] engine looks for it and installs it on Linux only (`DotnetInstaller`, `RuntimeProbe`, `StartService`: `ldconfig`, `.so`)
        - [ ] `setup.sh` installs it
    - [ ] clone on macOS tried to the end
    - [ ] test macOS (Homebrew), which the container scenario does not cover
- [ ] web setup beyond the quick setup (`tests/Packaging/wizardweb-grid-scenario.sh` makes a grid, its simulator and its region in the browser, as a person who is not the user of the install, and starts them)
    - [ ] advanced setup
    - [ ] core installed from the page
    - [ ] more regions and simulators
    - [ ] remote grid
- [ ] machine install screens as data
- [ ] mode where the app is cloned in the folder of the site and locks itself once configured (WordPress-like)

Helpers:

- [ ] search categories as sub-paths
- [ ] guide topics as sub-paths (`/guide/<topic>/`)
- [ ] `GridStatus` and `GridStatusRSS` of `[GridInfoService]`
    - Firestorm reads them, in the formats of Second Life, from the OpenSimExtras of the simulator features, where `GridService.SetExtraServiceURLs` of Robust puts them from `[GridInfoService]` (`get_grid_info` says them too)
    - `GridStatus`: a light HTML or plain text page, the viewer takes one line of global status from it by keyword (ONLINE green, OFFLINE red, DEGRADED or MAINTENANCE orange)
        - [ ] DEGRADED
        - [ ] MAINTENANCE
    - `GridStatusRSS`: a standard RSS 2.0 feed (dates RFC 822: title, link, guid, pubDate, description in CDATA, everything in English)
        - [ ] first only the moments the grid changes state (the parsers have part of it)
        - [ ] later real messages (another RSS source, a social feed)
    - examples: status.secondlifegrid.net (`history.rss`, `history.atom`, and the page, like health but for the public)
- [ ] lists for LSL scripts: a guide source can be this format too
    - the guide and the search give the list as text: title and URL separated by a pipe, a text alone is a title, a dash alone a new column, a pipe alone an empty row
- [ ] `news` block of the right sidebar: what does it show?
- [ ] shortcode form `[grid_info]` for texts
- [ ] Bootstrap or Tailwind for the looks
- [ ] web search settings, to confirm with each viewer
    - tested with Firestorm (default mode)
        - `[GridInfoService] search` reaches it (its web search tab and the parameters of the grid); the standard search uses `query.php` with its own interface
        - `[LoginService] SearchURL` or `[LoginService] search` alone do not, though `SearchURL` is the documented one (the core sends it in the login response and as `search-server-url` of the grid features)
    - [ ] who reads `SearchURL`: other viewers, or Firestorm in another mode?
    - [ ] test the other modes of Firestorm (Phoenix, Viewer 7, hybrid, text)
    - [ ] test other viewers
    - the setup gives the same value to both settings, `search` is the one the helpers test
    - a simulator's own `[SimulatorFeatures] SearchServerURI` is for a complete management interface (w4os), not the helpers

OpenSim Manager:

- [ ] grid info block
- [ ] grid status block
- [ ] new users block

w4os plugin:

- [ ] implement or port the new features above

### 3.2 (more, later)

- [ ] display names
    - [ ] custom display names (a display name that is not the account name)
    - OSGrid publishes a closed module (`DisplayNameSimModule`, `contrib/osgrid`)
        - serves the viewer capabilities (`SetDisplayName`, `GetDisplayNames`)
        - asks a web service of OSGrid (`/get?id=`, `/set`, `/reset`, answers with `display_name`, `display_name_next_update`...)
    - for our grids, one of:
        - [ ] a service in the helpers and a module of our own
        - [ ] the same API, so that their module works with ours
- [ ] classifieds: handle hypergrid/centralized (might require an OpenSim module, as it does not seem to expose a way to query an external source)
- [ ] one rendering engine for the pictures of text: the one of 2do-mahna-mahna
    - columns, a header, a footer, the proportions of the prim (1x1, 1.5x1 and 1x1.5 give different results)
    - [ ] 2do board uses it
    - [ ] the calendar of events (2do) and the destinations in world use it
    - [ ] `textgen` becomes a simplified version of the same engine (`Assets\TextRendererInterface` is the place)
    - [ ] `textgen.php` and its parameters stay as they are: hundreds of objects in world call them (`Api\Legacy::textgen()`)
    - [ ] the picture of the monitor (`format=png`, square today) takes the proportions of the prim
- [ ] Mega board: combine Teleport Board, 2d Events and Advertising/Communication, implement 2do Board server-side rendering on specific linked prims/faces for each
- [ ] offline messaging: handle hypergrid/centralized (part of the exchanges are only between sender simulator and viewer, not querying either sender home grid or recipient home grid for proper helpers handling)
- [ ] centralized NPC chatbot (avoid full chat script baked into NPC image, uneditable)
- [ ] AI integration: chatbot
- [ ] AI integration: MCP
- [ ] profile stream, see `opensim-flux` project
    - [ ] `opensim-flux`: a project of its own, to be added as a submodule when it starts (the plan is in its README)
        - optional social stream by avatar, on ActivityPub
        - installed with the kit, enabled apart (`Flux.ini` and/or `[Flux]`)
    - [ ] the stream itself in the helpers
- [ ] additional currency modules
    - MoneyServer (Podex needs it, so does a game currency without real value): an old version, unmaintained, its site is gone
        - [ ] MoneyServer forks: compare the forks (DEVELOPERS.md)
        - [ ] keep a stable one, or make one that reads its settings like the other modules (its ini is looked for next to its executable, its credentials are not dotNet connection strings)
    - PayPal: the original module is outdated
        - [ ] compare the recent forks
    - [ ] the settings of Podex and PayPal, after an analysis (the Podex model trusts the grid owner, see `W4OS-PARITY.md`)
    - until then the helpers keep the Podex and PayPal code they have, with no addon and no setting
- [ ] true CMS (page builder, themes, ...): a viable alternative to WordPress or similar frameworks, for a basic website, we don't try to compete with full-featured CMS':
    - [ ] order of the pages in the menus
    - [ ] a summary of a page for the search engines
    - [ ] a page in other places than a menu (footer, sidebar...)
    - [ ] page builder
    - [ ] themes
- [ ] Managed simulator pool: maintain a list of simulators assigned to grid's pool(s), to allow adding new regions to the less busy one (in CPU and/or or prim usage) (optional, manual selection or dedicated simulator creation still available). There could be a pool for mainland, a pool for estates or other region types, and maybe a pool by user/owner
- [ ] Ticketing: functionalties similar to laravel magicoli/two-way-tickets, but independant from framework, issues linked to grid/simulator and can be linked by admin to the related github repo (not a single specific repo but the one involved in the issue)
- [ ] Mac: alternative MacPorts support
- [ ] Setup: disambiguate Simulators and Regions in default naming convention (Welcome -> "Welcome Simulator" to distinguish it from its region "Welcome")
- [ ] Guest simulators (outside users connecting a simulator to our grids), after the v3 release
    - the private port is never public: an open private port lets anyone connect a simulator unverified (what OSGrid met)
    - until then, the private ports are open to known servers only (ours first, then local machines such as vonda or amy)
    - the guest must be identified: confirm that the connecting IP is the guest's
    - one of (to analyse, combinable):
        - [ ] Robust's own authentication (already in the OpenSim code)
        - [ ] a secondary Robust for guests only: the private services only, on the same databases (exhaustive tests first)
        - [ ] a sands/dyndns2 identity (account on sands, hostname kept on the current IP) feeding the allow list of the firewall
    - first case: a friend's simulator refused on the Robust private port of speculoos.world (2026-11-14)

## Backlog (no milestone), by package

Engine:

- [ ] the minimum length of the password of an avatar is the admin's to set, in `opensim.conf` (6 characters today, OpenSimulator has no rule; the double quote stays forbidden, the implementations and the console do not agree on it)
- [ ] full sqlite3 and pgsql compatibility check (including OpenSim install and all queries review). This is theorically the case thanks to the use of PDO, but it must be tested systematicaly to lift the mysql dependency
- [ ] memory/CPU usage monitoring
    - notify admin and/or restart Sim above given thresholds
    - maybe better to suggest a dedicated monitoring tool to handle that

Kit:

- [ ] setup from a clone: the archive of the core belongs in the cache
    - today downloaded into `src/` of the clone (`src/opensim-0.9.3.0.tar.gz`), the folder of the PHP code
- [ ] `setup.sh` in a clone builds the packages and installs them by default (developers' mode, with the scenarios of the tests)
    - [ ] running from the clone with composer becomes an option
- [ ] presets
    - [ ] the grid tells the rest of its description in `setup.json` (as a grid of this machine does)
    - [ ] presets of complete grids (the whole folder)
    - [ ] the in-world search of speculoos.world is the 2do directory with `gk=`: check it
- [ ] one place for the OpenSimulator cores
    - the release ones (`core/`) and the development one (`upstream/opensim`, a submodule) all go to `core/`
    - OpenSimulator is not an upstream of the kit, the kit includes it
    - files: `packaging/build`, `packaging/remote-build`, `.gitmodules`, `DEVELOPERS.md`
- [ ] bash 4 or later for the scripts that use bash-tools, and bash 3 for the ones a person runs
    - [ ] a guard at the start of `packaging/tools` and of the dev scripts that load bash-tools: stop with a message with bash 3 (the PATH stays the person's)
    - [ ] the scripts shipped to a machine (`setup.sh`, `libexec/setup-frontend.sh`, the cron) do not use bash-tools: they stay compatible with bash 3 (macOS), checked by a test
- [ ] `opensim online`: show who's online
- [ ] localize the launcher and the bash scripts with gettext
- [ ] a first translation of the setup (`locales/fr.po`)
- [ ] localize helpers

Helpers:

- [ ] the viewer and its updates (Olivier, 2026-11-16)
    - [ ] a block that tells the viewer whether an update of it is available: its version read from its user agent, compared with the last release of that viewer; optional, in the splash page by default
    - [ ] what it does is the manager's to set: the last version shown in the block or not, and an update notice posted to the zone of notifications or not
    - the zone of notifications is being built now (2026-11-16, the mini site, in the site and the helpers): a message at the beginning of the content of any page of the site and of the helpers, for an update of the viewer, an error, anything to tell the person

Build and release:

- [ ] `build-tools release` refuses a pre-release when the apt repository has no suite for it
    - [ ] betas published in `stable` to move to `unstable` (cleanup, later)
- [ ] rename composer packages to match github account GuduleLapointe

Backups:

- [ ] codenames of the series sort alphabetically: 3.x is `charm`, the 4.x one must sort after it (a word starting with d or later)
    - the backup type folder is named after it: `opensim-charm`

Other projects:

- [ ] install instructions in the README of `lsl-ossl-zed`
    - rust, clone, `zed: install dev extension`
    - the prebuilt LSP binaries only cover Linux x86_64 and macOS
