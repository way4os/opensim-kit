# Developers

## What this project is

The packages of OpenSimulator for Debian and Ubuntu, and the metapackage that installs the whole environment:

- the releases of OpenSimulator (`opensim-<version>`), its development version (`opensim-unstable`) and the essential modules
  (`opensim-<version>-<module>`), each in its own folder;
- `opensim`, the latest release, and `opensim-kit`, which installs the command line (`w4os-cli`), the engine
  (`w4os-engine`, with `opensim-rest-php`), the website of a grid (`w4os-site`, which carries `w4os-helpers`), the latest
  release, its essential modules and a database server.

The command line, the engine, the helpers and the site are projects of their own. The tools this repository had before
(opensim-tools: the `opensim` command in PHP, its setup, its tests) are gone: what they did and where it is now is in
[OPENSIM-TOOLS-PORTING.md](OPENSIM-TOOLS-PORTING.md), their code at the commit `de51268`.

## Packaging

The packages are built with [nfpm](https://nfpm.goreleaser.com) and published to an apt repository with the tools of [build-tools](https://github.com/magicoli/build-tools) (`apt-package`, `apt-publish`), the repository being the one `APT_REPO_DIR` names in `.env`. Everything lives in `packaging/`, one definition per package:

| Package                   | Definition              | Version                          | Content                                                                                                         |
| ------------------------- | ----------------------- | -------------------------------- | --------------------------------------------------------------------------------------------------------------- |
| `opensim-<version>`       | `opensim-core.yaml`     | OpenSimulator release + revision | an OpenSimulator release, in `/usr/share/opensim/<version>`, for amd64 and arm64                                |
| `opensim`                 | `opensim.yaml`          | same as the latest release       | metapackage, the latest release                                                                                 |
| `opensim-kit`             | `opensim-kit.yaml`      | git tag of this repository       | metapackage, `w4os-cli`, `w4os-site`, the latest release, its essential modules and a database server           |
| `opensim-unstable`        | `opensim-unstable.yaml` | source version, date and commit  | OpenSimulator built from the `upstream/opensim` submodule, in `/usr/share/opensim/unstable`                     |
| `opensim-<core>-<module>` | `opensim-<module>.yaml` | module version + revision        | a module for a release or `unstable`, in `/usr/share/opensim-modules/<core>/<module>`, not loaded until enabled |

The command line (`w4os-cli`), the engine (`w4os-engine`, with `opensim-rest-php`) and the website of a grid (`w4os-site`, which carries the helpers) are built and packaged by their own projects. `opensim-kit` depends on them; the releases install without them. `w4os-engine` makes the system layout and adds an install profile for each release in `/usr/share/opensim`, and removes it with the release, through a dpkg trigger; the postinst of `opensim-kit` enables its essential modules once.

`opensim-tools` and `opensim-web`, the generation before, are no longer built; what opensim-tools did and where it is now is in [OPENSIM-TOOLS-PORTING.md](OPENSIM-TOOLS-PORTING.md). `w4os-cli` conflicts with and replaces `opensim-tools`: installing the new `opensim-kit` removes it (`apt full-upgrade`, or `apt install opensim-kit`). `opensim-web` has no successor of the same files (the site of `w4os-site` is in `/var/lib/w4os/www`, not in `/var/www/html`): a server that has it keeps it, with `w4os-helpers`, until the site of `w4os-site` is set up (`w4os-site setup`, `w4os-site web`); then `apt purge opensim-web`. Nothing depends on it any more, so `apt autoremove` would take it away with `w4os-helpers` (`apt-mark manual opensim-web` keeps it). `apt upgrade` holds the kit back (it would remove a package); the prerm of `opensim-tools` stops the service and the instances when it goes: they are started again by hand (`systemctl start opensim`).

The modules are installed in their own folders, as some fail when loaded without their config: installing one does not enable it. They only suggest their core, so a build made elsewhere can use them and removing a core keeps them. `opensim-kit` installs the handful of essential modules for the latest release; the others are only in the repository. Third-party terms are in `contrib/README.md`, `modules/README.md` and in the `copyright` file of each module package.

Removing `opensim-<version>` or `opensim-unstable` stops the instances running from that core only, found by the full path of their assembly in the process list: instances of another release or of a build installed elsewhere are left alone. Nothing in an OpenSim instance needs a clean shutdown, its state is in the database, so a plain stop is enough.

The version of the metapackages is the version tag when the build is on one, the release. Any other build carries the version being worked on, declared in `.version` (a pre-release such as `3.0.0-dev`): `3.0.0~dev.<commits>+g<sha>`, which sorts after the former dev builds and before the release. The tag goes on the release commit only, which updates `CHANGELOG.md` (the tag message repeats it); `.version` is bumped for the next development version after it, a pre-release that sorts after the tag (`3.0.0-beta.2` after `3.0.0-beta.1`, not `3.0.0-dev`: `~dev` sorts after `~beta`).

## Building and publishing

```bash
dev/build.sh deb                             # every package, into dist/
dev/build.sh deb opensim-kit                 # only this package
vendor/bin/apt-package --publish      # publish, on a version tag of this repository
vendor/bin/apt-package --publish opensim-core opensim   # a new release, without a tag
```

`dev/build.sh` builds the last commit and refuses when changes are not committed or when `composer.lock` is not up to date (`DIRTY=1` builds the last commit anyway). The Debian packages are made by `apt-package`, of build-tools. The version carries the commit: `3.0.0~beta.4.709+gb98c10a` (`g` is for git, as `git describe` writes it, the hash follows it). The scripts are those of [build-tools](https://github.com/magicoli/build-tools) (`vendor/bin/build-tools`, a development dependency): the version, `stage` (the committed files without what `.distignore` leaves out, and the vendor folder composer makes without the development tools, from the repositories of `composer.json`), the release. The project keeps its own `packaging/`: `build` (prepares the packages), `remote-build`.

Publishing skips the packages whose version is already in the repository, so the metapackages and the releases keep their own pace. The packages with the version of this repository need a version tag (`v3.0.0`, the `v` is optional), and are attached to its GitHub release, marked as a pre-release when the version has one (`v3.0.0-beta.1`).

`packaging/build` runs first and prepares only the packages asked for:

- `opensim-core`, `opensim`: the release of `packaging/opensim-core.versions`, the last one or `OPENSIM_VERSION`, e.g. `OPENSIM_VERSION=0.9.3.0 vendor/bin/apt-package opensim-core`. Its tarball is downloaded into `cache/downloads/` and checked against its SHA256.

- `opensim-unstable`: OpenSimulator built from the commit of the `upstream/opensim` submodule (a shallow clone of the upstream repository, `master`), in a .NET SDK container (podman, or `CONTAINER=docker`). Each commit is built once, into `build/cache/`. With `BUILD_HOST=name` in `.env` (an ssh host with git, podman and rsync, see `.env.example`) the build runs on that machine instead, in the same container, so its system does not matter: it keeps a clone of the repository and the NuGet packages in `~/.cache/opensim-kit`, always builds from the files of the commit, and only what the package needs (`bin/` and the documents) comes back. The build is much faster there than in the podman machine of a laptop. Update it with `git submodule update --remote upstream/opensim`.
- `opensim-<module>`: the files listed for the module in `packaging/opensim-modules.versions`, for the release of `opensim-core`, or the core of `MODULES_CORE` (a release or `unstable`), e.g. `MODULES_CORE=unstable vendor/bin/apt-package opensim-gloebit`. The files downloaded go to `cache/downloads/`, checked against their SHA256. The packages are named after the modules.

A new OpenSimulator release is added at the end of `packaging/opensim-core.versions`, with its checksum. A published package is never changed: a packaging change of a published release gets the next revision in that file, as for the modules in `packaging/opensim-modules.versions`.

The `opensim` account, the folders of the system layout and `/etc/w4os/w4os.conf` are made by `w4os-engine`; the `opensim` service is `w4os-cli`'s.

The scripts of `dev/`, `packaging/` and `tests/Packaging` use the helpers of [bash-tools](https://github.com/magicoli/bash-tools), from composer's copy in a git checkout (`composer install`, a development dependency). During development, composer links the local `../bash-tools` checkout when there is one (path repository, `dev-dev`): switch to a released version before a release (and back to `dev-dev` with the path repository after it).

The PHP dependencies are locked for PHP 8.1 (`config.platform.php` in `composer.json`), the oldest PHP of the supported systems (Ubuntu 22.04).

The releases are installed read-only, so nothing may be written in their `bin/` folder at run time. OpenSim writes its platform `System.Drawing.Common.dll` there on start when it differs: the build puts it in place beforehand.

## Release

One command in each project, in the order of the family (rest-php, engine, helpers, kit), each one completely before the next:

```bash
dev/release.sh          # the whole release, after one question
dev/release.sh status   # what is done and what remains
dev/release.sh beta     # patch|minor|major|stable|dev|alpha|beta|rc|1.2.3-beta.4: see the versions in the README of build-tools
```

It needs the `.env` of the project to say where the apt repository is (`APT_REPO_DIR`, see `.env.example` of build-tools), `gh` (logged in) and `nfpm`, and says what is missing before it does anything. `dev/switch.sh dev|release` does the composer part alone: the projects of the family linked next to this one (path repositories, `@dev`), or required by version (`^` the latest version tag of each, refused when a project changed since that release).

## Tests

`tests/Packaging/kit-check` installs the packages of the kit in a Debian container (podman): a fresh install of `opensim-kit`
from the repository with the builds of `dist/`, and the upgrade of a server of the generation before (opensim-kit 3.0.0~beta.4,
with opensim-tools and opensim-web). `DEPS=folder` adds the packages of the family built elsewhere. Where the containers run is
always on the command line: `--connection NAME` (a `podman system connection`, `--container NAME` is the same) or `--local`, never
an environment variable (one that is not exported, or dropped by sudo, would start them here). On macOS, whose podman machine is
too small for them, kit-check refuses to run without one.

```bash
dev/build.sh deb opensim-kit && tests/Packaging/kit-check --connection ursull
DEPS=../w4os-site/dist tests/Packaging/kit-check --connection ursull docker.io/library/ubuntu:26.04
```

## Survey of the web systems of the grids

`tools/grid-survey` redoes the market study of [docs/market/grid-web-systems-2026-11.md](docs/market/grid-web-systems-2026-11.md): which web system each grid of the Hypergrid Business list runs, as a dated JSON result, and the numbers of the w4os.org page ([Grids using w4os](https://w4os.org/features/grids-using-w4os)) from it. It is a development tool, not packaged (`/tools` is in `.distignore`). PHP 8.3, like the other tools of the family, with Pest for its tests.

**Refreshing the numbers when Hypergrid Business publishes a new list** (about once a month, with its "<Month> <year> OpenSim grid stats" page):

```bash
cd tools/grid-survey && composer install                       # once
bin/grid-survey run \
    --list https://www.hypergridbusiness.com/statistics/active-grids/ \
    --stats https://www.hypergridbusiness.com/statistics/november-2026-opensim-grid-stats/ \
    --month 2026-12 --only "neverworld,speculoos"                # a dry run on a few grids first, then without --only
bin/grid-survey update-page --result ../../docs/market/grid-web-systems-2026-12.json \
    --grids-json ~/Projects/sites/w4os.org/pages/03.features/01.grids/grids.json   # the diff; --write applies it
```

- `run` crawls the sites (read-only: user agent `grid-web-survey/1.0; read-only market study`, 15 s timeout, one request at a time per site with a 0.5 to 1 s pause, `robots.txt` respected, no login, no form), classifies each grid and writes `docs/market/grid-web-systems-<month>.json` (per grid: system, outcome, confidence, signals; totals by system with the shares of grids and of active users; the source month and dates; the excluded grids). The full run takes about an hour, so it goes in the background. The pages fetched are kept in `tools/grid-survey/results/<month>/pages` (not in git): `run --offline` classifies them again after a change of rules, without crawling. Without `--stats`, there are no active users, so no share by audience.
- `update-page` compares the counts with `grids.json` and prints a diff; `--write` applies it. Only the grid counts of the systems and `surveyed` (the survey month) change. The **last-update date of each interface, its source and the year a development ended stay hand-kept**: check them against each project's repository or site when the numbers are refreshed, and edit `grids.json` by hand. The list of w4os grids at the top of `grids.json` is hand-kept too (a grid may ask not to be on it): the update only says which grids run w4os and are not on it, and the other way round. The text of the page says "November 2026" in each language (`grids.*.md`): change it by hand until the template reads `surveyed`.
- The classes are the systems of the page (the ids of `grids.json`). The order of the rules is in `src/Classifier.php`, the names in `signatures/classes.json`. Laravel and other frameworks count as CMS, not as custom code. A grid with no known system is custom code, with an OpenSim interface when it has its own sign-up or login (`custom`), without when it has none (`custom_site`); a CMS product (Concrete, Grav...) counts as carrying an OpenSim interface only when its sign-up page asks for an avatar.
- Fingerprints: the technologies database of [enthec/webappanalyzer](https://github.com/enthec/webappanalyzer) (the maintained successor of Wappalyzer's rules, GPL-3.0), pinned to a commit in `signatures/webappanalyzer.sha` and downloaded into `tools/grid-survey/cache` on first use (`bin/grid-survey signatures --update` takes the latest), plus `signatures/own.json` in the same format: the OpenSim interfaces (w4os, DreamGrid, Diva Wifi, osWebInterface, Modlos, jOpenSim, Hosting4Opensim, Kitely), and the few builders and cases the database misses or no longer matches (Sandvox, WebAcappella, FrontPage, Google Sites, Grav without a generator tag). Our entries win over the database. A site that is wrongly classified is fixed there, then `run --offline` again.
- `signatures/excluded.json` lists the grids left out of the survey with the reason (today the three grids of the 3D Worldz panel, which say they run Halcyon and not OpenSim). They are not crawled, they appear in the result under `excluded` and in the summary, and they are not in the totals. The decision to keep the rule is Olivier's: remove an entry (or run with `--include-excluded`) to count them.
- Tests: `cd tools/grid-survey && vendor/bin/pest`. They use saved pages (`tests/fixtures/crawls`, trimmed from real sites) and a subset of the database: no network, no site crawled.

## References

- http://opensimulator.org
- https://github.com/opensim/opensim
- http://opensimulator.org/wiki/UnofficialDebPackages
- http://opensimulator.org/wiki/Related_Software

**Main modules**

- **Search**: https://github.com/GuduleLapointe/OpenSimSearch, in `modules/OpenSimSearch` (fork of https://github.com/kcozens/OpenSimSearch, very old but still the source for builds)

**Currency modules**

Active:

- **Gloebit**: https://github.com/gloebit/opensim-moneymodule-gloebit
    - Compiled dlls https://github.com/gloebit/opensim-moneymodule-gloebit/releases
- **PayPal**: https://github.com/Outworldz/DTL-PayPal
- **DTL/NSL**: https://github.com/MTSGJ/opensim.currency
- **Podex Exchange**: https://www.podex.info/p/setting-up-money-server.html (service active, but see below for dll module)

Outdated:

- **Generic MoneyServer**: used by Podex Exchange and other money implementations as well as for fictional currencies. The Original svn repo vanished (http://www.nsl.tuis.ac.jp/svn/opensim/opensim.currency/trunk). Podex website mentions https://github.com/MTSGJ/opensim.currency, which is active, but has a disclaimer to investicate: "Web Monitor function (ASP.NET) is removed from original DTL Currency. So, this version is less secure than original version!! Please use this at Your Own Risk!!". Many other forks were made by various grid operators, a comparison of them could be useful to determine which is the best option to include or if we should create our own.
- **Virwox** (abandonned), as well as OMEconomy-Modules, make sure to remove any remaining references

**Additional tools**

- https://github.com/way4os/w4os-helpers/
- https://github.com/GuduleLapointe/w4os/
- http://opensimulator.org/wiki/Webinterface
- https://github.com/Outworldz/os-webrtc-janus (webrtc voice)

**Alternative distributions**

- https://www.osgrid.org/download (most popular)
- https://github.com/Outworldz/DreamGrid-Opensim (dist 7.2 but based on opensim 0.9.3.1)
- https://github.com/diva (outdated)

**OpenSimulator.org resources**

- [Console commands reference](http://opensimulator.org/wiki/Server_Commands):
- [OSSL functions reference](http://opensimulator.org/wiki/Category:OSSL_Functions)
