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

## Public content (the content comes from H; the shell is a shortcode, a block, a Twig or Blade template)

| Feature | Home | State | Milestone |
| --- | --- | --- | --- |
| Grid info, grid status, popular places, web search | H blocks | ✓ | |
| Destinations guide | H | ◐ `Guide::read()` takes any file or URL (w4os fixed an SSRF in `fb665fcf`) | 3.0 |
| Avatar profile (page, block, mini profile) | H | ◐ no picture: needs the web assets | 3.0 |
| **Web assets server** (`/assets/<uuid>.<format>`: in-world pictures on a site) | H | ✗ | **3.0** |
| New users (block, dashboard widget) | H | ✗ trivial, whenever wanted | 3.1 |
| Dashboard widgets (grid info, status, new users) | content H, shell W and M | M shows placeholders | 3.1 |
| Activity feed, statuses, in-world feed tab (Flux) | `opensim-flux` | ✗ | 3.2 |
| The shortcodes, blocks, widgets, Divi modules, avatar menu, localized pages | W | | |

## Avatars and accounts (the engine; the users of the site are WordPress's or Laravel's)

| Feature | Home | State | Milestone |
| --- | --- | --- | --- |
| Create an avatar: names cleaned (accents out, letters and digits only, first letter capital), restricted names (Default, Test, Admin, the model pattern) refused, taken name refused | E | ◐ `AccountWriter` writes the account, the rules are not there | 3.0 |
| **Avatar models**: the list to choose from at registration (name and profile picture), what makes a model (a name pattern or a fixed list of avatars), the copy of its appearance and inventory into the new avatar | E (+ H for the picture) | ✗ only the default outfit (`AccountWriter`); the model accounts themselves are made by hand from the console | **3.0** |
| Change or reset the password of an avatar | E | ✗ | 3.0 |
| Accounts in bulk | K | ✓ (to try with a viewer) | 3.0 |
| Sign in with avatar name and password | E `SignIn` | ✓ (the plugin should call it, not its own SQL) | |
| Counts and lists that leave out models, accounts without email, visitors of other grids | E | ✗ | 3.1 |
| Avatars list: filters, profile in a modal, last seen | M, E | ◐ sort, search, columns | 3.1, with the admin interface |
| Regions list; start, stop, create a region | E, K, M | ✓ in E and K; M in 3.0 | 3.0 |
| User and avatar linked, several avatars for one user, sync by hand or cron, terms accepted at registration, roles | W (its users), M (its users) | each has its own | |

## Management and services

| Feature | Home | State | Milestone |
| --- | --- | --- | --- |
| Search: places, land for sale, classifieds, events (2do) | H | ✓ | |
| Offline messages by mail | H | ✓ | |
| Currency: core money module, Gloebit | H | ✓ | |
| Currency: Podex, PayPal and their settings (messages, URLs) | H | ◐ code kept, no setting; analysis first (see below) | later |
| Message of the day, splash page, texture from a URL (`textgen`), HYPEvents | H | ✓ | |
| URLs and requirements check | H `health`, K `status` | ✓ | |
| Installation wizard; migration of the old settings (2to3) | K; E and H | ✓ wizard; ◐ migration of the old `config.php` | 3.0 |
| Connections: databases, credentials, read from the files or the console | E | ◐ files of the grid; remote grids by console | 3.1 |
| Languages (cy, de, fr, it, nl, pt_BR) | all | ◐ helpers have all but nl, the manager de, en, fr, nl | 3.1 |
| Gudz Teleport Board | H | in no version yet | later |

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
