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

Bugs (found in use; no beta goes out with them):

Kit:

- [ ] `opensim start|restart` never rewrites the config
    - [ ] the config is written only after an install or an upgrade
    - [ ] and only with confirmation (as Debian does)
- [ ] Hypergrid setup complete: the manual fixes that were needed to get it working are done by the kit [?] which ones
- [ ] no `127.0.0.1` left in the config (unusable beyond tests)
- [ ] more flexible OSSL config, optional (the default one refuses very common usages, e.g. NPC and animated objects)
    - the standard config enables the functions with the defaults of the core
    - [ ] the operator customizes them: which functions, for whom
- [ ] options (OpenSimSearch, Gloebit...) come with their own ini file, merged into the config
- [ ] Caddy config compatible with an existing website
    - [ ] only the tiny website (`opensim-web`) is a fully handled website
    - [ ] the Caddy config is a snippet, updatable
    - [ ] its routes follow the URLs of the Robust ini (`/welcome`, `/search`, `/guide`...), even without handling `/*`
- [ ] `opensim start --verbose` or `--show-command`: show the command actually run, for a manual intervention in case of issues
    - a region created without a user needs a manual start to fix its owner and estate
- [ ] port mapping does not work from a local network
    - [ ] not included or enabled in the package as is
    - [ ] works from a local network (more work)

Helpers:

- [ ] popular places block asks the search URL of the grid, never fetches the results itself
    - the search service can be external, it is even the recommended setup (2do directory)
- [ ] popular places block has the details and the layout of the w4os block (the reference)
- [ ] links in the pages shown in a viewer use the `TPLINK_HG` form (Firestorm does not handle `hop://` from inside a page, though the browser sends them to it)

The beta:

- [ ] state of the refactoring frozen as a coherent beta of the family (the Unreleased list below)
    - [ ] `opensim setup`: install wizard from scratch to live grid with basic/default features (terminal CLI)
    - [ ] `opensim setup --web`: open wizard in a secured temporary website for web setup
        - [ ] the page is served over HTTPS (some browsers refuse forms over plain HTTP, the passwords must not cross the network in clear)
        - self-signed is fine for this temporary step
        - options: the server of the Symfony CLI, or Caddy `tls internal` in front of `php -S`
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
        - [ ] from its host (reference: `install/transfer-remote-sim.sh`)
            - [ ] connect to the host
            - [ ] fetch the config and the data
            - [ ] make the simulator here: an OAR when it can be reached, the database only when it cannot
    - [ ] `opensim restore`

### 3.0.0-beta.8 (safe for other grids)

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
- the grid imposes its asset service on the simulators
- other services: not separated unless the analysis says so

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
- [ ] try live: `opensim save oar` for every region (cron backup)
    - [ ] oar backup rotation (`--keep N`)
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
- [ ] offline messages on a grid without helpers
    - the setup writes no `[Messaging]`
    - the `OfflineIMService` of Robust, which the V2 module needs, is not enabled
    - options: helpers (mails, no limit) / V2 module with the Robust service (no mails, 25 messages) / both
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

- [ ] web assets server: serve `/assets/<uuid>.<format>`
    - [ ] textures of the grid converted for the web, read from the asset service of Robust
    - [ ] a cache
- [ ] picture of the avatar profile (needs the web assets)
- [ ] avatar models: the rule that makes a model (a name pattern, first or last name, or a fixed list of avatars)
    - [ ] list to choose from at registration, with name and profile picture
    - [ ] copy of the model's appearance and inventory into a new avatar
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
- [ ] delete a region
    - checks: users in it, neighbours, estate, parcels and objects
    - [ ] backup first
    - [ ] registry of the grid cleaned
    - [ ] registry of the search cleaned
    - today: the console (`delete-region`), its file and a row of the grid database, by hand
- [ ] ready to use default avatars
- [ ] default Inventory package
- [ ] nat/port forwarding instructions or presets (with standard tools or third-party provides like ngrok, cloudflare...)
- [ ] opensim tools installation-agnostic (kit installs and custom/standard OpenSim installs)
    - `/etc/opensim/opensim.conf` becomes only a reference pointing to the user's actual core installation and configuration files location (e.g. `/opt/osgrid`, `~/diva`, `~/opensim/opensim-0.9.3.0/bin`...)
    - [ ] a grid follows the profile of its own install, the default profile is only the default of the new grids
    - [ ] the system user to run the instances as is per profile, not only `SystemUser` of `[Defaults]`
    - [ ] the setup offers to register an install (the core menu)
    - [ ] no script assumes `/etc/opensim`, `/var/lib/opensim` or `/usr/share/opensim` (review, one fix per file, with a test)

Installation:

- [ ] SSL management for the OpenSim servers (0.9.3 supports it)
    - simulator: `http_listener_ssl` and a PKCS12 certificate; `EnableCertConverter` makes it from the PEM files of the CA (`fullchain.pem`, `privkey.pem`) at each start
    - Robust: `[Network] https_*` and `cert_path`, no converter (a `.p12` made with openssl)
    - [ ] base it on certbot, not on the certificate of Caddy (the `ExternalHostName` of a region is not always a site)
    - [ ] deploy hook: copy the PEM where `opensim` reads them (`/etc/letsencrypt` is root's), restart the instances (the conversion only happens at start)
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

## Backlog (no milestone), by package

Engine:

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
- [ ] `opensim online`: show who's online
- [ ] localize the launcher and the bash scripts with gettext
- [ ] a first translation of the setup (`locales/fr.po`)
- [ ] localize helpers

Build and release:

- [ ] `build-tools release` refuses a pre-release when the apt repository has no suite for it
    - [ ] betas published in `stable` to move to `unstable` (cleanup, later)
- [ ] rename composer packages to match github account GuduleLapointe

Other projects:

- [ ] install instructions in the README of `lsl-ossl-zed`
    - rust, clone, `zed: install dev extension`
    - the prebuilt LSP binaries only cover Linux x86_64 and macOS
