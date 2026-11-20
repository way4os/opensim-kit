# The three w4os admins, page by page

For a side by side review on three screens: the Grav admin of the mini site, w4os-wp on speculoos.world, and the pre-v3 w4os-wp
on w4os.org. For each page: keep the best choice, every feature and every piece of information, and change only what deserves it.
The parity table is [W4OS-PARITY.md](W4OS-PARITY.md).

| Column | Site | Version | Admin base |
| --- | --- | --- | --- |
| Grav | ursull (dev) | grav-plugin-w4os charm-dev, engine charm-dev | http://192.168.8.69:8830/w4os |
| speculoos | speculoos.world | w4os-wp branch `speculoos` (v2 pages + v3 classes) | https://speculoos.world/w/wp-admin/ |
| w4os.org | w4os.org | w4os-wp 2.9.3 (pre-v3; its v3 pages are marked "(dev)") | https://w4os.org/wp-admin/ |

A WordPress page is `admin.php?page=<slug>` under its admin base. A dash means the page does not exist there.

## Dashboard and status

| Page | Grav | speculoos | w4os.org | Notes |
| --- | --- | --- | --- | --- |
| Dashboard | [/w4os](http://192.168.8.69:8830/w4os) | [Dashboard](https://speculoos.world/w/wp-admin/) (widgets) | [Dashboard](https://w4os.org/wp-admin/) (widgets) | grid info, grid status, recent avatars, counts |
| Status / Health | [/w4os/health](http://192.168.8.69:8830/w4os/health) | [Status](https://speculoos.world/w/wp-admin/admin.php?page=w4os) | [Status](https://w4os.org/wp-admin/admin.php?page=w4os) | pages and URLs checked, ini snippets, admin notices |

## Grid

| Page | Grav | speculoos | w4os.org | Notes |
| --- | --- | --- | --- | --- |
| Simulators | [/w4os/simulators](http://192.168.8.69:8830/w4os/simulators) | - | - | new in Grav (start, stop, usage) |
| Regions | [/w4os/regions](http://192.168.8.69:8830/w4os/regions) | [Regions](https://speculoos.world/w/wp-admin/admin.php?page=w4os-regions) | [Regions (dev)](https://w4os.org/wp-admin/admin.php?page=w4os-regions) | columns, views, sorts, filters |
| A region, its parcels | from the list (edit) | from the list (Edit) | from the list (dev) | owner, flags, parcels tab / sidebar |

## Avatars

| Page | Grav | speculoos | w4os.org | Notes |
| --- | --- | --- | --- | --- |
| Avatars list | [/w4os/avatars](http://192.168.8.69:8830/w4os/avatars) | [Avatars](https://speculoos.world/w/wp-admin/admin.php?page=w4os-avatars) | [Avatars (dev)](https://w4os.org/wp-admin/admin.php?page=w4os-avatars), [Users](https://w4os.org/wp-admin/users.php) | views with counts, roles, columns |
| New avatar | [/w4os/avatars/new](http://192.168.8.69:8830/w4os/avatars/new) | - (front-end profile page) | - (front-end profile page) | names checked while typed, model choice |
| An avatar | from the list (edit) | profile modal | user profile | role, email, password, picture |
| Avatar models | [/w4os/models](http://192.168.8.69:8830/w4os/models) | [Avatar Models](https://speculoos.world/w/wp-admin/admin.php?page=w4os-models) | [Avatar Models](https://w4os.org/wp-admin/admin.php?page=w4os-models) | match rule, preview |
| Avatars settings | - (WordPress only) | [Avatars › Settings](https://speculoos.world/w/wp-admin/admin.php?page=w4os-avatars&tab=settings) | - | WP accounts, multiple avatars |
| Flux | [/w4os/flux](http://192.168.8.69:8830/w4os/flux) | [Flux](https://speculoos.world/w/wp-admin/edit.php?post_type=flux_post) | - | posts list, author, delete |

## Services

| Page | Grav | speculoos | w4os.org | Notes |
| --- | --- | --- | --- | --- |
| Economy | [/w4os/economy](http://192.168.8.69:8830/w4os/economy) | [Economy](https://speculoos.world/w/wp-admin/admin.php?page=w4os-economy) | [Economy](https://w4os.org/wp-admin/admin.php?page=w4os-economy) | provide, module, rate, banker, key, Podex |
| Search Engine | [/w4os/search](http://192.168.8.69:8830/w4os/search) | [Search Engine](https://speculoos.world/w/wp-admin/admin.php?page=w4os-search) | [Search Engine](https://w4os.org/wp-admin/admin.php?page=w4os-search) | engine and web URLs, registers, database, tasks |
| Destination Guide | [/w4os/guide](http://192.168.8.69:8830/w4os/guide) | [Destinations Guide](https://speculoos.world/w/wp-admin/admin.php?page=w4os-guide) | [Destinations Guide](https://w4os.org/wp-admin/admin.php?page=w4os-guide) | source, path |
| Offline Messages | [/w4os/offline](http://192.168.8.69:8830/w4os/offline) | [Offline Messages](https://speculoos.world/w/wp-admin/admin.php?page=w4os-offline) | [Offline Messages](https://w4os.org/wp-admin/admin.php?page=w4os-offline) | V1/V2, sender, database |
| Web Assets | [/w4os/assets](http://192.168.8.69:8830/w4os/assets) | [Assets Server](https://speculoos.world/w/wp-admin/admin.php?page=assets-server) | [Assets Server](https://w4os.org/wp-admin/admin.php?page=assets-server) | provide, path or external URL |

## Site

| Page | Grav | speculoos | w4os.org | Notes |
| --- | --- | --- | --- | --- |
| Shortcodes | [/w4os/shortcodes](http://192.168.8.69:8830/w4os/shortcodes) | [Shortcodes](https://speculoos.world/w/wp-admin/admin.php?page=w4os-shortcodes) | [Shortcodes](https://w4os.org/wp-admin/admin.php?page=w4os-shortcodes) | names, options, previews |
| Site settings | [/w4os/settings](http://192.168.8.69:8830/w4os/settings) | [Settings](https://speculoos.world/w/wp-admin/admin.php?page=w4os-settings), [v2 Settings](https://speculoos.world/w/wp-admin/admin.php?page=w4os_settings) | [Settings](https://w4os.org/wp-admin/admin.php?page=w4os_settings) | connection, pages, brand, theme |
| Installation wizard | - (`w4os-site setup` on the command line) | [Installation Wizard](https://speculoos.world/w/wp-admin/admin.php?page=w4os_installation_wizard) | - | not a priority for the beta |

## How to read a row

- Grav pages need a manager or god session on ursull; WordPress pages need an administrator.
- w4os.org's "(dev)" pages appear only with its v3 option on; they are the first draft of what speculoos has now.
- What differs on purpose is in W4OS-PARITY.md (W: WordPress only; the decisions dated there).
