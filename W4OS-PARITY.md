# What w4os does, and where it goes in the suite

**The aim**: every feature of w4os that is not specific to WordPress has its implementation in the suite (engine, helpers, kit, manager),
so that the plugin only bridges: it calls a method of the engine or the helpers and puts the answer in a shortcode, a block, a widget.
The manager does the same with Blade and Filament. No feature goes away; what is not in the suite yet is listed below with the
package it belongs to and the milestone. When the suite is ready, a new integration (Drupal, Ghost, Joomla, PrestaShop...) is a bridge
of two or three days: the documentation of the engine API is part of that (step 8 of the manager's ARCHITECTURE.md).

Three versions were compared, by their code: **2.8** (tag `v2.8`, wordpress.org), **2.9.4** (tag `v2.9.4`, last stable on GitHub, adds the
v3 preview) and **speculoos** (branch `speculoos`, 3.0.1-dev, in service on speculoos.world, with bugs). Some features of 2.x are not
all ready in speculoos, which has new ones the others do not: all of them count.

- Integration points (shortcodes, blocks, widgets, admin pages, hooks, cron, post types, ajax) and the ~190 options of 2.8 are all
  still in 2.9.4 and in speculoos (3 internal options excepted). Of the 304, 473 and 558 functions and methods, 4 of 2.8 are gone from
  2.9.4 and 42 of 2.9.4 from speculoos: moved or renamed (`OpenSim_Rest` and the console are the engine's, `W4OS3_*` folded, the models
  preview...), none is a feature that went away. Speculoos adds the `account_info` and `custom_auth_form` shortcodes, the
  `w4os/avatar-menu` block, the installation wizard, userless authentication (proof of concept) and the 2to3 migration.
- Not checked by running the plugin: the Divi modules, WooCommerce flows, the multilingual page lookup, every block as rendered.

Homes: **E** engine · **H** helpers · **K** kit · **M** manager · **W** WordPress only (the bridge, and what WordPress owns: its users,
roles, menus, WooCommerce). State: ✓ done · ◐ partly · ✗ missing.

Two bridges are followed: **Grav**, `grav-plugin-w4os` (with the light site `w4os-site` that bundles it), and **WP**, `w4os-wp`, the
WordPress plugin to come, so nothing is done there yet: its column is the plan of the port. Updated 2026-11-17, from the code of
opensim-engine and opensim-helpers on charm-dev and of grav-plugin-w4os.

## Public content (the content comes from H; the shell is a shortcode, a block, a Twig or Blade template)

| Feature | Home | State | Grav | WP | Milestone |
| --- | --- | --- | --- | --- | --- |
| Grid info, grid status, popular places, web search | H blocks | ✓ | ✓ `[w4os block=…]`, the home page laid out like the splash | ✗ | |
| Destinations guide | H | ✓ read from `GuideSource` of `Helpers.ini` only, no address from the request | ✓ `[w4os block="destination_guide"]` | ✗ | 3.0 |
| Destinations guide, as the 2.x guide: the list format of the boards ("Name\|host:port:Region\|x,y,z", a name alone a category, `#` and `//` comments, `-` and `\|` layout hints, the 4th and 5th columns of the old format), the same categories, order and links (`secondlife://host:port Region/x/y/z`) | H (`DestinationGuideBlock`, `tests/Unit/DestinationGuideTest.php` against the 2.x output), E (`DestinationList`, `Destination`) | ◐ same categories, destinations and links; a place in the region now in the link; ✗ a grid without its region (link ends with %20) and `host:Region` without port (empty link), E; ✗ the list for the boards (`format=list`) loses the 3rd column and the layout hints, E | ✓ through the block | ✗ | 3.0 |
| Avatar profile (page, block, mini profile) | H (block), E (data: picks, classifieds, groups, `GET /avatars/{id}/picks`, `/classifieds`, `/groups`) | ✓ the viewer's tabs, always all (2nd Life, Web, Interests, Picks, Classifieds, 1st Life; None. when empty); picture in its proportions; name, key, birth and age, account, partner, groups; picks and classifieds as the viewer (list, picture, place, teleport link); public only when published; tab in the address | ✓ `/profile/First.Last` (the helpers' block, bare for a viewer), Edit button for its owner or a manager | ✗ | 3.0 |
| **Web assets server** (`/assets/<uuid>.<format>`: in-world pictures on a site) | E (`WebAssets`, API `/assets/{file}`), H route | ✓ converted from the asset service or the database, kept a day, placeholder | ✓ `/grid-assets/<uuid>.jpg` in bundled mode, the API's in remote mode | ✗ | **3.0** |
| Logos of w4os and OpenSimulator, logo of the grid (`LogoURL`) | E (`Logos`, API `/logos/{name}`, `logo` of `/info`) | ✓ | ✓ footer, top bar, favicon | ✗ | 3.0 |
| New users (block, dashboard widget) | H (`new_users`) | ✓ newest people, names linking to their profile; models, technical accounts, accounts without email and disabled ones left out | ✓ on the managers' dashboard | ✗ | 3.0 |
| Dashboard widgets (grid info, status, new users) | content H, shell W and M | M shows placeholders | ◐ managers' dashboard `/account/dashboard` (Tabler cards: regions online, simulators running, avatars online and pending, each leading to its filtered list; the newest avatars); grid info and status not yet | ✗ | 3.1 |
| Activity feed, statuses, in-world feed tab (Flux) | `opensim-flux` | ✗ | ✗ | ✗ | 3.2 |
| Notices to the visitor (a new viewer, an error...) at the start of a page | H (`View::notify()`; a block's in its `X-Helpers-Notices` header) | ✓ | ✓ in the site's zone of messages, whatever the theme | ✗ | 3.0 |
| Pages of the grid on the site (search, profile, guide) or on the helpers: each address on its own | H (served only under their base, `Grid::servedByHelpers()`), E (`[Helpers] HelpersURL`) | ✓ | ✓ the plugin's pages with the helpers' blocks; the helpers under `/helpers` | ✗ | 3.0 |
| Helpers on their own (no site): templates, a theme folder of the grid | H | ✓ layouts, pages and components, a theme folder replacing them and the assets | n/a | n/a | 3.0 |
| The shortcodes, blocks, widgets, Divi modules, avatar menu, localized pages | W | | ✓ shortcodes, visitor menu, pages in 7 languages | ✗ | |

## Avatars and accounts (the engine; the users of the site are WordPress's or Laravel's)

| Feature | Home | State | Grav | WP | Milestone |
| --- | --- | --- | --- | --- | --- |
| Create an avatar: names cleaned (accents out, letters and digits only, first letter capital), restricted names (Default, Test, Admin, the model pattern) refused, taken name refused | E | ✓ letters, digits and hyphens, accents transliterated (`normalizeName`), a name all in small letters or all in capitals gets its capitals (mixed case kept as typed), Default, Test, Admin and the grid's name refused, taken name refused; a new avatar is public | ✓ registration page, every refusal told by its code | ✗ | 3.0 |
| **Avatar models**: the list to choose from at registration (name and profile picture), what makes a model (a name pattern or a fixed list of avatars), the copy of its appearance and inventory into the new avatar | E (+ H for the picture) | ✓ `[Models]` rule, `GET /avatars/models` with the picture, outfit and inventory copied (checked on a MySQL grid) | ✓ models to pick, with their picture | ✗ | **3.0** |
| Email confirmation of a new avatar: nothing written in the grid until the link is followed, new link on demand | E | ✓ `avatar_registrations`, `202 registrations`, name taken meanwhile refused | ✓ confirm page, resend, test site without mail | ✗ | 3.0 |
| Change or reset the password of an avatar | E | ✓ `PATCH` with the current one, `password-resets` by mail | ✓ account page, forgotten password, managers' "reset link" | ✗ | 3.0 |
| Change the email of an avatar, confirmed by a link | E | ✓ `confirmEmail` of a change | ✓ account page | ✗ | 3.0 |
| Profile of an avatar edited on the web (about, wants, skills, languages, first life, URL) | E | ✓ the boxes of wants and skills (`wantsList`, `skillsList`), show in search (`published`), groups read | ✓ edit page in the viewer's tabs (2nd Life with the picture, key, birth, account, partner, groups, show in search; Web; Interests; Picks and Classifieds read; 1st Life; Account with email and password), languages as tags, the same page for a manager, View button | ✗ | 3.0 |
| Accounts in bulk | K | ✓ (to try with a viewer) | ✗ (the kit's) | ✗ | 3.0 |
| Sign in with avatar name and password | E `SignIn` | ✓ disabled and pending told apart (`account-disabled`, `email-unconfirmed`), also for Robust's private service | ✓ through the Login plugin, one message with the reason; Login's own limit of failures | ✗ | |
| Counts that leave out models and technical accounts (no email); visitors of other grids counted apart | E | ◐ members are the active accounts, models and technical ones included; users in world counted apart from members | ✓ shown as E counts them | ✗ | 3.0 |
| Avatars list: type (user, model, technical), active, online, last seen, created; search, sort. The profile modal is the interface's | E (data), M and W (display) | ✓ `GET /avatars`: search, sort (name, email, created, level, last seen, online), filters (status, role, god, technical, online); `status` active, disabled, pending; `role` from the level; no model type | ✓ managers' list: picture, status, role, filters, sort, enable, disable, reset link (none without an email); edit page: role, email (optional, clearable), new password; no delete (a full scenario first) | ✗ | 3.0 |
| Regions list: owner, simulator, presence, control, port, size, last seen; filters by owner and simulator | E | ◐ `listRegions`: name with its URI and teleport, location, size, owner, simulator, flags, last seen, enabled and online; filters status, flags, owner, simulator; no presence count nor port | ✓ admin list and region page (owner, flags, enabled) | ✗ | 3.0 |
| Regions list; start, stop, create a region | E, K, M | ✓ in E and K; M in 3.0 | ◐ simulators list: start, stop, restart, enable, disable (`startInstance`, `stopInstance`, `restartInstance`, `updateSimulator`); no region creation | ✗ | 3.0 |
| User and avatar linked, several avatars for one user, sync by hand or cron, terms accepted at registration, roles | W (its users), M (its users) | each has its own | ◐ the avatar is the site's user; terms at registration; managers from a user level | ✗ | |
| Registration open or closed, against bots without a captcha | W, M (the site's) | | ✓ setting `register.enabled`, the Form plugin's honeypot, the Login plugin's limit per address | ✗ | 3.0 |

## Management and services

| Feature | Home | State | Grav | WP | Milestone |
| --- | --- | --- | --- | --- | --- |
| Search: places, land for sale, classifieds, events (2do) | E (the search of the helpers: `register.php`, `query.php`, `parser.php`), H pages | ✓ | ✓ served by the bundled helpers (`serve_helpers`): web search answers, the simulators register to it (`[DataSnapshot]`, OpenSimSearch), the index fills by itself after the visits (the tasks in the site's folder, `TasksRoot`) | ✗ | |
| Offline messages by mail | H | ✓ | ✓ served by the bundled helpers | ✗ | |
| Currency: core money module, Gloebit | H | ✓ | ✓ served by the bundled helpers | ✗ | |
| Currency: Podex, PayPal and their settings (messages, URLs) | H | ◐ code kept, no setting; analysis first (see below) | ✗ | ✗ | later |
| Message of the day, splash page, texture from a URL (`textgen`), HYPEvents | H | ✓ | ✓ served by the bundled helpers; motd block on the home page | ✗ | |
| URLs and requirements check | H `health`, K `status` | ✓ | ◐ `w4os-site status` (the API answers) | ✗ | |
| Installation wizard | K | ✓ | ◐ `w4os-site setup` and `web` (connection, web server) | ✗ | |
| Migration of the settings of a 2.x install (WordPress options, `config.php`) | W, to the engine's settings | the helpers still read `config.php` | n/a | ✗ | plugin update |
| Connections: databases and credentials from the files, or through the console of Robust for a grid on another machine | E | ◐ files only | ✓ the API of the grid, or the bundled engine on the Robust database of a grid described by its login address | ✗ | 3.0 (to confirm) |
| Languages (cy, de, fr, it, nl, pt_BR): translations w4os has, carried for the strings that move | all | ✓ engine catalog and helpers in the six; manager: de, en, fr, nl | ✓ 7 languages, generated from the engine's catalog | ✗ | 3.0 |
| Branding: colors and logos of w4os by default, the grid's or the site's own | E (logos), the bridge | ✓ | ✓ colors of the logo, logo and favicon settings of the theme | ✗ | 3.0 |
| Gudz Teleport Board | H | in no version yet | ✗ | ✗ | later |

## Admin parity with w4os-wp (speculoos): the reference

w4os-wp on `speculoos` is the reference for what a bridge must offer: after the move to the engine 3.x, the WordPress plugin may not be
released with less than it gives today, and the Grav plugin follows the same list. Audited 2026-11-17 from the code of w4os-wp
(`speculoos`, b82e9362), opensim-engine (charm-dev 53d63a9, its contract: the `operationId`s below) and grav-plugin-w4os (charm-dev
db16b16). What loads on speculoos: `wordpress/init-plugin.php`, which starts the `W4OS3_*` classes (`init-plugin.php:95-121`) and still
loads `v1/init.php`, `v2/loader.php` and `v1/admin/admin-init.php` (`:1019-1031`): most service pages are v2 code waiting for their move.

Marks: **dev** a page or option the code calls dev, temporary or a placeholder; **legacy** v1 or v2 code still loaded, waiting to move to
its place; **not impl** shown but not working. They still count for parity, at the place they are meant to have. Gap side: **E** engine
(data or operation missing), **P** plugin (the engine has it, the bridge does not show it), **W** WordPress only (no parity needed in
Grav).

### Menu, Status, Settings

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| Menu OpenSimulator: Status, Settings, Avatars, Regions, Flux, services, Shortcodes, Installation Wizard | `v2/class-settings.php:93-126`, `class-w4os3-settings.php:31,108` | | ✓ sidebar: Dashboard, Simulators, Regions, Avatars (by level) | P: Settings and services in the sidebar once they exist |
| Status: user counts (grid accounts, linked, WP only, grid only, models, service accounts) | `v1/admin/status-page.php:13-57`, `v1/users.php:56` legacy | ✓ counts by filters of `listAvatars` (`model`, `technical`, `email`) | ◐ dashboard cards (online, pending), no models nor service accounts | P: two cards more (models, service accounts) in `w4os_cards`; linked WP accounts are W |
| Status: "Synchronize users now", sync cron | `status-page.php:110-117`, `v1/users.php:151,394` | | n/a (the avatar is the site's user) | W |
| Status: OpenSimulator pages and URLs checked (profile, search, offline messages, welcome, register, password, economy, about, help), "should be X, got Y", ini snippet, create page, cron check | `status-page.php:129-245`, `admin-init.php:269-428`, `public-functions.php:599` | ✓ `getHealth`, `pageHealth`, `getAddresses` | ✗ (only `w4os-site status` on the command line) | **P**: a Health page (or dashboard section) from `getHealth` and `getAddresses`, with the snippet of each address |
| Admin notices: requirements, Robust database, invalid login URI, local Robust found | `public-functions.php:849`, `v1/w4osdb.php:15-134`, `v2/class-settings.php:217` | ✓ `getHealth` | ✗ | P: the problems of `getHealth` as notices on the dashboard |
| Dashboard widgets: grid info, grid status, recent users | `v1/admin/dashboard.php:14-29` legacy | ✓ `getInfo`, `getMonitor`, `listAvatars` | ◐ recent avatars card; no grid info nor status card | P: grid info and grid status cards |
| Settings › Connections: Robust (host, port, console), assets and profiles databases | `class-w4os3-service.php:88-121` | ✓ the engine's own configuration (`getLayout`, `setups`) | ✓ Connection tab: API address and key, or the bundled engine and its database | none: the connections are the engine's |
| Settings › Pages: profile page, registration and password pages (custom URL, profile, WP default), terms page | `class-w4os3-avatar.php:441-465`, `v2/class-tos.php:36-69` | | ✓ Pages tab (a route for each page), terms page | none (the WP choices are W) |
| Settings › Developer: debug HTML, self-signed certificates | `class-w4os3-settings.php:49-58` dev | | Grav's debugger | W |
| v2 Settings: grid info (login URI, name, status) | `v2/class-settings.php:143-169` legacy, misplaced | ✓ `getInfo`, `getMonitor` | ◐ shown on the site, not in its settings | P: grid info on the dashboard (above) |
| Permalinks: profile, assets and helpers bases | `v1/profile-page.php:189`, `v2/class-helpers-assets.php:187`, `v2/class-helpers.php:113` | | ◐ profile and every page route; the helpers' base `/helpers`, assets fixed | P (low): the helpers' and assets' bases as routes |
| Installation wizard: config method, grid connection, grid info, helpers, economy, save | `class-w4os3-installation-wizard.php:45-131`, `helpers/engine/class-installation-wizard.php:77-702` dev, mostly not impl | ✓ `quickSetup`, `setupStep`, `applySetupFile`, `importSetup`, `getLayout` | ✗ (only `w4os-site setup` on the command line) | P: a setup page driving `setupStep`; E: the steps described for a form (an `x-form` of each step) so no bridge writes them |
| Shortcodes page: each shortcode, live preview, options | `v2/admin-helpers/templates/shortcodes.php:11-74` | | ✗ (the README) | P (low): a reference page |
| Settings test and validation pages, 2to3 migration | `wordpress/admin/settings-test.php`, `settings-validation.php` dev | | n/a | W (migration of WP options) |

### Avatars

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| List: name (level, profile modal, Profile link), email, type, active, online, last seen, created | `class-w4os3-avatar.php:491-734`, generic `class-w4os3-list-table.php` | ✓ `listAvatars` x-list: name and picture, email, role, created, last seen, status (account state with presence, ban) | ✓ and more: picture, role, bans, row menu, edit page | **E**: no type column (user, model, service account): the `model` and `technical` filters exist, not the attribute nor its column |
| Views with counts: All, Default Model, Service Account, User Avatar, Active, Inactive, Online, Offline | `class-w4os3-list-table.php:381-465` | ◐ one `meta.total` per query | ◐ filters, count of the filtered list, no count per value | **E**: counts per value of a filter in the list's `meta` (one query); P: views with their count |
| Sort by name, email, type, active, online, last seen, created; search; no pagination | `avatar.php:506-553`, `list-table.php:170-247` | ✓ sorts name, email, level, last seen, created, active, online; search | ✓ (and pages) | E: sort by type, once the type exists |
| Bulk actions: Message, Start, Restart, Stop, Disable | `list-table.php:318-356` not impl | ✗ no bulk operation (each row's action) | ✗ | P (low): checkboxes and the row actions in turn; message: E |
| Profile modal: picture, born, last seen, partner, wants, skills, languages, about | `avatar.php:883-906` | ✓ `getAvatar`, `listAvatarGroups` | ✓ profile page and edit page (no modal) | none |
| Avatars › Settings: create WP accounts, multiple avatars, override author, hide profile title | `avatar.php:405-427` (two not impl) | | n/a | W |
| Avatars › Avatar Models: match rule (first name, any, last name, a list), name, chosen avatars, preview | `class-w4os3-model.php:28-348`; duplicate `v2/class-avatar-model.php:84-175` legacy | ◐ `listAvatarModels`, `getAvatarModel` read; the rule is in the engine's settings (`[Models]`), no operation writes it | ◐ models offered at registration; no admin page | **E**: an operation (and `x-form`) to read and write the rule; **P**: an Avatar models page (the models with their picture, the rule) |
| Create an avatar from the admin | `v1/profile.php:1225-1253`, not impl (fails without a password) | ✓ `createAvatar` | ✗ (public registration only) | P: a "New avatar" action on the list; E: the creation fields as an `x-form` |
| WP user profile fields, users list columns and filter | `v1/profile.php:1142-1223`, `admin-init.php:61-217` legacy | | n/a | W |

### Regions and simulators

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| List: name with flags, Edit and Teleport links; owner (filter); URI; simulator (filter); control (console, database); presence; internal port; size; status; last activity | `class-w4os3-region.php:272-351,467-686` | ◐ `listRegions`: name with URI and teleport, location, size, owner, simulator, flags, last seen, enabled and online; filters status, flags, owner, simulator; console and database per simulator in `listSimulators` | ✓ regions list; simulators list with their connections and start, stop, restart | **E**: presence (avatars in the region) and internal port as columns |
| Views with counts by size and status | `region.php:332-340` | ◐ | ◐ | E and P: as for the avatars |
| Edit: simulator credentials, status, simulator, owner, teleport, map; parcels in a sidebar | `region.php:172-243,498` | ✓ `getRegion`, `updateRegion` (owner, flags, enabled), `listParcels` | ◐ region page: overview and form (owner, flags, enabled); no parcels | **P**: a Parcels tab from `listParcels` |
| Regions › Settings: "Make coffee" | `region.php:143-147` dev placeholder | | | none |

### Services (v2 pages, legacy, meant for the wizard and the settings)

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| Flux: post type with an avatar as author, list with Author column, profile tab, posting form, load more | `class-w4os3-flux.php:41-529`, `class-w4os3-avatar.php:1014-1095` | ✗ (`opensim-flux`) | ✗ | E (`opensim-flux`), then P; milestone 3.2 |
| Assets server: provide or not, internal URL, external server, assets base, conversion and cache | `v2/class-helpers-assets.php:81-262`, `templates/assets-render.php:70-180` | ✓ `getAssetMedia`, `pageAsset` | ✓ served (bundled) or the helpers' `/pictures` (remote) | none; P (low): an external assets server setting |
| Economy: provide, helper URL and ini snippets, its database, provider (Gloebit, Podex, none), rate, money script key, Podex messages | `v2/class-helpers-economy.php:52-407` | ✓ `currency`, `landTool`; the settings are the engine's (`[Economy]`), no operation reads or writes them | ✓ served by the bundled helpers; ✗ settings | **E**: the settings of the services as a resource with its `x-form`; P: a settings page that shows it |
| Search engine: provide, database, search and register URLs with their snippets, HYPEvents URL, parser every 5 minutes | `v2/class-helpers-search.php:60-271`, `v1/cron.php:4-56` | ✓ `searchQuery`, `registerSimulator`, tasks (`listTasks`, `runTask`) | ✓ served, the index fills after the visits; ✗ settings, ✗ tasks shown | E: settings as above; P: the tasks (last run, run now) |
| Destinations guide: provide, source, URL and snippet | `v2/class-w4os-guide.php:53-228` | ✓ `listDestinations`; the source in `Helpers.ini` | ✓ guide page; ✗ setting | E: settings as above |
| Offline messages: provide, sender address (never saved: a bug), URL and snippet | `v2/class-helpers-offline.php:46-168` | ✓ `offlineSaveMessage`, `offlineRetrieveMessages` | ✓ served; ✗ settings | E: settings as above |
| Addresses and ini snippets the viewers and simulators need | on each service page | ✓ `getAddresses` | ✗ in the admin | P: shown on the Health page (above) |

### Front end

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| Profile page `/profile/First.Last`, own profile, creation form, login; in-viewer profile | `class-w4os3-avatar.php:77-137,983-1104`, `templates/content-page-profile.php` | ✓ | ✓ | none |
| Avatar creation with a model | `v1/profile.php:676-812` | ✓ `createAvatar`, `listAvatarModels` | ✓ | none |
| Shortcodes and blocks: avatar-profile, grid-info, grid-status, popular-places, web-search, base_url; Divi modules | `blocks/*.php` | ✓ blocks of the helpers | ✓ shortcodes; Divi is W | none |
| Avatar menu (profile, account, logout; login, register) | `user-menu.php:71-348` | | ✓ visitor menu | none |
| Grid login with name or email, landing on the profile, wp-login takeover | `v1/gridauth.php:9-144` | ✓ `signIn` | ✓ | none |
| Terms checkbox at registration | `v2/class-tos.php:29-36` | | ✓ | none |
| Flux tab of a profile, posting | see Flux | ✗ | ✗ | E then P |
| Userless login (`custom_auth_form`, `account_info`) | `class-w4os3-userless-auth.php` dev, proof of concept | | n/a | none |

### The gaps, by priority

1. **Health** (P): a page or dashboard section from `getHealth` and `getAddresses`: the addresses viewers and simulators need with their
   ini snippet, what is wrong, the requirements. The engine has it all; w4os-wp's Status page is the most used admin page.
2. **Avatar models** (E then P): an operation to read and write the models rule, with its `x-form`; then an Avatar models page (the
   models with their picture, the rule).
3. **Avatar type and counts** (E then P): a `type` attribute and column (user, model, service account) sortable and filterable, and
   counts per value of a filter in the list's `meta`; then views with their count, for avatars (type, state, presence) and regions
   (status, size).
4. **Settings of the services** (E then P): economy, search, guide, offline messages, assets as a settings resource of the engine with
   its `x-form`, so each bridge shows one generic settings page; the search tasks (last run, run now) beside them.
5. **Create an avatar from the admin** (P, E for its `x-form`).
6. **Parcels of a region** (P): a tab of the region page from `listParcels`. Then presence and port in the regions list (E).
7. **Dashboard**: grid info and grid status cards, models and service accounts counts (P, configuration only).
8. **Installation wizard** (P, E for the steps' forms) over `setupStep`.
9. **Bulk actions** (P, low: not implemented in w4os-wp either) and a **shortcodes reference** page (P, low).
10. **Flux** (E: `opensim-flux`, then P), milestone 3.2.

## Money for the grid owner (new)

Registration is free on Speculoos and stays so; a paid offer pays for the work and the servers. What a plan or an order unlocks (a
region, its size and limits, an extra avatar, a service) must be applied from any platform: WooCommerce through the plugin, the Laravel
app, or a third party through the API of the engine. So the engine holds the catalogue of what can be sold and the actions an order
triggers (make a region, set its limits, suspend it, restore it); a payment platform is an adapter that calls them when a payment
comes in or stops. By hand first (3.0: the actions exist, the invoice is not automated), then one adapter (3.1).

**Podex**: its model rests on a banker avatar that the grid owner controls and can credit at will, since the balances and the
transactions are in the grid's database: the owner only promises Podex not to. Gloebit checks every transaction on its side and takes
the responsibility, without being exposed to a dishonest or clumsy grid owner. Nothing is added to the Podex code until it is analysed,
including what has to be checked for security.
