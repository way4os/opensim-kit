# What w4os does, and where the suite stands

A feature of w4os is not removed from 3.x. The suite (engine, helpers, kit, manager) takes over a feature before the plugin drops its
own copy of it: **a row below is ✓ before the matching code leaves the plugin.** The plugin keeps what is WordPress (shortcodes, blocks,
widgets, WooCommerce, the users of the site); the rest is what the suite has to offer.

Three versions were compared, by their code, not by their documentation: **2.8** (tag `v2.8`, wordpress.org), **2.9.4** (tag `v2.9.4`,
last stable on GitHub, adds the v3 preview) and **speculoos** (branch `speculoos`, 3.0.1-dev, in service on speculoos.world).

- Integration points (shortcodes, blocks, widgets, admin pages, hooks, cron, post types, ajax) and the ~190 options of 2.8: all of
  them are still in 2.9.4 and in speculoos (only 3 internal options, `w4os_add_crossorigin`, `w4os_css_classes_body` and
  `w4os_db_connected`, are not options any more in speculoos).
- Functions and methods: 304 (2.8), 473 (2.9.4), 558 (speculoos). 42 of 2.9.4 are not in speculoos: they were moved or renamed
  (`OpenSim_Rest` and the console methods are the engine's, `W4OS3_*` helpers were folded, the models preview, `w4os_fast_xml`...), none is
  a feature that went away (4 functions of 2.8 are gone from 2.9.4, renamed or folded). Speculoos adds `account_info` and `custom_auth_form` shortcodes, the `w4os/avatar-menu` block, the
  installation wizard, userless authentication (proof of concept) and the 2to3 migration.
- Not checked by running the plugin: the Divi modules, WooCommerce flows, the multilingual page lookup, the rendering of every block.

Legend: **W** stays in the plugin · **H** helpers · **E** engine · **K** kit (CLI) · **M** manager. ✓ done · ◐ partly · ✗ missing.

## Public pages

| Feature | Versions | In the suite | |
| --- | --- | --- | --- |
| Grid info (shortcode, block, widget) | all | H block `grid-info` | ✓ (shortcode, widget: W) |
| Grid status (users online, regions...) | all | H block and page `grid_status` | ✓ |
| Popular places | all | H block | ✓ |
| Web search (block, page) | all | H block `search` | ✓ |
| Destinations guide | 2.7+ | H `guide` | ◐ reads any file or URL it is given (see below) |
| Avatar profile (page, block, mini profile) | all | H block `profile` | ◐ no picture in its code: needs the web assets |
| Web assets server (`/assets/<uuid>.<format>`: in-world pictures on a site) | all | none | ✗ |
| New users (shortcode, dashboard widget) | all | none | ✗ |
| Dashboard widgets (grid info, status, new users) | all | M dashboard shows placeholders | ✗ |
| Activity feed, statuses, in-world feed tab (Flux) | 2.9.x preview | `opensim-flux`, planned | ✗ (3.2) |
| Avatar menu, user menu, registration page in the menu | speculoos | W | W |
| Divi modules, localized pages (WPML, Polylang) | all | W | W |

## Avatars and users

| Feature | Versions | In the suite | |
| --- | --- | --- | --- |
| Create an avatar from the web: names cleaned (accents out, letters and digits), first letter capital, restricted names refused, taken name refused | all | E `AccountWriter` writes the account; the rules are not there | ◐ |
| Avatar models: the default outfit chosen at registration, models with a picture, hidden from lists | all | E gives the default outfit only (`AccountWriter`), no choice | ◐ |
| Password of the avatar kept in step with the site account; change it | all | E hashes at creation; no command or form changes it | ◐ |
| Accounts in bulk | new | K `opensim import --users` | ✓ (to try with a viewer) |
| Sign in to a site with avatar name and password, account made when missing | all | M, three ways (private port, OpenID, viewer login) | ✓ |
| Several avatars for one user | speculoos | M `users.avatars` | ✓ |
| Terms of service accepted at registration | all | none | W (to carry to a public registration of the manager) |
| Users and avatars kept in step by hand or by cron | all | W | W |
| Avatars list: sorted, filtered, searched, profile in a modal, columns and last seen | 2.9.x preview | M list: sort, search, columns (level, created, active, flags, title, UUID); no filter, no modal, no last seen | ◐ |
| Counts and lists leave out models, accounts without an email, visitors from other grids | all | none known in H or M | ✗ (to check) |
| Regions list | 2.9.x preview | M | ✓ |
| Start, stop, create a region from the site (Robust console) | speculoos | K; M in 3.0 | ◐ |
| WooCommerce: avatar tab, registration fields, paid accounts | all | none | W |

## Services for viewers and simulators

| Feature | Versions | In the suite | |
| --- | --- | --- | --- |
| Search: places, land for sale, classifieds, events (2do) | all | H `query`, `register`, `parser`, `eventsparser` | ✓ |
| Offline messages by mail, sender | all | H `offline` | ✓ |
| Currency: core money module, Gloebit | all | H `currency`, `landtool` | ✓ |
| Currency: Podex, PayPal, their settings (messages, URLs) | all | H keeps the code, no setting | ◐ (decided) |
| Message of the day, splash page, texture from a URL (`textgen`) | new | H | ✓ |
| Events from HYPEvents | all | H | ✓ |
| URLs check (login, helpers, assets answer) and requirements page | all | H block `health` (checks in the engine), K `opensim status` | ✓ |
| Installation wizard, migration of the settings (2to3) | speculoos | K `opensim setup`; the 2to3 migration is W (its helpers part is to port, see ROADMAP) | ◐ |
| Connections: databases, credentials read from the console, encrypted | 2.9.x preview | E `ConfigReader`, files of the grid | ◐ (remote grids) |
| Languages: de, fr, it, nl, pt_BR, cy | all | H `locales`: cy, de, fr, it, pt_BR; M: de, en, fr, nl | ◐ |

## What the comparison found

Fixes made in w4os in 2025 that the suite does not have yet, to carry before 3.0:

- **Guide: SSRF** (`fb665fcf`): `Guide::read()` of the helpers takes any file or URL it is given. Accept an address of the grid's own setting only.
- Two other fixes concern the SQL of the plugin's own login (password hashed in PHP before the query, `dc1568d2`). The suite signs in through the private port, the password is hashed in PHP and sent as a form: nothing to carry, nothing else to check.
- Avatar name validation (`e0896cff`, `037431eb`): the rules of the first row of the avatars table, to put in the engine.
- The search address in https when the site is (`52bdd839`): to check in the setup and the helpers.

Missing, by milestone (also in `ROADMAP.md`):

- **3.0**: the guide fix; the rules of the avatar names and the restricted names where an avatar is made (CLI, manager); change the password of an avatar.
- **3.1**: the web assets server (the pictures of the profile with it) and the new-users block; a default avatar to choose (models); the filters, the modal and the last seen of the avatars list; the counts that leave out models, accounts without email and visitors; the dashboard of the manager; the Podex and PayPal settings.
- **3.2**: Flux (`opensim-flux`).
