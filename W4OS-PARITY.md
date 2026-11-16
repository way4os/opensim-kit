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
WordPress plugin to come, so nothing is done there yet: its column is the plan of the port. Updated 2026-11-16, from the code of
opensim-engine and opensim-helpers on charm-dev and of grav-plugin-w4os.

## Public content (the content comes from H; the shell is a shortcode, a block, a Twig or Blade template)

| Feature | Home | State | Grav | WP | Milestone |
| --- | --- | --- | --- | --- | --- |
| Grid info, grid status, popular places, web search | H blocks | ✓ | ✓ `[w4os block=…]`, the home page laid out like the splash | ✗ | |
| Destinations guide | H | ✓ read from `GuideSource` of `Helpers.ini` only, no address from the request | ✓ `[w4os block="destination_guide"]` | ✗ | 3.0 |
| Avatar profile (page, block, mini profile) | H (block), E (data: picks, classifieds, groups, `GET /avatars/{id}/picks`, `/classifieds`, `/groups`) | ✓ the viewer's tabs, always all (2nd Life, Web, Interests, Picks, Classifieds, 1st Life; None. when empty); picture in its proportions; name, key, birth and age, account, partner, groups; picks and classifieds as the viewer (list, picture, place, teleport link); public only when published; tab in the address | ✓ `/profile/First.Last` (the helpers' block, bare for a viewer), Edit button for its owner or a manager | ✗ | 3.0 |
| **Web assets server** (`/assets/<uuid>.<format>`: in-world pictures on a site) | E (`WebAssets`, API `/assets/{file}`), H route | ✓ converted from the asset service or the database, kept a day, placeholder | ✓ `/grid-assets/<uuid>.jpg` in bundled mode, the API's in remote mode | ✗ | **3.0** |
| Logos of w4os and OpenSimulator, logo of the grid (`LogoURL`) | E (`Logos`, API `/logos/{name}`, `logo` of `/info`) | ✓ | ✓ footer, top bar, favicon | ✗ | 3.0 |
| New users (block, dashboard widget) | H (`new_users`) | ✓ newest people, names linking to their profile; models, technical accounts, accounts without email and disabled ones left out | ✓ on the managers' dashboard | ✗ | 3.0 |
| Dashboard widgets (grid info, status, new users) | content H, shell W and M | M shows placeholders | ◐ managers' dashboard `/account/dashboard` (first entry of the admin menu), its blocks listed in its page: new users now, grid health later; Grav's admin not needed | ✗ | 3.1 |
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
| Regions list: owner, simulator, presence, control, port, size, last seen; filters by owner and simulator | E | ◐ `GET /regions` gives them; filters online, hyperlink and by simulator, not by owner | ✗ | ✗ | 3.0 |
| Regions list; start, stop, create a region | E, K, M | ✓ in E and K; M in 3.0 | ✗ | ✗ | 3.0 |
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
