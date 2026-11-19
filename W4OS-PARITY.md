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
WordPress plugin to come, so nothing is done there yet: its column is the plan of the port. Updated 2026-11-19, from the code on
charm-dev of opensim-engine (8d0d7a7), opensim-helpers (8cafe24), grav-plugin-w4os (2c3c2e3) and w4os-views (44b25a8).

## Public content (the content comes from H; the shell is a shortcode, a block, a Twig or Blade template)

| Feature | Home | State | Grav | WP | Milestone |
| --- | --- | --- | --- | --- | --- |
| Grid info, grid status, web search | H blocks | ✓ | ✓ `[w4os block=…]`, the home page laid out like the splash | ✗ | |
| Popular places, as w4os-wp 2.x (`blocks/popular-places.php`): the parcels with the most visitors as a grid of cards, as many rows as needed, the picture of the parcel (its snapshot in the search) with its name over it and its region under it | H (`PopularPlacesBlock`, `assets/css/places.css`), E (`Grid::popularPlaces()`) | ✓ a place without a picture has the grid's placeholder (2.x left it out); `count` (10 by default, 2.x 5); `include_hypergrid`, `include_landsales`, off by default as in 2.x | ✓ `[w4os block="popular_places"]` with its options, listed on the shortcodes page | ✗ | 3.0 |
| Destinations guide | H | ✓ read from `GuideSource` of `Helpers.ini` only, no address from the request | ✓ `[w4os block="destination_guide"]` | ✗ | 3.0 |
| Destinations guide, as the 2.x guide, same format as Gudz Teleport Board ("Name\|host:port:Region\|x,y,z", a name alone a title, `#` and `//` comments, `-` a column, `\|` alone a spacer, the old 5-column format), the same categories, order and links, cards with the picture of the parcel | H (`DestinationGuideBlock`, `DestinationGuideTest`), E (`DestinationList`, `Destination`) | ✓ the lines read as the board reads them (the old 5-column format, an address once); the list for the boards (`format=list`) keeps the landing point and the markers | ✓ through the block | ✗ | 3.0 |
| Avatar profile (page, block, mini profile) | H (block), E (data: picks, classifieds, groups, `GET /avatars/{id}/picks`, `/classifieds`, `/groups`) | ✓ the viewer's tabs, always all (2nd Life, Web, Interests, Picks, Classifieds, 1st Life; None. when empty); picture in its proportions; name, key, birth and age, account, partner, groups; picks and classifieds as the viewer (list, picture, place, teleport link); public only when published; tab in the address | ✓ `/profile/First.Last` (the helpers' block, bare for a viewer), Edit button for its owner or a manager | ✗ | 3.0 |
| **Web assets server** (`/assets/<uuid>.<format>`: in-world pictures on a site) | E (`WebAssets`, API `/assets/{file}`), H route | ✓ converted from the asset service or the database, kept a day, placeholder | ✓ `/grid-assets/<uuid>.jpg` in bundled mode, the API's in remote mode | ✗ | **3.0** |
| Logos of w4os and OpenSimulator, logo of the grid (`LogoURL`) | E (`Logos`, API `/logos/{name}`, `logo` of `/info`) | ✓ | ✓ footer, top bar, favicon | ✗ | 3.0 |
| New users (block, dashboard widget) | H (`new_users`) | ✓ newest people, names linking to their profile; models, technical accounts, accounts without email and disabled ones left out | ✓ on the managers' dashboard | ✗ | 3.0 |
| Dashboard widgets (grid info, status, new users) | content H, shell W and M | ✓ | ✓ admin dashboard `/w4os`: cards (regions online, simulators running, avatars online, pending, models, service accounts), the newest avatars, the grid info and status blocks | ✗ | 3.1 |
| Activity feed, statuses, in-world feed tab (Flux) | E (posts), `opensim-flux` | ◐ the posts of an avatar, the list of all the posts (`listPosts`); no feed page of the grid | ✓ the stream of a profile and its posting form; the Flux admin list (author, date, search, delete) | ✗ | 3.2 |
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
| Counts that leave out models and technical accounts (no email); visitors of other grids counted apart | E | ✓ the views of the avatars count the user avatars only (`meta.counts`), as w4os-wp; users in world apart from members | ✓ shown as E counts them | ✗ | 3.0 |
| Avatars list: type (user, model, technical), active, online, last seen, created; search, sort. The profile modal is the interface's | E (data), M and W (display) | ✓ `listAvatars`: `type` (user, model, service); sorts name, email, level, type, created, last seen, active, online; filters status, online, role, type, published, email, model, technical, god, active; views type, active, online with their counts | ✓ managers' list: picture, status, role, type, views with counts, enable, disable, reset, bans; New avatar; edit page | ✗ | 3.0 |
| Regions list: owner, simulator, presence, control, port, size, last seen; filters by owner and simulator | E | ✓ `listRegions` in the columns of w4os-wp (name with its URI, owner, simulator, presence, internal port, size, location, last activity, flags, status); filters status, flags, owner, simulator, size; views status and size with counts | ✓ admin list with its views; region page with its parcels | ✗ | 3.0 |
| Regions list; start, stop, create a region | E, K, M | ✓ in E and K; M in 3.0 | ◐ simulators list: start, stop, restart, enable, disable (`startInstance`, `stopInstance`, `restartInstance`, `updateSimulator`); no region creation | ✗ | 3.0 |
| User and avatar linked, several avatars for one user, sync by hand or cron, terms accepted at registration, roles | W (its users), M (its users) | each has its own | ◐ the avatar is the site's user; terms at registration; managers from a user level | ✗ | |
| Registration open or closed, against bots without a captcha | W, M (the site's) | | ✓ setting `register.enabled`, the Form plugin's honeypot, the Login plugin's limit per address | ✗ | 3.0 |

## Management and services

| Feature | Home | State | Grav | WP | Milestone |
| --- | --- | --- | --- | --- | --- |
| Search: places, land for sale, classifieds, events (2do) | E (the search of the helpers: `register.php`, `query.php`, `parser.php`), H pages | ✓ | ✓ served by the bundled helpers (`serve_helpers`): web search answers, the simulators register to it (`[DataSnapshot]`, OpenSimSearch), the index fills by itself after the visits (the tasks in the site's folder, `TasksRoot`) | ✗ | |
| Offline messages by mail | H | ✓ | ✓ served by the bundled helpers | ✗ | |
| Currency: core money module, Gloebit | H | ✓ | ✓ served by the bundled helpers | ✗ | |
| Currency: Podex, PayPal and their settings (messages, URLs) | H, E (settings) | ◐ code kept; the Podex message and URL in the economy settings (`PodexMessage`, `PodexUrl`); analysis first (see below) | ✗ | ✗ | later |
| Message of the day, splash page, texture from a URL (`textgen`), HYPEvents | H | ✓ | ✓ served by the bundled helpers; motd block on the home page | ✗ | |
| URLs and requirements check | E `getHealth`, H `health`, K `status` | ✓ | ✓ Health page `/w4os/health` (each line with its snippet, checked again one by one); `w4os-site status` | ✗ | |
| Installation wizard | K | ✓ | ◐ `w4os-site setup` and `web` (connection, web server) | ✗ | |
| Migration of the settings of a 2.x install (WordPress options, `config.php`) | W, to the engine's settings | the helpers still read `config.php` | n/a | ✗ | plugin update |
| Connections: databases and credentials from the files, or through the console of Robust for a grid on another machine | E | ◐ the files, or the access an application gives (`DatabaseAccess`); not through the console | ✓ the API of the grid, or the bundled engine on the Robust database of a grid described by its login address | ✗ | 3.0 (to confirm) |
| Languages (cy, de, fr, it, nl, pt_BR): translations w4os has, carried for the strings that move | all | ✓ engine catalog and helpers in the six; manager: de, en, fr, nl | ✓ 7 languages, generated from the engine's catalog | ✗ | 3.0 |
| Branding: colors and logos of w4os by default, the grid's or the site's own | E (logos), the bridge | ✓ | ✓ colors of the logo, logo and favicon settings of the theme | ✗ | 3.0 |
| Gudz Teleport Board | H | in no version yet | ✗ | ✗ | later |

## Admin parity with w4os-wp (speculoos): the reference

w4os-wp on `speculoos` is the reference for what a bridge must offer: after the move to the engine 3.x, the WordPress plugin may not be
released with less than it gives today, and the Grav plugin follows the same list. Audited 2026-11-17 from the code of w4os-wp
(`speculoos`, b82e9362); its Engine and Grav columns checked again 2026-11-19 against opensim-engine (charm-dev fed8e40, its
contract: the `operationId`s below) and grav-plugin-w4os (charm-dev e701b78). What loads on speculoos: `wordpress/init-plugin.php`, which starts the `W4OS3_*` classes (`init-plugin.php:95-121`) and still
loads `v1/init.php`, `v2/loader.php` and `v1/admin/admin-init.php` (`:1019-1031`): most service pages are v2 code waiting for their move.

Marks: **dev** a page or option the code calls dev, temporary or a placeholder; **legacy** v1 or v2 code still loaded, waiting to move to
its place; **not impl** shown but not working. They still count for parity, at the place they are meant to have. Gap side: **E** engine
(data or operation missing), **P** plugin (the engine has it, the bridge does not show it), **W** WordPress only (no parity needed in
Grav).

### Menu, Status, Settings

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| Menu OpenSimulator: Status, Settings, Avatars, Regions, Flux, services, Shortcodes, Installation Wizard | `v2/class-settings.php:93-126`, `class-w4os3-settings.php:31,108` | ✓ `menu` of the settings forms and of the lists | ✓ sidebar: Dashboard, Avatars, Flux, Regions, Simulators, Models, the five services, Health, Shortcodes, Settings | none (the wizard: not a priority) |
| Status: user counts (grid accounts, linked, WP only, grid only, models, service accounts) | `v1/admin/status-page.php:13-57`, `v1/users.php:56` legacy | ✓ views of `listAvatars` with their counts (`type`) | ✓ dashboard cards, models and service accounts with them | none (linked WP accounts are W) |
| Status: "Synchronize users now", sync cron | `status-page.php:110-117`, `v1/users.php:151,394` | | n/a (the avatar is the site's user) | W |
| Status: OpenSimulator pages and URLs checked (profile, search, offline messages, welcome, register, password, economy, about, help), "should be X, got Y", ini snippet, create page, cron check | `status-page.php:129-245`, `admin-init.php:269-428`, `public-functions.php:599` | ✓ `getHealth`, `getHealthCheck`, `getAddresses`; kept and refreshed by the `health` task | ✓ Health page: each line, expected and got, its snippet, checked again one by one | none |
| Admin notices: requirements, Robust database, invalid login URI, local Robust found | `public-functions.php:849`, `v1/w4osdb.php:15-134`, `v2/class-settings.php:217` | ✓ `getHealth` | ✓ the problems of the last health report on the dashboard, a link to the Health page | none |
| Dashboard widgets: grid info, grid status, recent users | `v1/admin/dashboard.php:14-29` legacy | ✓ `getInfo`, `getMonitor`, `listAvatars` | ✓ grid info and status blocks, recent avatars card | none |
| Settings › Connections: Robust (host, port, console), assets and profiles databases | `class-w4os3-service.php:88-121` | ✓ the engine's own configuration (`getLayout`, `setups`) | ✓ Connection tab: API address and key, or the bundled engine and its database | none: the connections are the engine's |
| Settings › Pages: profile page, registration and password pages (custom URL, profile, WP default), terms page | `class-w4os3-avatar.php:441-465`, `v2/class-tos.php:36-69` | | ✓ Pages tab (a route for each page), terms page | none (the WP choices are W) |
| Settings › Developer: debug HTML, self-signed certificates | `class-w4os3-settings.php:49-58` dev | | Grav's debugger | W |
| v2 Settings: grid info (login URI, name, status) | `v2/class-settings.php:143-169` legacy, misplaced | ✓ `getInfo`, `getMonitor` | ✓ on the dashboard | none |
| Permalinks: profile, assets and helpers bases | `v1/profile-page.php:189`, `v2/class-helpers-assets.php:187`, `v2/class-helpers.php:113` | | ◐ profile and every page route; the helpers' base `/helpers`, assets fixed | P (low): the helpers' and assets' bases as routes |
| Installation wizard: config method, grid connection, grid info, helpers, economy, save | `class-w4os3-installation-wizard.php:45-131`, `helpers/engine/class-installation-wizard.php:77-702` dev, mostly not impl | ✓ `quickSetup`, `setupStep`, `applySetupFile`, `importSetup`, `getLayout` | ✗ (only `w4os-site setup` on the command line) | not a priority (Olivier, 2026-11-18). Later, a choice: (a) the screens of `setupStep` (the machine's quick setup) in the field format of `x-form` with catalog keys, or (b) a setup of a site on a grid, as w4os-wp's wizard, over the settings pages; (b) for the bridges, (a) for a web installer |
| Shortcodes page: each shortcode, live preview, options | `v2/admin-helpers/templates/shortcodes.php:11-74` | | ✓ `/w4os/shortcodes`: each shortcode, its options, the block it shows | none |
| Settings test and validation pages, 2to3 migration | `wordpress/admin/settings-test.php`, `settings-validation.php` dev | | n/a | W (migration of WP options) |

### Avatars

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| List: name (level, profile modal, Profile link), email, type, active, online, last seen, created | `class-w4os3-avatar.php:491-734`, generic `class-w4os3-list-table.php` | ✓ `listAvatars` x-list: name and picture, email, role, type, created, last seen, status | ✓ and more: picture, role, bans, row menu, edit page | none |
| Views with counts: All, Default Model, Service Account, User Avatar, Active, Inactive, Online, Offline | `class-w4os3-list-table.php:381-465` | ✓ `views` of the x-list, `meta.counts` per value (user avatars only, as w4os-wp) | ✓ views with their count | none |
| Sort by name, email, type, active, online, last seen, created; search; no pagination | `avatar.php:506-553`, `list-table.php:170-247` | ✓ every sort, type included; search | ✓ (and pages) | none |
| Bulk actions: Message, Start, Restart, Stop, Disable | `list-table.php:318-356` not impl | ✗ no bulk operation (each row's action) | ✗ | P (low): checkboxes and the row actions in turn; message: E |
| Profile modal: picture, born, last seen, partner, wants, skills, languages, about | `avatar.php:883-906` | ✓ `getAvatar`, `listAvatarGroups` | ✓ profile page and edit page (no modal) | none |
| Avatars › Settings: create WP accounts, multiple avatars, override author, hide profile title | `avatar.php:405-427` (two not impl) | | n/a | W |
| Avatars › Avatar Models: match rule (first name, any, last name, a list), name, chosen avatars, preview | `class-w4os3-model.php:28-348`; duplicate `v2/class-avatar-model.php:84-175` legacy | ✓ `getModelsSettings`, `updateModelsSettings` with their `x-form` and preview; `listAvatarModels` | ✓ Models page: the rule, the models it picks with their picture | none |
| Create an avatar from the admin | `v1/profile.php:1225-1253`, not impl (fails without a password) | ✓ `createAvatar` with its `x-form`, `create` of the list | ✓ New avatar page | none |
| WP user profile fields, users list columns and filter | `v1/profile.php:1142-1223`, `admin-init.php:61-217` legacy | | n/a | W |

### Regions and simulators

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| List: name with flags, Edit and Teleport links; owner (filter); URI; simulator (filter); control (console, database); presence; internal port; size; status; last activity | `class-w4os3-region.php:272-351,467-686` | ✓ `listRegions` in the columns and words of w4os-wp, the URI under the name, the status last; console and database per simulator in `listSimulators` | ✓ regions list; simulators list with their connections and start, stop, restart | none |
| Views with counts by size and status | `region.php:332-340` | ✓ | ✓ | none |
| Edit: simulator credentials, status, simulator, owner, teleport, map; parcels in a sidebar | `region.php:172-243,498` | ✓ `getRegion`, `updateRegion` (owner, flags, enabled), `listParcels` (picture, name, owner, area, flags) | ✓ region page with a Parcels tab | none |
| Regions › Settings: "Make coffee" | `region.php:143-147` dev placeholder | | | none |

### Services (v2 pages, legacy, meant for the wizard and the settings)

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| Flux: post type with an avatar as author, list with Author column, profile tab, posting form, load more | `class-w4os3-flux.php:41-529`, `class-w4os3-avatar.php:1014-1095` | ✓ `listPosts`, `deletePost`, the texts of the list (Flux Posts, Search Flux Posts...) | ✓ admin list, profile tab, posting form | none (load more: the list's pages) |
| Assets server: provide or not, internal URL, external server, assets base, conversion and cache | `v2/class-helpers-assets.php:81-262`, `templates/assets-render.php:70-180` | ✓ `getAssetMedia`; JPEG 2000 required, else the external server (302); settings `assets` as w4os-wp (Provide, path or address) | ◐ settings page from the engine's form; not yet its Provide and path | **P**: render Provide, the path under the site |
| Economy: provide, helper URL and ini snippets, its database, provider (Gloebit, Podex, none), rate, money script key, Podex messages | `v2/class-helpers-economy.php:52-407` | ✓ settings `economy` ported from w4os-wp (order, words, conditions, Gloebit notes, `meta.snippets`); provider, database and Provide defaults wait on Olivier | ◐ settings page from the engine's form; not yet Provide, path, notes, help lists, snippets | **P**: render them |
| Search engine: provide, database, search and register URLs with their snippets, HYPEvents URL, parser every 5 minutes | `v2/class-helpers-search.php:60-271`, `v1/cron.php:4-56` | ✓ settings `search` ported from w4os-wp; tasks (`listTasks`, `runTask`) | ◐ settings page; ✗ tasks shown | **P**: Provide, path, snippets; the tasks (last run, run now) |
| Destinations guide: provide, source, URL and snippet | `v2/class-w4os-guide.php:53-228` | ✓ settings `guide` ported from w4os-wp (source, its help, sample, URL snippet) | ◐ settings page; guide page | **P**: Provide, path, snippets |
| Offline messages: provide, sender address (never saved: a bug), URL and snippet | `v2/class-helpers-offline.php:46-168` | ✓ settings `offline` ported from w4os-wp (the sender saved) | ◐ settings page | **P**: Provide, path, snippets |
| Addresses and ini snippets the viewers and simulators need | on each service page | ✓ `getAddresses` | ✓ on the Health page | none |

### Front end

| w4os-wp feature | Where (speculoos) | Engine | Grav | Gap |
| --- | --- | --- | --- | --- |
| Profile page `/profile/First.Last`, own profile, creation form, login; in-viewer profile | `class-w4os3-avatar.php:77-137,983-1104`, `templates/content-page-profile.php` | ✓ | ✓ | none |
| Avatar creation with a model | `v1/profile.php:676-812` | ✓ `createAvatar`, `listAvatarModels` | ✓ | none |
| Shortcodes and blocks: avatar-profile, grid-info, grid-status, popular-places, web-search, base_url; Divi modules | `blocks/*.php` | ✓ blocks of the helpers | ✓ shortcodes; Divi is W | none |
| Avatar menu (profile, account, logout; login, register) | `user-menu.php:71-348` | | ✓ visitor menu | none |
| Grid login with name or email, landing on the profile, wp-login takeover | `v1/gridauth.php:9-144` | ✓ `signIn` | ✓ | none |
| Terms checkbox at registration | `v2/class-tos.php:29-36` | | ✓ | none |
| Flux tab of a profile, posting | see Flux | ◐ | ◐ | see Flux |
| Userless login (`custom_auth_form`, `account_info`) | `class-w4os3-userless-auth.php` dev, proof of concept | | n/a | none |

### The gaps, by priority

Checked again 2026-11-19 against charm-dev: engine 8d0d7a7, helpers 8cafe24, grav-plugin-w4os 2c3c2e3, w4os-views 44b25a8. Done since:
the services settings pages and the Flux admin list in the plugin, the dashboard notice, the popular places options; in the engine,
the five pages ported from w4os-wp v2, who handles a service (`meta.service`), the texts of the lists. Left for the beta:

1. **The services pages as the engine gives them now** (P, w4os-views): Provide (a switch), the path under the site (`path`, `base`
   site), the notes (`note`), the help in points (`description` a list), the ini lines (`meta.snippets`).
2. **Routing by who handles a service** (P): `meta.service.handledBy`, the helpers under their base, the site's page with its block
   elsewhere (the health checks it, `data-helpers-block`).
3. **The search tasks** (P): last run, run now (`listTasks`, `runTask`), beside the search settings.
4. **Five choices of Olivier** on the services pages (E): Provide's default, what off shows, the path, the economy provider, the
   economy database; each a small commit once decided.

After the beta: the connections through the console of Robust (E, if time allows), bulk actions and the bases of the helpers and
assets as routes (P, low), the installation wizard (not a priority), the WP bridge (its column), a cache of the settings
choices (maybe per user: pending changes, restart needed) and the case of an engine without write access to the ini, a
dashboard whose cards can be rearranged, shown, hidden and taken from any defined block, the pick list of an avatar as a
source of the destination guide.

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
