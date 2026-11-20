# The w4os rename map

The suite is **w4os, "Way for OpenSim"** (decided 2026-11-20). Projects tied to the w4os environment take the w4os name; what
installs or derives from OpenSimulator itself keeps "opensim". The rename comes after the tests under way and before the public
release, in one block, with transitional packages for every name already published.

Decided items are marked ✅ (all decided on 2026-11-20).

## Packages, repositories, Composer

| Now | After | Status |
| --- | --- | --- |
| opensim-engine (deb, magicoli/opensim-engine, GitHub GuduleLapointe/opensim-engine) | w4os-engine | ✅ |
| opensim-helpers (deb, magicoli/opensim-helpers, GitHub GuduleLapointe/opensim-helpers) | w4os-helpers | ✅ |
| w4os-site | unchanged | ✅ |
| w4os-wp (GitHub GuduleLapointe/w4os, wordpress.org slug w4os-opensimulator-web-interface) | unchanged; the wordpress.org slug cannot move | ✅ |
| grav-plugin-w4os | unchanged | ✅ |
| w4os-views | unchanged | ✅ |
| opensim-kit, opensim, opensim-core, opensim-0.9.3.0-*, opensim-unstable | unchanged: the kit installs OpenSimulator and the tools around it (w4os, Gloebit, the search module…) | ✅ |
| opensim-cli (deb, magicoli/opensim-cli, GitHub GuduleLapointe/opensim-cli) | w4os-cli; its command stays `opensim` | ✅ |
| opensim-rest-php (deb, magicoli/opensim-rest-php, command opensim-rest-cli) | unchanged: a generic client of OpenSimulator's REST console, with or without w4os | ✅ |

GitHub organisation: **way4os** (w4os is taken by an inactive account; way4os also teaches the pronunciation, "way-four-OS"),
full repository names inside (github.com/way4os/w4os-engine, way4os/opensim-kit…), Composer vendor `w4os/` (free on Packagist). ✅

GitHub keeps redirecting a renamed repository's old URLs; Packagist gets the new name and the old one marked abandoned with its
replacement.

## Commands

| Now | After | Status |
| --- | --- | --- |
| `opensim` | unchanged | ✅ |
| `w4os` alias of `opensim` | not now: wait for complaints, keep `w4os` free | ✅ |
| `w4os-site` | unchanged | ✅ |
| `opensim-rest-cli` | unchanged, with opensim-rest-php | ✅ |
| `opensim-api` (the engine's API command) | w4os-api | ✅ |

## Paths, services, accounts

| Now | After | Status |
| --- | --- | --- |
| /etc/opensim/opensim.conf (suite config) | /etc/w4os/w4os.conf, migrated at upgrade | ✅ |
| /etc/opensim (grids, robust.d, opensim.d, presets: OpenSimulator's ini) | unchanged | ✅ |
| /usr/share/opensim-engine, /usr/share/opensim-helpers | follow their packages (/usr/share/w4os-engine, /usr/share/w4os-helpers) | ✅ |
| /var/lib/opensim (OpenSimulator data), /var/log/opensim, /var/cache/opensim | unchanged | ✅ |
| /var/lib/opensim/www (the w4os site) | /var/lib/w4os/www | ✅ |
| system account `opensim` | unchanged: it runs OpenSimulator | ✅ |
| opensim.service (the instances) | unchanged | ✅ |
| the engine API service and socket (opensim-api) | w4os-api, with the command | ✅ |

## PHP namespaces

| Now | After | Status |
| --- | --- | --- |
| engine `OpenSim\` | `W4OS\Engine\` | ✅ |
| helpers `OpenSim\Helpers\` | `W4OS\Helpers\` | ✅ |
| Grav plugin `W4os\Grav\`, w4os-views `W4OS\Views\` | one casing, `W4OS\` (an acronym, as PSR allows): `W4OS\Grav\`, `W4OS\Views\` | ✅ |
| opensim-kit `OpenSimKit\` | unchanged with the kit | ✅ |

## How the rename is done

- Transitional packages for every published name (opensim-engine, opensim-helpers, and opensim-cli if renamed): empty, depending
  on the new name, in charm, dropped after one release. `Replaces`/`Breaks` on the new packages.
- The suite config moved with `dpkg-maintscript-helper mv_conffile` (or an equivalent the engine checks).
- One commit per project, pages, tests and docs in the same change; no compatibility aliases for unpublished code.
- A profile README for way4os (repository `way4os/.github`, `profile/README.md`): what w4os is, its projects, where to start.
- Then the live tests, including the upgrade from the packages published on charm stable (beta.4) to the renamed ones.
