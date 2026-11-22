# Web systems of OpenSimulator grids — market study, November 2026

Which web systems do the active OpenSimulator grids use for their websites, and how many already run w4os?

- Source list: Hypergrid Business, [Active OpenSim Grids](https://www.hypergridbusiness.com/statistics/active-grids/) (last update 2026-10-30, 324 rows; the page header says 332).
- Sizes: Hypergrid Business [October 2026 grid stats](https://www.hypergridbusiness.com/statistics/october-2026-opensim-grid-stats/) (regions, total users, active users in the past month). 194 of the 324 grids have an active-user figure; together they count **48,370 active users** and 163,425 regions.
- Crawl: 2026-11-15, read-only, public pages only (see [Method and limits](#method-and-limits)).

## Key figures

1. **253 of 324 grids (78%) have a reachable website.** 71 have none or could not be read (45 unreachable, 15 blocked by robots.txt, 11 errors or default server pages).
2. **There is no dominant CMS.** WordPress leads the CMSs with 46 grids (14%) and 11% of active users. Joomla follows with 20 grids (2.6% of actives). Drupal: 0. Grav: 1 (besides our own sites).
3. **The largest group by number is "no CMS at all":** 87 grids (27%) use a grid web UI, mostly **Diva Wifi** (78 grids, 42 of them DreamGrid bundles served on port 8002). With static pages, site builders and grid-server-only grids, about 120 grids (37%) have no real website system.
4. **The largest group by audience is custom code:** 47 grids, **64% of active users** (50% without Wolf Territories, which alone weighs 29% of all actives). Wolf Territories, OSgrid, Groovy Verse, GBG World and Alternate Metaverse all run their own PHP.
5. **w4os runs on 15 grids:** 6% of reachable grids, **one WordPress grid in three**, 1,757 active users (3.6% of all actives, 5.1% without Wolf Territories). Neverworld (1,211 actives) is the flagship.

## Summary by family

Shares are over all 324 listed grids; "% actives" is weighted by the October active users (48,370).

| Family | Grids | % grids | Active users | % actives | % actives without Wolf | Biggest grids (actives) |
|---|---:|---:|---:|---:|---:|---|
| Custom code (own PHP, Laravel, CodeIgniter, JS apps…) | 47 | 15% | 31,172 | 64.4% | 50.1% | Wolf Territories (13,908), OSgrid (4,013), Groovy Verse (3,848), GBG World (2,748) |
| WordPress | 46 | 14% | 5,348 | 11.1% | 15.5% | DigiWorldz (1,815), Neverworld (1,211, w4os), Sciattisi (1,048), Kurtopia (263, w4os) |
| Grid web UI (Diva Wifi, osWebInterface, Modlos…) | 87 | 27% | 4,282 | 8.9% | 12.4% | Cave Grid (1,005), Free Life (479), Jungle Friends (362), Big City (212) |
| Hosted grid panel (Hosting4Opensim, 3D Worldz, Kitely) | 13 | 4% | 2,951 | 6.1% | 8.6% | Friends Grid (743), AviWorlds (696), Funny Rides (420), LinkUp (342) |
| Other CMS (Concrete CMS, Grav) | 2 | 1% | 1,443 | 3.0% | 4.2% | Darkheart's Playground (1,443, Concrete CMS) |
| Joomla | 20 | 6% | 1,251 | 2.6% | 3.6% | Trianon World (764), Party Destination (180), AusVirtual (98), Dorena's World (89) |
| Site builder (Wix, Google Sites, Weebly, GoDaddy…) | 13 | 4% | 274 | 0.6% | 0.8% | Twiztid Timez (159), Utopia Skye (61), Accueil Dracsoom (37) |
| Static HTML | 25 | 8% | 56 | 0.1% | 0.2% | Planet 3L (32), The Shirls Hypergrid (12) |
| No site / error | 71 | 22% | 1,593 | 3.3% | 4.6% | Astralia (341, robots.txt), Herederos (243, unreachable), Exotic Realities (140, Cloudflare 523) |

Among the 50 grids with at least 100 active users: 13 custom code, 12 grid web UI, 8 WordPress (3 with w4os), 6 hosted panels, 2 Joomla, 1 other CMS, 1 site builder, 7 with no readable site.

### Summary by system

| System | Grids | Active users | % actives | Biggest grids |
|---|---:|---:|---:|---|
| Custom PHP | 41 | 29,619 | 61.2% | Wolf Territories, OSgrid, Groovy Verse, GBG World, Alternate Metaverse, New Life Italy, BloodMoon, Craft World |
| WordPress (with w4os) | 15 | 1,757 | 3.6% | Neverworld, Kurtopia, Ansjela Grid |
| WordPress (without w4os) | 31 | 3,591 | 7.4% | DigiWorldz, Sciattisi, Barefoot Dreamers, Dynamic Worldz |
| Diva Wifi (incl. 42 DreamGrid bundles) | 78 | 3,994 | 8.3% | Cave Grid, Free Life, Jungle Friends, Big City, Dark Angel |
| Hosting4Opensim panel (Laravel) | 9 | 2,692 | 5.6% | Friends Grid, AviWorlds, Funny Rides, LinkUp, Gentle Fire |
| Concrete CMS | 1 | 1,443 | 3.0% | Darkheart's Playground |
| Joomla (14 of them with jOpenSim) | 20 | 1,251 | 2.6% | Trianon World, Party Destination |
| JS app (SvelteKit, Flutter, Angular, Vite) | 4 | 1,204 | 2.5% | AvatarLife, SpaceGrid |
| CodeIgniter | 1 | 345 | 0.7% | ZetaWorlds |
| Kitely (own platform) | 1 | 259 | 0.5% | Kitely |
| osWebInterface | 4 | 172 | 0.4% | Second Sanctuary |
| Google Sites | 4 | 165 | 0.3% | Twiztid Timez |
| Moodle + Modlos | 1 | 103 | 0.2% | Japan Open Grid |
| Wix | 3 | 135 | 0.3% | Utopia Skye (one Wix site is a "domain not connected" error) |
| Other site builders (GoDaddy, WebAcappella, Sandvox, FrontPage, Weebly, Blogger) | 7 | 48 | 0.1% | Accueil Dracsoom |
| 3D Worldz panel (ASP.NET) | 3 | 0 | 0% | 3D Worldz, Creo Mundos, Sovaria Estates |
| Grid server only (Robust answers, no page) | 4 | 13 | 0% | Virtual Travelers |
| SledjChisl (C, FastCGI) | 1 | 4 | 0% | Infinite Grid |
| Grav | 1 | 0 | 0% | 4Creative |
| Static HTML | 25 | 56 | 0.1% | Planet 3L |

## w4os adoption

**15 grids run w4os** (detected by the plugin's assets, blocks or classes in the HTML, and/or by its public `readme.txt`).

| Grid | Actives | Regions | Install | Seen on the home page | Notes |
|---|---:|---:|---|---|---|
| Neverworld | 1,211 | 3,546 | GitHub (`w4os`, dev build) | yes | WooCommerce, OpenSim world map plugin; the largest w4os grid |
| Kurtopia | 263 | 80 | GitHub (`w4os`) | yes, and on the register page | |
| Ansjela Grid | 214 | 46 | WordPress.org | yes | **closes on 22 December 2026** (farewell notice on the home page) |
| Speculoos | 21 | 23 | own folder (`w4os-2.x`) | yes | the w4os author's grid |
| OliGrid | 15 | 26 | WordPress.org | yes | Elementor, OpenSim map plugin |
| Carnivale Grid | 12 | 39 | GitHub (`w4os`, dev build) | yes, and on the register page | |
| Virtual Harmony in the Cloud | 11 | 21 | GitHub (`w4os`) | no (readme only) | installed, not visible on the home page |
| Ankabi | 8 | 73 | WordPress.org | yes, and on the register page | Elementor Pro, WP User Manager |
| Rainbow Grid | 1 | 113 | WordPress.org | yes (grid info shortcode) | Breakdance |
| Sheikah | 1 | 5 | WordPress.org | yes | |
| Sense Limits | 0 | 185 | WordPress.org | yes | |
| Maze of The Mind | — | 196 | WordPress.org | no (readme only) | installed, not visible on the home page |
| Migrating Coconuts | — | 267 | GitHub (`w4os`, dev build) | yes | |
| Six Sides | — | — | both folders | no (readme only) | |
| SV3D | — | — | WordPress.org | yes | site in sv3d.fr/wordpress; Francopholie (same owners) now shows a company page |

- 15 grids = 6% of the 253 reachable grids, 33% of the 46 WordPress grids.
- 1,757 active users = 3.6% of all actives; Neverworld alone is 69% of the w4os audience.
- 3 of the 50 grids with 100+ actives run w4os (Neverworld, Kurtopia, Ansjela), and Ansjela closes in December.
- 9 installs come from WordPress.org (`w4os-opensimulator-web-interface` folder), 6 from GitHub (`w4os` folder, 3 of them dev builds; Six Sides has both), 1 from the author's own branch (`w4os-2.x`): the GitHub channel matters almost as much as the directory.
- 3 grids have it installed without any visible use on the home page (probably helpers or profiles only, or a test).

## Other grid-specific web tools seen

| Tool | Grids | Active users | Notes |
|---|---:|---:|---|
| Diva Wifi | 78 sites + 9 sites that send registration to a Wifi page | 3,994 | mostly small home-hosted grids; 42 are DreamGrid (Outworldz) bundles with the same template |
| jOpenSim (Joomla component) | 14 | 1,246 | Trianon World, Party Destination, AusVirtual, Dorena's World, Freedom Grid… |
| Hosting4Opensim panel ("H4HUB", Laravel) | 9 | 2,692 | turnkey grid + website sold by a host: land/region panel, parcel reservations, support tickets, events, gallery, character choice at signup |
| osWebInterface | 4 | 172 | `oswebinterface/welcomesplashpage.php`, German-speaking grids |
| 3D Worldz panel (ASP.NET) | 3 | — | same template, hosted by 3D Worldz |
| Moodle + Modlos | 1 | 103 | Japan Open Grid |
| Own platforms | 2 | 976 | Kitely (GWT), AvatarLife (SvelteKit, Web3) |

## Hosting4Opensim (H4HUB / H4OS), a closer look

Read on 2026-11-15 from public pages only: [hosting4opensim.com](https://hosting4opensim.com/) (home, [H4OS overview](https://hosting4opensim.com/h4os.html), [about](https://hosting4opensim.com/help.html)), the [H4HUB store](https://store.h4hub.com/index.php/store/grid-packages), the [demo grid site](https://testsite.hosting4opensim.com) and the customer grids' home pages. No login, no sign-up.

**What it is.** A hosting company, not a software product. Founded in December 2022 by two friends (CEO in Mexico, CTO in Brazil), it says it hosts "10+" grids. H4OS is its in-house management system: a Laravel site (`XSRF-TOKEN` + per-site session cookie, Bootstrap 5, jQuery, TinyMCE, reCAPTCHA) installed for each grid on the company's servers. 8 of the 9 customer sites and the demo resolve to the same Hetzner address (5.9.111.110), the 9th to a second Hetzner address; LinkUp sits behind Cloudflare. The store is WHMCS. It also sells AviWorlds region rentals, so AviWorlds and H4HUB are closely linked.

**How a grid gets it.** Only with the hosting: the website comes inside the grid package, it is not sold or distributed on its own.

- Grid package (Robust + region server): **from $100/month**. That buys 20 GB RAM, 4 cores, 200 GB NVMe, 200 GB object storage for assets, 1 TB backup drive, hourly backups with 14-day rollback, SSH/RDP access, 2 free regions up to 8×8, own domain, unlimited support.
- Extra regions: $12.50/month per 5 regions (any size up to 8×8), only for grids hosted there. Everything is non-refundable.

**Open source?** No. There is no public repository for H4OS, Hosting4Opensim or H4HUB on GitHub (repo and code searches on the names and on its menu labels found nothing), and no license is stated. The only open piece is the **registration page**: a separate PHP app that reuses [djphil/osregister](https://github.com/djphil/osregister) ("OpenSim Register v0.1", same template, comments and avatar-model picker). That project is GPL-3.0 in its LICENSE file but CC-BY-NC-SA 4.0 in its README. Its last commit is from 2020-08-20, it has 4 stars and 7 forks, and it creates accounts by writing straight into the Robust database (`UserAccounts`, `auth`, inventory tables).

**How it talks to OpenSim.** The code is closed, so this is inferred from the features:
- direct access to the Robust database (account login with the grid username and password, registration through osregister, the stats in `/api/gridstatus`);
- a server-side agent on the region servers, which it controls (region reboot, OAR save/load, map generation, HTTPS certificates, "Region Guardian" health checks).

That works because H4HUB runs every server itself. It is not a model a third party can install.

**Features seen** (the 9 grids' menus, the demo and the H4OS page):

- **Public site**: home with slideshow and news, events, gallery, blog, forum, how to connect, map, free parcels, grid status with a JSON endpoint, terms/privacy/DMCA, dark/light theme, PWA manifest.
- **Accounts**: registration with a starting avatar picker (male/female/neutral preview), login with the grid credentials, password reset, reCAPTCHA.
- **Residents and region owners**: "My regions", Region Panel (reboot, backup/restore, map), Parcel Reservation system, friends, groups and offline IMs, support tickets, partner system (marriage/divorce), money module (Podex, Gloebit or private currency).
- **Grid owner (UserLevel 250+)**: Deploy Region on a coordinate grid that shows free spaces and buffers, OAR manager (save, upload, place, strip ownership for publishing), crashed/offline region cleanup, ROBUST logs and "login finder", MAC/URL access control, registration access, maintenance status, email campaigns with unsubscribe, page and news editing.

**Lessons for w4os**

What it does well, to match or beat:
- **One package, nothing to assemble.** Site, admin, region tools and backups arrive together. This is what made 9 grids with 2,692 actives choose it, several of them among the 25 biggest.
- **Region-owner self-service.** Reboot, backup/restore and map generation without asking staff. w4os has nothing like it today, and the engine API is the right place for it.
- **Placing a region on the map:** free coordinates, collision and buffer checks. Small, very useful, rarely seen elsewhere.
- **Avatar choice at signup**, land and parcel reservations, events, tickets, maintenance banner, mailing campaigns: the "running a grid" layer, not only the "showing a grid" layer.
- **A public JSON grid-status endpoint.**

What it lacks:
- **Openness.** It is closed and only exists inside one host's hosting: a grid that leaves H4HUB leaves without its website, its accounts flow and its tools.
- **A real design system.** All sites share one template with the same menu and footer, so there is little brand freedom.
- **Real multilingual content**: none visible, only the English UI.
- **Hypergrid-friendly search and profiles shared across grids**: none visible.
- **Its foundations are dated:** registration on a 2020 PHP app writing raw SQL, Bootstrap 5.2 beta, jQuery 3.4.

Where w4os can stand apart:
- **Open source and no lock-in.** Install it anywhere, move hosts, keep the site.
- **One engine API for everything.** The same accounts, regions, search, profiles and currency behind w4os-wp, the light w4os-site and w4os-app, instead of one closed portal per host.
- **A light install for the ~120 grids with no real site**, and a full app (w4os-app) that can offer the same region-owner and grid-owner tools to any host, including H4HUB's own customers if they leave.
- **Proper i18n**, brand freedom (any WordPress theme, Grav theme or app theme), and hypergrid-wide services (search, profiles, helpers) that a single-host panel does not try to provide.

## What grids need from a website

Keyword scan of the home page and one registration page of the 243 grids whose pages had content. It only sees what is public, so features behind a login are under-counted.

| Feature | Grids | Share |
|---|---:|---:|
| Login / my account | 185 | 76% |
| Registration | 180 | 74% |
| Grid status / statistics (online, regions, actives) | 169 | 70% |
| Terms, privacy, DMCA | 161 | 66% |
| Viewer setup / how to connect (login URI, Firestorm) | 154 | 63% |
| Password reset | 111 | 46% |
| Search / destinations / popular places | 103 | 42% |
| Events / calendar | 85 | 35% |
| Marketplace / shop | 83 | 34% |
| News / blog | 82 | 34% |
| Map | 73 | 30% |
| Forum / chat (often Discord) | 68 | 28% |
| Profiles | 52 | 21% |
| Land / region rentals | 42 | 17% |
| Multilingual / translation | 41 | 17% |
| Donations | 29 | 12% |
| Currency / money | 27 | 11% |
| Avatar choice at registration | 23 | 9% |

What stands out, from reading the bigger sites:

- **The core is the same everywhere:** create an account, log in, reset a password, show that the grid is up (users online, regions, 30-day actives), explain how to connect a viewer.
- **Discovery is the second layer:** destinations or popular places, events calendar, map. The big grids (OSgrid, Wolf, GBG, BloodMoon) all have them.
- **Commercial grids add land and money:** region rentals, a land panel, currency purchase, marketplace, donations (Hosting4Opensim panel, Wolf, GBG, BloodMoon, Neverworld with WooCommerce).
- **Avatar choice at signup** appears on the most polished sites (AvatarLife, Friends Grid and the other Hosting4Opensim grids, Virtualife through Wifi), and is still rare.
- **Translation** is mostly done with Google Translate widgets, not real multilingual content (OSgrid, Wolf, BloodMoon, New Life Italy). Craft World and Japan Open Grid are truly multilingual.
- **Trust pages** (terms, privacy, DMCA, GDPR) are on two sites in three.

## What it means for w4os

- **WordPress is the only CMS worth a plugin today**, and w4os already holds a third of it. Growth inside WordPress means converting the other 31 WordPress grids (DigiWorldz, Sciattisi, Barefoot Dreamers…).
- **A Joomla plugin would target 20 grids and 2.6% of actives**, most of them on jOpenSim. Worth checking jOpenSim's maintenance state before deciding; it is the only other CMS with a visible OpenSim tool.
- **No case for Drupal** (0 grids) or other CMSs (Concrete: 1, Grav: 1).
- **The light mini site targets the biggest group by count:** about 120 grids on Diva Wifi, static pages, site builders or nothing. They want registration, status and "how to connect" with no CMS to run. DreamGrid alone ships Wifi to 42 grids.
- **The engine API is the way to the big audiences:** the custom-code grids hold 64% of active users and will not change CMS, but could use shared services (registration, search, profiles, currency helpers).
- **Hosting4Opensim shows the turnkey demand** (9 grids, 5.6% of actives, several of them among the 25 biggest): grid hosting plus a ready website with land panel and avatar choice. That is the space of w4os-app.

## Method and limits

- **List and sizes.** 324 rows parsed from the Hypergrid Business active grids page (the page header announces 332). Sizes from the October 2026 stats table; 130 listed grids have no active-user figure (not in the stats or blank), so they weigh zero in the weighted shares. Actives are self-reported by the grids or gathered by Hypergrid Business, and the weighting is dominated by a few grids: Wolf Territories alone is 29% of all actives, so the shares are also given without it.
- **Crawl.** Python `urllib`, user agent `grid-web-survey/1.0; read-only market study`, 15 s timeout, one worker per host (never two requests to one site at a time), 0.5–1 s pause between requests to the same site. Per grid: `robots.txt`, the listed URL, the first registration-like link found on it (register, sign up, join, create account, `/wifi`…), and for WordPress sites a probe of `/wp-content/plugins/{w4os-opensimulator-web-interface,w4os}/readme.txt`. A second pass followed meta-refresh redirects and retried unreachable sites once. No login, no form, nothing sent.
- **Detection.** `generator` meta, `Server` / `X-Powered-By` headers, cookies (`XSRF-TOKEN`/Laravel, `MoodleSession`, Joomla hash cookie, `PHPSESSID`), asset paths (`/wp-content/`, `/media/jui/`, `/user/themes/`, `/wifi/`, `_app/immutable`…), known tool names (Wifi, jOpenSim, osWebInterface, Modlos, Hosting4Opensim "Site Credits"). The 50 biggest grids and every ambiguous result were then reviewed by hand; 19 classifications were corrected (listed in the "Signals seen" column).
- **Unreachable sites.** 45 sites reset or timed out, also from a second network (ursull), so they were down or filtering on 2026-11-15; many are home-hosted DreamGrid servers that run part-time. 15 sites forbid all crawling in `robots.txt` (Astralia, Sanctum Astra, Outworldz, Furry World…) and were left alone.
- **Guesses.** "Custom PHP" means PHP with no known framework or CMS signature: it can hide a little-known CMS. "Static HTML" means no server-side signal; some may be generated. Features are counted by keywords on two public pages, so they are a floor, not an inventory. w4os may be installed on a few more WordPress grids that block or rename the plugin folder.
- **Data files.** Raw pages, crawler and detection scripts were kept in the session scratchpad only (not in this repository). The survey can now be redone with `tools/grid-survey` (see DEVELOPERS.md); its classes follow the w4os.org page (custom interface and custom website are split by the sign-up test described there), so its totals are not the families of this study.

## All grids

Sorted by active users (grids without a figure at the end, alphabetically). "Confidence" is about the system, not the size.

| Grid | Actives | Regions | URL | System | Family | w4os | Signals seen | Confidence |
|---|---:|---:|---|---|---|:-:|---|---|
| Wolf Territories Grid | 13908 | 44564 | <https://www.wolf-grid.com/> | Custom PHP | Custom code | no | own PHP site (htmx, signups.php); Google Translate link misread as Google Sites | high |
| OSgrid | 4013 | 25009 | <https://www.osgrid.org/> | Custom PHP | Custom code | no | own PHP site (register.php, login.php), forums + wiki apart; server=cloudflare | high |
| Groovy Verse | 3848 | 18428 | <https://groovyverse.com/> | Custom PHP | Custom code | no | meta refresh to /welcome/, own PHP pages (maintenance notice); server=Apache/2.4.58 (Ubuntu) | medium |
| GBG World | 2748 | 1929 | <https://gbg-world.com/adminsim/> | Custom PHP | Custom code | no | own 'AdminSim' PHP portal, /helpers/search.php; server=Apache/2.4.52 (Ubuntu) | high |
| DigiWorldz | 1815 | 2515 | <https://www.digiworldz.com/> | WordPress | WordPress | no | WordPress: home html+headers/cookies | high |
| Alternate Metaverse | 1683 | 11190 | <https://alternatemetaverse.com/> | Custom PHP | Custom code | no | redirect to ./main/index.php, own PHP; server=Apache/2.4.71 (Ubuntu) | medium |
| Darkheart’s Playground | 1443 | 425 | <https://darkheartsos.com> | Concrete CMS | Other CMS | no | generator Concrete CMS; generator=Concrete CMS | high |
| New Life Italy | 1387 | 635 | <https://newlifeitaly.com/> | Custom PHP | Custom code | no | own portal (/public/assets, osgp cookie); x-powered-by=PleskLin; server=nginx | medium |
| Neverworld | 1211 | 3546 | <https://www.neverworldgrid.com/> | WordPress | WordPress | **yes** | generator=Unos 2.12.2,WooCommerce 10.2.4,WordPress 7.1.4; w4os: readme.txt probe; w4os: home html; WordPress: home html+register page+headers/cookies | high |
| Sciattisi Grid | 1048 | 51 | <https://sciattisigrid.it/> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html+headers/cookies | high |
| Cave Grid | 1005 | 21 | <http://cavegrid.org:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Trianon World | 764 | 40 | <https://www.trianon-world.com> | Joomla | Joomla | no | generator=HELIX_ULTIMATE_GENERATOR_TEXT; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Friends Grid | 743 | 1625 | <https://friends-grid.com/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| BloodMoon | 725 | 82 | <https://bloodmoonpack.com/grid/> | Custom PHP | Custom code | no | server=Apache/2.4.58 (Ubuntu) | medium |
| AvatarLife | 717 | 620 | <https://www.avatarlife.com/> | JS app (SPA) | Custom code | no | SvelteKit (_app/immutable), avatar choice at register; Next.js / Nuxt / SPA: home html+register page; server=nginx/1.18.0 (Ubuntu) | high |
| AviWorlds | 696 | 664 | <https://aviworlds.com/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| Craft World | 623 | 880 | <https://www.craft-world.org> | Custom PHP | Custom code | no | server=nginx/1.22.1 | medium |
| SpaceGrid | 487 | 133 | <https://spacegrid.online/> | JS app (SPA) | Custom code | no | Flutter web app; Next.js / Nuxt / SPA: home html; server=nginx/1.24.0 (Ubuntu) | high |
| Free Life | 479 | 14 | <http://freelife.outworldz.net:8002/wifi> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Funny Rides | 420 | 196 | <https://funny-rides.net/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| Virtualife | 419 | 95 | <http://grid.virtualife-grid.it> | Custom PHP | Custom code | no | Diva Wifi: home html+register page; accounts via Diva Wifi; server=Apache/2.4.52 (Ubuntu) | high |
| Jungle Friends Grid | 362 | 125 | <http://junglefriends.opensim.fun:8002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| ZetaWorlds | 345 | 17152 | <https://zetaworlds.com/> | CodeIgniter | Custom code | no | CodeIgniter: headers/cookies; server=nginx | high |
| LinkUp Grid | 342 | 98 | <https://linkup-grid.com/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| Astralia | 341 | 73 | <https://www.astralia.eu/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Gentle Fire Grid | 299 | 44 | <https://gentlefiregrid.net/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| Kurtopia | 263 | 80 | <http://kurtopia.ddns.net/> | WordPress | WordPress | **yes** | generator=WordPress 7.1.4; x-powered-by=PHP/8.5.12; w4os: readme.txt probe; w4os: home html+register page; WordPress: home html+register page | high |
| Kitely | 259 | 17526 | <https://www.kitely.com> | Kitely (own platform) | Hosted grid panel | no | commercial platform, GWT front end; Next.js / Nuxt / SPA: home html | high |
| Herederos Grid | 243 | 16 | <http://hg.caribougrid.com:16002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Ansjela Grid | 214 | 46 | <http://hg.ansjelagrid.com> | WordPress | WordPress | **yes** | generator=WordPress 7.1.4; w4os: readme.txt probe; w4os: home html; WordPress: home html | high |
| Big City | 212 | 68 | <http://bigcity.ddns.net:8042/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Dark Angel Grid | 196 | 15 | <http://hg.darkangelgrid.com:26002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Barefoot Dreamers | 191 | 51 | <https://barefoot-dreamers.com/> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html | high |
| Party Destination Grid | 180 | 30 | <https://partydestinationgrid.com/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; x-powered-by=PHP/8.4.24; jOpenSim (Joomla): register page; Joomla: home html+register page; accounts via jOpenSim (Joomla) | high |
| Homelandz | 175 | 35 | <http://homelandz.opensim.fun:8802/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| WestWorld Grid | 172 | 58 | <https://www.westworldgrid.com/> | Custom PHP | Custom code | no | server=Apache | medium |
| Ausgrid | 163 | 35 | <http://login.ausgridds.biz:8002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Second Sanctuary | 160 | 23 | <https://grid.2nd-sanctuary.de/welcomesplashpage.php> | osWebInterface | Grid web UI | no | welcomesplashpage.php, same layout as other osWebInterface sites; server=Apache/2.4.58 (Ubuntu) | high |
| Twiztid Timez Grid | 159 | 159 | <https://www.twiztidtimez.com/> | Google Sites | Site builder | no | server=cloudflare; Google Sites: home html | high |
| Exotic Realities | 140 | 776 | <https://exoticrealities.com/> | HTTP 523 | No site / error | no |  | low |
| Sanctum Astra | 130 | 12 | <https://sanctumastra.com> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Farm Grid | 125 | 6 | <http://farm-grid.ddns.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Dynamic Worldz | 118 | 170 | <https://dynamicworldz.com> | WordPress | WordPress | no | generator=WordPress 7.1.4; x-powered-by=PleskLin; WordPress: home html | high |
| Escape 2 Reality Grid | 118 | 57 | <http://grid.escape2reality.org> | Default server page | No site / error | no |  | low |
| Genesis Roleplay Grid | 116 | 39 | <http://grid.genesis-roleplay.org:8002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| OutofThisWorld Grid | 112 | 56 | <http://outofthisworld.opensim.fun:36002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Tenth Dimension | 112 | 24 | <http://grid.tenthdimension.org:26002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Counter Earth | 106 | 107 | <https://www.counterearthgrid.com/> | HTTP 404 | No site / error | no |  | low |
| Japan Open Grid | 103 | 203 | <https://www.jogrid.net/wi/> | Moodle + Modlos | Grid web UI | no | MoodleSession cookie, blocks/modlos (Modlos 3.5.0); x-powered-by=PHP/8.1.34; phpBB / forum: home html; server=Apache | high |
| Spartans Keep | 100 | 185 | <https://spartanskeep.com/> | WordPress | WordPress | no | generator=WooCommerce 11.3.0,WordPress 7.1.4; x-powered-by=PleskLin; WordPress: home html+register page+headers/cookies | high |
| AusVirtual Grid | 98 | 31 | <http://ausvirtualgrid.xyz/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; x-powered-by=PHP/7.4.22; jOpenSim (Joomla): home html+register page; Joomla: home html+register page; accounts via jOpenSim (Joo | high |
| Carima Welt | 91 | 79 | <http://carima-welt.de:8002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Dorena’s World | 89 | 58 | <http://dorenas-world.de/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Xenolandia | 87 | 19 | <http://xenolandia.de:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Freedom Grid | 80 |  | <http://freedomgrid.world/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Zodiack | 77 | 23 | <http://zodiack.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Kamilian Radio | 76 | 128 | <https://kamilianradio.com/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| Nymphgrid 2 Shopping | 75 | 4 | <http://2.nymphgrid.com:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Great Canadian Grid | 74 |  | <http://www.greatcanadiangrid.ca/> | Wix (domain not connected) | No site / error | no | Wix ConnectYourDomain error page; Wix: home html+headers/cookies; Next.js / Nuxt / SPA: home html | high |
| Terra Nova | 74 | 80 | <http://terranova.outworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Harmonys Haven | 64 | 31 | <https://harmonyshaven.com/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| OpenSim Network | 63 | 20 | <https://opensimnetwork.com/> | WordPress | WordPress | no | Diva Wifi: home html+register page; WordPress: home html; accounts via Diva Wifi | high |
| 3rd Rock Grid | 62 | 296 | <https://www.3rdrockgrid.com/> | WordPress | WordPress | no | WordPress: home html | high |
| Avacon | 62 | 25 | <https://avacon.org/> | WordPress | WordPress | no | redirects to /blog/ (organisation, not a grid site); generator=WordPress 7.1.4; Diva Wifi: home html; WordPress: home html; accounts via Diva Wifi | medium |
| HG Safari Grid | 61 | 11 | <http://grid.hgsafari.org:58002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Utopia Skye | 61 | 138 | <https://utopiaskye.com/> | Wix | Site builder | no | generator=Wix.com Website Builder; Wix: home html+headers/cookies; Laravel: headers/cookies | high |
| Lady’s Dreamworld | 59 | 16 | <http://ladysdreamworld.opensim.fun:8002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Outworldz | 58 | 140 | <https://www.outworldz.com/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Vela Dreams | 58 | 41 | <http://vela-dream.ddnss.de:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| The Oar Shop | 52 | 2 | <https://panel.theoarshop.com/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| ArtDestiny | 51 | 1089 | <http://artdestiny.de/> | WordPress | WordPress | no | generator=WordPress 6.9.11; WordPress: home html+register page+headers/cookies | high |
| Virtual Life Brasil | 49 | 45 | <http://www.virtuallifebrasil.com.br/> | Custom PHP | Custom code | no | server=Apache/2.4.66 (Debian) | medium |
| Accueil Dracsoom | 37 | 37 | <http://dracsoom.free.fr/> | Site builder (other) | Site builder | no | generator=WebAcappella 4.6.26  ecommerce (WIN) #0; Diva Wifi: home html+register page; accounts via Diva Wifi | low |
| Beyond Infinity | 37 | 5 | <http://infinity.outworldz.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Planet 3L | 32 | 12 | <http://planet3l.com/> | Static HTML | Static HTML | no | server=Apache/2.4.6 (CentOS) OpenSSL/ | low |
| Eenhgrid | 30 | 2 | <http://eenhgrid.outworldz.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Furry World | 30 | 358 | <https://furry-world.de/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| OpenWorld | 30 | 22 | <http://openworld.freemyip.com:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Servex Grid | 28 | 14 | <http://cff.lffl.de/> | WordPress | WordPress | no | generator=WordPress 7.0.8; x-powered-by=PHP/8.2.35; WordPress: home html | high |
| Bridgemere | 27 | 27 | <http://bridgemere.outworldz.net:8002/wifi> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Stellar | 26 | 18 | <http://stellar-grid.ddns.net:8002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| The Water’s Edge | 26 | 14 | <http://grid.waters-edge.fun:36002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| WoodstockSim | 26 | 9 | <http://woodstocksim.outworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| The Originals | 25 | 22 | <http://the3originals.com.br:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Stefanies World | 23 | 22 | <https://stefaniesworld.de/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Flotsam | 22 | 11 | <http://hg.flotsamgrid.com:34002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Adreans-World | 21 | 165 | <http://adreans-world.from-fl.com> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; x-powered-by=PHP/5.6.8; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Icelady Grid | 21 | 21 | <https://iceladygrid.de/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Speculoos | 21 | 23 | <https://speculoos.world/> | WordPress | WordPress | **yes** | generator=Powered by Slider Revolution 6.7.35 - re,Powered by WPBakery Page Builder - drag ,WPML ver:4.8.6 stt:38,1,4,3,11;,WordPress 6.8.12; w4os: home html; WordPress: home html | high |
| The Haven Grid | 21 | 14 | <http://thehavengrid.outworldz.net:9002/> | Unreachable | No site / error | no | URLError(gaierror(8, 'nodename nor servname provided, or not known')) | n/a |
| GridMania | 20 | 115 | <http://gridmania.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html+register page; server=OSWebServer | high |
| Atlantide | 18 | 9 | <https://real-3d.zapto.org:8002/wifi> | Unreachable | No site / error | no | URLError(SSLError(1, '[SSL: WRONG_VERSION_NUMBER] wrong version number (_ssl.c:1 | n/a |
| Bezerkly | 18 |  | <http://ga.bezerkly.net/> | Custom PHP | Custom code | no | x-powered-by=Mono; server=Apache | medium |
| Conectados Grid | 18 | 41 | <http://conectados.opensim.fun:8802/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Hawaiian Dreams Grid | 18 | 8 | <http://hg.hawaiiandreamsgrid.net:26002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| NeonWorld | 18 | 2 | <http://neonworldbeach.outworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Discovery Grid | 16 | 1614 | <https://discoverygrid.net> | Custom PHP | Custom code | no | server=Apache/2.4.71 (Ubuntu) | medium |
| TinyOne | 16 | 1 | <http://tinyone.uk:9002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Arcgel | 15 | 6 | <http://gridarchel.ddns.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Cooperation Creative | 15 | 19 | <http://hg.cc-group.cc:8002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Decadence | 15 | 8 | <http://decadence.ddns.net:8002/wifi> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| OliGrid | 15 | 26 | <https://oligrid.net> | WordPress | WordPress | **yes** | generator=Elementor 4.4.0; features: e_font_icon_s,Redux 4.5.18,Site Kit by Google 1.191.0; x-powered-by=PHP/8.5.12; w4os: readme.txt probe; w4os: home html; WordPress: home html | high |
| Medieval Fantasy | 14 | 16 | <http://medieval-fantasy.de:8002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| OpenSimulator Community Conference | 14 | 22 | <https://conference.opensimulator.org/> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html+register page+headers/cookies | high |
| The Verse | 14 | 31 | <http://theverse.ddns.net:8002/wifi> | WordPress | WordPress | no | Diva Wifi: home html; WordPress: home html; accounts via Diva Wifi | high |
| Ardalia | 13 | 17 | <http://ardiva.ddns.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Ardiva | 13 | 17 | <http://ardiva.ddns.net:8002/wifi> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Harmonious Grid | 13 | 4 | <http://harmoniousgrid.inworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Carnivale Grid | 12 | 39 | <https://carnivalegrid.com/> | WordPress | WordPress | **yes** | x-powered-by=PHP/8.3.36; w4os: readme.txt probe; w4os: home html+register page; WordPress: home html+register page+headers/cookies | high |
| The Shirls Hypergrid | 12 | 14 | <http://theshirls.com/> | Static HTML | Static HTML | no | server=Apache | low |
| Virtual Travelers | 12 | 9 | <http://virtualtravelers.outworldz.net:8002/> | Grid server only (no page) | Grid web UI | no | Grid server pages: URL on grid port, no Wifi; accounts via Grid server pages; server=OSWebServer | low |
| Baller Nation | 11 | 51 | <https://www.ballernation.us/> | WordPress | WordPress | no | generator=WordPress 6.5.3; WordPress: home html | high |
| Virtual Harmony in the Cloud | 11 | 21 | <https://vharmonyartshub.com/> | WordPress | WordPress | **yes** | generator=WordPress 7.1.4; w4os: readme.txt probe; WordPress: home html+headers/cookies | high |
| DreamGrid Nydalimas | 10 | 9 | <http://nydalimas.outworldz.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| EdMondo | 10 | 310 | <http://slw.indire.it:8002/wifi/index.html> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=nginx/1.6.3 | high |
| Hispanofur | 10 | 17 | <http://hispanofur.duckdns.org:8002/wifi> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Vendetta Life | 10 | 15 | <http://hg.vendettagrid.life:16002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Wiccagrid | 10 | 22 | <http://wiccaone.inworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Xmir | 10 | 62 | <http://www.xmir.org/> | Site builder (other) | Site builder | no | generator=Sandvox 2.10.12 | low |
| Cozy Comforts Grid | 9 | 60 | <https://cozycomforts.net/> | WordPress | WordPress | no | generator=WordPress.com; WordPress: home html | high |
| FuoriGrid | 9 | 10 | <https://fuorigrid.opensim.online/index.php?option=com_opensim> | Joomla | Joomla | no | jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Stress Free Grid | 9 | 9 | <http://stressfreegrid.opensim.fun:36002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Ankabi | 8 | 73 | <http://ankabi.de> | WordPress | WordPress | **yes** | generator=Elementor 4.3.5; features: e_font_icon_s,WordPress 7.1.4; w4os: readme.txt probe; w4os: home html+register page; WordPress: home html+register page | high |
| Lux Serenity | 8 | 1 | <http://62.169.30.187:9000/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Shork Group Grid | 8 | 2 | <http://shorkgroup.opensim.fun:24002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Casperia Prime | 7 | 69 | <http://casperia.ddns.net/grid/1/osloginscreen/> | HTTP 404 | No site / error | no |  | low |
| Cat Woman Rose | 7 | 27 | <http://catwomanrose.de/> | osWebInterface | Grid web UI | no | OpenSim web interface (other): home html; accounts via OpenSim web interface (other); server=Apache/2.4.29 (Ubuntu) | low |
| Port Anna | 7 | 10 | <https://opensimworld.com/hop/91610> | Skipped (robots.txt) | No site / error | no |  | n/a |
| AIRE Mille Flux | 6 | 23 | <http://grid.aire-mille-flux.org:8002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Ay Island | 6 | 5 | <http://ay-island.de:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| CreaNovale Grid | 6 | 2 | <https://www.creanovale.ca/opensimgrid/> | Static HTML | Static HTML | no | server=LiteSpeed | low |
| La Grilla de Don Javi | 6 | 1 | <http://donjavi.minigrid.xyz:8002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| GerGrid | 5 | 132 | <https://www.gergrid.de/> | Custom PHP | Custom code | no | server=Apache | medium |
| Land Of Bree | 5 | 56 | <http://carlyspencer.ddns.net:9002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Langalf’s Demesne | 5 | 2 | <http://langalfs-demesne.ddns.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Metaverse Dimensions | 5 | 12 | <http://login.metaversedimensions.com:8002/wifi> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Saar-Harzer | 5 | 15 | <http://saar-harzer.de:8002/wifi> | Diva Wifi | Grid web UI | no | server=OSWebServer | high |
| Tanduria | 5 | 10 | <http://tanduria.de> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Zirconia | 5 | 32 | <http://newzirconia.ddns.net:9002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Fantasia | 4 | 6 | <https://grid.drabra.de/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Grid Racers | 4 | 8 | <http://gridracers.com/JOpensim/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; x-powered-by=PHP/7.4.12; jOpenSim (Joomla): home html+register page; Joomla: home html+register page; accounts via jOpenSim (Joo | high |
| Hartland | 4 | 16 | <http://hartland.ddns.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Infinite Grid | 4 | 100 | <https://www.infinitegrid.org/> | SledjChisl (C, FastCGI) | Custom code | no | sledjchisl.fcgi account manager; server=Apache/2.4.25 (Debian) | high |
| Mentis Pacem | 4 | 18 | <http://mentis-pacem-metaversum.outworldz.net:9002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Sav’s World | 4 | 2 | <http://savs-world.inworldz.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| SnuSnu Island | 4 | 4 | <http://snusnuisland.duckdns.org/> | Static HTML | Static HTML | no | server=Apache | low |
| Swiss Grid | 4 | 45 | <https://swissgrid.opensim.ch/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| The Islands | 4 |  | <https://theislands.online> | Google Sites | Site builder | no | server=nginx/1.24.0 (Ubuntu); Diva Wifi: home html; Google Sites: home html; accounts via Diva Wifi | high |
| Whispering Willows | 4 | 19 | <http://hg.whisperingwillows.net> | Default server page | No site / error | no |  | low |
| Argentoratum | 3 | 0 | <https://cogito.no-ip.info/cogito/grille/index.php> | Custom PHP | Custom code | no | server=Apache/2.4.52 (Ubuntu) | medium |
| Ciudad 404 | 3 | 124 | <https://www.ciudad404.com/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; Joomla: home html | high |
| GridExperience | 3 | 18 | <http://www.gridexperience.com/> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html | high |
| Lagniappe Grid | 3 | 26 | <http://hg.lagniappegrid.com:8402/wifi/index.html> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| NewWorld | 3 | 4 | <http://newworld.no-ip.org:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| OpenManniLand | 3 | 13 | <http://openmanniland.de/> | osWebInterface | Grid web UI | no | OpenSim web interface (other): home html; accounts via OpenSim web interface (other); server=Apache/2.4.66 (Ubuntu) | low |
| Rocket World Grid | 3 | 1 | <http://grid.rocketworld.top:8022/wifi> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| 3DLES | 2 | 10 | <https://www.3dles.com/> | Joomla | Joomla | no | generator=Helix Ultimate - The Most Popular Joomla; x-powered-by=PHP/8.3.35; Joomla: home html | high |
| Caelum Alpha | 2 | 3 | <http://caelumalpha.outworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Deep Playa | 2 |  | <http://rajal.org> | Custom PHP | Custom code | no | x-powered-by=PHP/8.5.12; server=Apache | medium |
| KittyBlue | 2 | 3 | <http://grid.xylinvale.space:9000/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Midway | 2 | 4 | <http://midway.outworldz.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Monarch Gardens | 2 | 58 | <http://monarchgardens.sanctuarygrid.net> | Default server page | No site / error | no |  | low |
| Obfuse Chill Grid | 2 | 9 | <http://obfusechill.minigrid.xyz:38002/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| RMBOSW Grid | 2 | 7 | <https://alterkater.eu/> | Static HTML | Static HTML | no | server=Apache | low |
| Time Grid | 2 | 5 | <http://timegrid.de/> | osWebInterface | Grid web UI | no | OpenSim web interface (other): home html; accounts via OpenSim web interface (other); server=Apache/2.4.58 (Ubuntu) | low |
| Twilight | 2 | 104 | <http://twilight2.twlght.com/splash/> | Unreachable | No site / error | no | URLError(OSError(50, 'Network is down')) | n/a |
| Virtual Worlds Grid | 2 | 910 | <https://www.virtualworldsgrid.com/> | Google Sites | Site builder | no | server=ESF; Google Sites: home html | high |
| Alterworld Grid | 1 | 5 | <http://hg.alterworld.us:7002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=Tiny WebServer | high |
| Devhalla | 1 | 48 | <http://devhalla-grid.duckdns.org:9000/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Dragonz Kin Territories | 1 | 48 | <http://grid.dragonzkinterritory.org:18002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| EVA Park | 1 | 1 | <http://evapark.co.uk/> | Site builder (other) | Site builder | no | generator=Microsoft FrontPage 5.0; Diva Wifi: home html; accounts via Diva Wifi | low |
| Magic Grid | 1 | 121 | <http://magic-grid.de/> | WordPress | WordPress | no | generator=WordPress 6.9.11; WordPress: home html+register page | high |
| New Life | 1 | 2 | <http://newlife3d.it/index.php?option=com_opensim> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Next Reality | 1 | 2 | <http://login.nextreality.uk:8002/wifi> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Open Mind Grid | 1 | 31 | <http://open-mind-grid.de:8002/> | Grid server only (no page) | Grid web UI | no | Grid server pages: URL on grid port, no Wifi; accounts via Grid server pages; server=OSWebServer | low |
| Rainbow Grid | 1 | 113 | <https://rg.spes.gr/> | WordPress | WordPress | **yes** | w4os: readme.txt probe; w4os: home html; WordPress: home html | high |
| Sheikah | 1 | 5 | <https://sheikah.org/en/> | WordPress | WordPress | **yes** | generator=Site Kit by Google 1.191.0,WordPress 7.1.4; w4os: readme.txt probe; w4os: home html; WordPress: home html | high |
| Unknown | 1 | 7 | <http://unknown.death.cl/> | Unreachable | No site / error | no | URLError(TimeoutError('timed out')) | n/a |
| Virlands | 1 | 2 | <http://virlands.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Virtual Harmony | 1 | 110 | <https://vharmonyarts.com/vharmony/public/vhgrids> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html | high |
| Assassin Guard | 0 | 11 | <http://assassinguard.opensim.fun> | Default server page | No site / error | no |  | low |
| Atek Grid | 0 | 18 | <http://atekgrid.com/> | Custom PHP | Custom code | no | server=Apache | medium |
| Beta Technologies | 0 | 25 | <http://opensim.betatechnologies.info/> | WordPress | WordPress | no | WordPress: home html+register page | high |
| Dolfins Dream World | 0 | 10 | <https://dolfinsdreamworld.wordpress.com/> | WordPress | WordPress | no | generator=WordPress.com; WordPress: home html | high |
| Esfera Split | 0 | 2 | <http://esferavirtual.com.br/splash/> | Custom PHP | Custom code | no | x-powered-by=PHP/8.2.12; server=Apache/2.4.58 (Win64) OpenSSL/ | medium |
| Esfera Virtual | 0 | 2 | <http://esferavirtual.com.br/splash/> | Custom PHP | Custom code | no | x-powered-by=PHP/8.2.12; server=Apache/2.4.58 (Win64) OpenSSL/ | medium |
| JamGrid | 0 | 13 | <https://www.jamgrid.de> | Static HTML | Static HTML | no | server=Apache | low |
| Planet Jikan | 0 | 12 | <http://planetjikan.com:9000/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Rad3DSims | 0 | 24 | <http://grid.rad3dsims.com:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Sense Limits | 0 | 185 | <https://senselimits.cloud/> | WordPress | WordPress | **yes** | generator=WordPress 7.1.4; w4os: readme.txt probe; w4os: home html; WordPress: home html | high |
| Tlön | 0 | 10 | <http://albinoni.nexus.it/> | Custom PHP | Custom code | no | server=Apache/2.4.10 (Debian) | medium |
| Virtual Marin | 0 | 17 | <https://www.virtualmarin.com/> | WordPress | WordPress | no | generator=WordPress 5.0.31; WordPress: home html+headers/cookies | high |
| WKD Bucket Grid | 0 | 2 | <https://wkdbucket.minigrid.xyz> | Unreachable | No site / error | no | URLError(SSLError(1, '[SSL: WRONG_VERSION_NUMBER] wrong version number (_ssl.c:1 | n/a |
| Yippee Ki-Ay Blues Grid | 0 | 18 | <https://www.yippeekiayblues.com> | Static HTML | Static HTML | no |  | low |
| 3 World |  |  | <https://3-world.de/> | Custom PHP | Custom code | no | server=nginx | medium |
| 3D Worldz |  | 17 | <https://www.3dworldz.com/> | 3D Worldz panel (ASP.NET) | Hosted grid panel | no | same IIS 7.5 ASP.NET layout ('Home - <name>'), 3D Worldz hosting; x-powered-by=ASP.NET; ASP.NET: headers/cookies | medium |
| 3rd Life Grid |  |  | <http://3rdlifegrid.com> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html+register page; server=OSWebServer | high |
| 4Creative |  | 44 | <https://4creative.net/> | Grav | Other CMS | no | generator=GravCMS; Grav: home html | high |
| 5×5 Vidagrid |  |  | <http://5x5.com.br/> | Static HTML | Static HTML | no | server=Apache/2.4.71 (Ubuntu) | low |
| A Virtual World |  |  | <http://avirtualworld.org/> | HTTP 404 | No site / error | no |  | low |
| Admeja |  |  | <https://admeja.weebly.com/> | Weebly | Site builder | no | Weebly: home html | high |
| Adrian Labs |  |  | <https://adrianlabs.com/> | Custom PHP | Custom code | no | x-powered-by=PHP/8.2.30; server=openresty | medium |
| Adult Life |  |  | <https://adult-life.de/> | Custom PHP | Custom code | no | server=Apache | medium |
| Alexa World |  | 5 | <http://alexaworld.de/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Alife Virtual |  |  | <http://www.alifevirtual.com/> | Custom PHP | Custom code | no | server=Apache/2.4.67 (Debian) | medium |
| AnSky |  |  | <https://www.anskygrid.ca/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; x-powered-by=PHP/7.4.33-pl1; jOpenSim (Joomla): register page; Joomla: home html+register page; accounts via jOpenSim (Joomla) | high |
| Anubis |  |  | <http://anubishabilis.outworldz.net:8002/wifi/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Arcadia Asylum |  | 23 | <http://arcadiaasylum.org> | Custom PHP | Custom code | no | server=Apache/2.4.58 (Ubuntu) | medium |
| Argofantasy |  |  | <http://argofantasy.it/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Arkham Grid |  |  | <https://arkhamgrid.org/> | WordPress | WordPress | no | WordPress: home html | high |
| Astria Porta |  |  | <http://astria-porta.net/> | Static HTML | Static HTML | no | server=cloudflare | low |
| Atlas Grid |  |  | <http://atlasgrid.fr> | Static HTML | Static HTML | no | server=Apache/2.4.67 (Debian) | low |
| Australia |  |  | <http://australia.outworldz.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Austria Grid |  |  | <https://austriagrid.net/> | Unreachable | No site / error | no | URLError(gaierror(8, 'nodename nor servname provided, or not known')) | n/a |
| Bridger |  |  | <http://bridger.outworldz.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Brogals World |  |  | <https://brogals-paradise.de/> | Unreachable | No site / error | no | URLError(gaierror(8, 'nodename nor servname provided, or not known')) | n/a |
| CandM World |  |  | <https://candmworld.com/> | WordPress | WordPress | no | WordPress: home html+register page+headers/cookies | high |
| Chez Nous |  |  | <http://perso.servebeer.com:8002/wifi> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| CLC grid |  |  | <http://clcgrid.com> | Unreachable | No site / error | no | URLError(TimeoutError('timed out')) | n/a |
| Creo Mundos |  | 24 | <https://discover.creomundos.com/> | 3D Worldz panel (ASP.NET) | Hosted grid panel | no | same IIS 7.5 ASP.NET layout ('Home - <name>'), 3D Worldz hosting; x-powered-by=ASP.NET; ASP.NET: headers/cookies | medium |
| CyberDataStorm |  |  | <http://cyberdatastorm.com:8002/> | Grid server only (no page) | Grid web UI | no | Grid server pages: URL on grid port, no Wifi; accounts via Grid server pages; server=OSWebServer | low |
| Dajas Grid |  |  | <http://helper.grid.daja.at/Welcome.php> | Unreachable | No site / error | no | URLError(TimeoutError('timed out')) | n/a |
| Disaster Land |  | 4 | <http://disastergrid.asuscomm.com/disastergrid/index.html> | Custom PHP | Custom code | no | server=Apache/2.4.68 (Win64) PHP/8.5. | medium |
| Dust |  |  | <http://dust.overunityproductions.com/login_screen.php> | Custom PHP | Custom code | no | x-powered-by=PHP/8.5.12; server=openresty | medium |
| DWGrid |  |  | <http://dwgrid.nl/splash/> | Static HTML | Static HTML | no | server=Apache/2.4.58 (Ubuntu) | low |
| Dyvall |  | 6 | <https://dyvall.de/> | Unreachable | No site / error | no | URLError(ConnectionResetError(54, 'Connection reset by peer')) | n/a |
| EducaSim |  |  | <https://educasim.es/> | Unreachable | No site / error | no | TimeoutError('The read operation timed out') | n/a |
| Elsewhere |  |  | <https://elsewhere-grid.com/> | Hosting4Opensim panel (Laravel) | Hosted grid panel | no | same panel layout, Site Credits page names Hosting4Opensim (H4HUB); Laravel: headers/cookies | high |
| Elysium |  |  | <https://elysiumisles.com/> | Unreachable | No site / error | no | TimeoutError('The read operation timed out') | n/a |
| Endive Atomic World |  | 7 | <http://www.endiveworld.com/> | Static HTML | Static HTML | no | server=Apache/2.4.51 (Win64) | low |
| Equinox Grid |  |  | <http://www.equinoxgrid.net/wpWebsite/> | WordPress | WordPress | no | generator=WordPress 6.8.12; WordPress: home html | high |
| EscapeLands |  |  | <https://www.escapelands.com/;> | WordPress | WordPress | no | generator=WooCommerce 11.1.2,WordPress 7.1.4; x-powered-by=PleskLin; Diva Wifi: home html; WordPress: home html+headers/cookies; accounts via Diva Wifi | high |
| Eureka World |  |  | <https://www.eurekaworld.co.il/> | Wix | Site builder | no | generator=Wix.com Website Builder; Wix: home html+headers/cookies; Laravel: headers/cookies | high |
| Fae Farms |  |  | <http://alanna.outworldz.net:8002/wifi> | Diva Wifi | Grid web UI | no | server=OSWebServer | high |
| Forgotten Worlds |  |  | <http://213.200.254.17:18002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| France RP |  |  | <http://francerpgrid.fr/> | Static HTML | Static HTML | no | server=Apache/2.4.52 (Ubuntu) | low |
| Francopholie |  |  | <http://francopholie.fr/> | Static HTML | Static HTML | no | now a company page (SV3D / ChassisPrix); phpBB / forum: home html; server=Apache/2.4.67 (Debian) | medium |
| Fresh MetaVerse |  |  | <http://os-metavers.fresh-projects.top:8002> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| German Grid |  | 14 | <https://germangrid.online/> | Custom PHP | Custom code | no | server=Apache/2.4.58 (Ubuntu) | medium |
| Ghost Area |  |  | <http://ghostarea.com:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| GL Grid |  |  | <https://glgrid.org/> | Custom PHP | Custom code | no | server=nginx | medium |
| Grid Nirvana |  |  | <http://www.gridnirvana.net> | Unreachable | No site / error | no | RemoteDisconnected('Remote end closed connection without response') | n/a |
| Here Grid |  |  | <https://here-grid.de> | HTTP 404 | No site / error | no |  | low |
| Holoneon |  |  | <http://holoneon.com> | Custom PHP | Custom code | no | server=nginx | medium |
| Hypergrid City |  |  | <http://hypergridcity.com> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html+headers/cookies | high |
| I Love You Grid |  | 178 | <http://www.iloveyougrid.net:8002> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html+register page; server=OSWebServer | high |
| Ignis Fatuus |  |  | <https://opensimworld.com/dir/?grid=ignis-fatuus.no-ip.biz:8002> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Imperiya Grid |  |  | <http://imperiya.sytes.net:8002/wifi> | Diva Wifi | Grid web UI | no | server=OSWebServer | high |
| Jazzn’s DreamWorlds |  | 11 | <http://jazznsdreamworlds.outworldz.net:8002/wifi> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| JoyGrid |  |  | <https://joygrid.de/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Kara Islands Estate |  |  | <https://karagrid.world/> | HTTP 400 | No site / error | no |  | low |
| Kater and Friends |  | 167 | <http://kater-and-friends.ddnss.de:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html+register page; server=OSWebServer | high |
| Kinky Haven |  | 677 | <https://kinkyhaven.com/> | Custom PHP | Custom code | no | server=Apache/2.4.58 (Ubuntu) | medium |
| Kirmes Creations |  |  | <http://kirmescreations.ddnssec.de:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Kokomo World |  |  | <https://grid.kokomoworld.de/> | Custom PHP | Custom code | no | server=nginx/1.22.1 | medium |
| KoolPheller Estates |  |  | <http://koolpheller.ddns.net/opensim/loginscreen.php> | Unreachable | No site / error | no | URLError(TimeoutError('timed out')) | n/a |
| Littlefield |  |  | <http://www.littlefieldgrid.com/> | Site builder (other) | Site builder | no | generator=Starfield Technologies; Go Daddy Website | low |
| Logicamp |  | 115 | <http://logicamp.org/> | Custom PHP | Custom code | no | server=Apache | medium |
| LuxeLife Virtual |  |  | <http://luxelifevirtual.com:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| MAGA Grid |  | 7 | <http://magagrid.us/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; x-powered-by=PHP/8.1.1; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
| Majickal Life |  |  | <http://majickallife.com/> | Static HTML | Static HTML | no | server=Apache | low |
| Mathesis |  |  | <http://aigroup.ceid.upatras.gr:9000/wifi/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Maze of The Mind |  | 196 | <https://mazeofthemind.com/> | WordPress | WordPress | **yes** | generator=WordPress 7.1.4; w4os: readme.txt probe; WordPress: home html+headers/cookies | high |
| Migrating Coconuts |  | 267 | <https://www.migratingcoconuts.net/> | WordPress | WordPress | **yes** | generator=Powered by WPBakery Page Builder - drag ,WordPress 7.1.4; w4os: readme.txt probe; w4os: home html; WordPress: home html | high |
| Miki Kiti Tiki |  |  | <http://tikitronic.com> | Static HTML | Static HTML | no | server=Apache/2.4.52 (Ubuntu) | low |
| MisFitz Grid |  | 215 | <https://misfitzgrid.com> | JS app (SPA) | Custom code | no | Angular-style chunk-*.js bundle; server=nginx/1.30.6 | medium |
| MK3 |  |  | <https://mk3.org/> | Custom PHP | Custom code | no | server=Apache/2.4.69 (Debian) | medium |
| Mobius Grid |  | 84 | <https://www.mobiusgrid.us/> | Custom PHP | Custom code | no | server=Apache/2.4.52 (Ubuntu) | medium |
| Moonrose |  |  | <https://moonrose-grid.de/> | Custom PHP | Custom code | no | server=Apache | medium |
| Morada Grid |  | 28 | <https://moradagrid.com/> | Static HTML | Static HTML | no | server=openresty | low |
| Moss Grid |  | 31 | <https://mossgrid.uk/> | Static HTML | Static HTML | no | server=LiteSpeed | low |
| My Virtual Beach |  |  | <http://grid.myvirtualbeach.com/JOpensim/index.php/en/> | Joomla | Joomla | no | jOpenSim (/JOpensim/ path); generator=Joomla! - Open Source Content Management; jOpenSim (Joomla): home html+register page; Joomla: home html+register page; phpBB / forum: home htm | high |
| Nabila Grid |  | 18 | <http://splash.proxnet.org/?gpid=8002&amp;gbh=nabila-world.de> | Static HTML | Static HTML | no | server=nginx | low |
| Napoli Pulcinella |  |  | <http://napoli-pulcinella.ddns.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Naras Nook |  |  | <https://www.narasnook.com> | Blogger | Site builder | no | Blogger: home html | high |
| Nemeton Grove |  |  | <https://nemetongrove.online/> | Custom PHP | Custom code | no | server=nginx/1.18.0 (Ubuntu) | medium |
| Nexus-Haven Grid |  |  | <http://grid.nexus-haven.ovh:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Nitro |  | 19 | <http://developer.ittybittymachine.net:5002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Nomads Metaverse |  |  | <http://nomads-metaverse.com:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Novalandia |  |  | <https://novalandia.online/> | Custom PHP | Custom code | no | server=nginx/1.24.0 (Ubuntu) | medium |
| Offworld |  | 67 | <https://offworld.eu/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Open Grid |  |  | <https://open-grid.online/> | Custom PHP | Custom code | no | server=cloudflare | medium |
| Open Virtual Worlds |  | 241 | <https://blogs.cs.st-andrews.ac.uk/> | Unreachable | No site / error | no | URLError(ConnectionResetError(54, 'Connection reset by peer')) | n/a |
| OpenSim Fest |  |  | <https://www.opensimfest.com/> | Unreachable | No site / error | no | URLError(gaierror(8, 'nodename nor servname provided, or not known')) | n/a |
| Ozone MiniVerse |  |  | <https://sites.google.com/view/ozone-splash> | Google Sites | Site builder | no | server=ESF; Google Sites: home html | high |
| Pandora Grid |  |  | <http://pandora.minigrid.xyz:48002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Pangea Grid |  |  | <https://pangea-grid.com/> | WordPress | WordPress | no | generator=Elementor 4.1.1; features: additional_cu,WordPress 7.1.4; WordPress: home html+headers/cookies | high |
| Paralax Life |  |  | <http://paralax.life:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Phaandoria Grid |  |  | <http://www.phaandoria.de/> | Static HTML | Static HTML | no | server=Apache | low |
| Power Angels |  |  | <https://power-angels.info/> | Unreachable | No site / error | no | URLError(gaierror(8, 'nodename nor servname provided, or not known')) | n/a |
| Reserected Worlds |  |  | <http://reserectedworlds.ddns.net:9000/> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Russian Grid |  | 10 | <http://russiangrid.ru:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html+register page; server=OSWebServer | high |
| Shalom Grid |  |  | <http://shalomgrid.opensim.fun:22002/wifi> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| Sharing Is Caring Family |  |  | <https://sharingiscaring.family> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html | high |
| Shasiyadynasty |  | 70 | <http://shasiyadynasty.outworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Six Sides |  |  | <http://sixsides.net> | WordPress | WordPress | **yes** | x-powered-by=PHP/8.5.12; w4os: readme.txt probe; WordPress: home html | high |
| Soul Grid |  |  | <https://soul-grid.de/> | Skipped (robots.txt) | No site / error | no |  | n/a |
| Sovaria Estates |  | 24 | <https://www.sovariaestates.world/> | 3D Worldz panel (ASP.NET) | Hosted grid panel | no | same IIS 7.5 ASP.NET layout ('Home - <name>'), 3D Worldz hosting; x-powered-by=ASP.NET; ASP.NET: headers/cookies | medium |
| Stadt NexVerse |  |  | <https://stadt-nexverse.de/> | Static HTML | Static HTML | no | server=Apache | low |
| Sub-Version Space |  | 0 | <https://www.sub-version.space/> | Custom PHP | Custom code | no | server=nginx | medium |
| SV3D |  |  | <http://sv3d.fr/wordpress/> | WordPress | WordPress | **yes** | generator=WordPress 5.8.2; w4os: home html; WordPress: home html | high |
| T-Grid |  |  | <http://hg.viewtwo.net/index.html> | Static HTML | Static HTML | no | server=nginx/1.30.6 | low |
| Tag Grid |  | 1577 | <https://frontpage.taggrid.online/> | WordPress | WordPress | no | generator=WordPress 5.3.2; WordPress: home html; phpBB / forum: register page | high |
| Tamai |  |  | <https://www.tamai.nl/> | Static HTML | Static HTML | no | server=nginx | low |
| The Devil’s Grid |  |  | <https://devilsgrid.jessithecat.eu/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; Joomla: home html | high |
| ThinkSim |  |  | <https://thinksim.space/> | Static HTML | Static HTML | no | server=nginx/1.24.0 (Ubuntu) | low |
| Three Hills Grid |  | 44 | <http://three.hills.grid.outworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html+register page; server=OSWebServer | high |
| Tomi’s World |  |  | <http://tomis-world.de/> | Static HTML | Static HTML | no | server=Apache | low |
| Tranquility Grid |  |  | <https://tranquilgrid.uk/> | Unreachable | No site / error | no | URLError(ConnectionResetError(54, 'Connection reset by peer')) | n/a |
| V.B. Creations |  | 23 | <http://vbcreations.outworldz.net:8002/> | Diva Wifi | Grid web UI | no | DreamGrid bundle; Diva Wifi: home html; server=OSWebServer | high |
| Vallands |  |  | <https://vallands.ca/hgVallands/> | WordPress | WordPress | no | generator=WordPress 6.9.11; WordPress: home html | high |
| Vibel |  |  | <http://vibel.eu/> | Joomla | Joomla | no | Joomla: home html | high |
| Vida Dupla |  | 82 | <https://metaversovidadupla.com.br/> | Custom PHP | Custom code | no | server=Apache | medium |
| Virtual Business Grid |  |  | <https://virtualbusinessgrid.com/> | Site builder (other) | Site builder | no | Site builder (other): home html | high |
| Virtual RBM |  |  | <http://virtualrbm.com.br/> | WordPress | WordPress | no | generator=Site Kit by Google 1.191.0; Diva Wifi: home html+register page; WordPress: home html+register page+headers/cookies; accounts via Diva Wifi | high |
| Virtual Worlds Zone |  | 562 | <https://virtual-worlds.zone/> | WordPress | WordPress | no | generator=WordPress 7.1.4; WordPress: home html | high |
| Vivec |  |  | <http://opensim.vivec.com:9000/> | Grid server only (no page) | Grid web UI | no | Grid server pages: URL on grid port, no Wifi; accounts via Grid server pages; server=OSWebServer | low |
| Vivo Sim |  |  | <http://vivosim.net/> | Joomla | Joomla | no | generator=MYOB; Joomla: home html+register page | high |
| Wild Grid |  |  | <http://wildgrid.ddns.net:8002/> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Winxtropia Grid |  | 2 | <http://bloompeters.ddns.net:11052> | Diva Wifi | Grid web UI | no | Diva Wifi: home html; server=OSWebServer | high |
| WonderVerse |  | 126 | <https://wonderverse.pro/> | JS app (SPA) | Custom code | no | Vite/Tailwind bundle; server=nginx | medium |
| Wyldwood Bayou |  |  | <https://wyldwoodbayou.com/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; Joomla: home html | high |
| Xaara CA |  |  | <http://grid.xaara.ca:8002> | Unreachable | No site / error | no | ConnectionResetError(54, 'Connection reset by peer') | n/a |
| Zone Nations |  |  | <https://www.zonenations.com/> | Joomla | Joomla | no | generator=Joomla! - Open Source Content Management; x-powered-by=PHP/8.5.12; jOpenSim (Joomla): home html; Joomla: home html; accounts via jOpenSim (Joomla) | high |
