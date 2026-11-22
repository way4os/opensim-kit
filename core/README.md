Core
====

OpenSimulator as upstream makes it, never modified.

| Folder | Content |
| --- | --- |
| `releases/` | the official release archives (`opensim-<version>.tar.gz`), downloaded by `packaging/build` and git-ignored; the packages of the stable versions are built from their content, never from a clone (a core built again is another build of the same version) |
| `unstable/` | submodule of https://github.com/opensim/opensim (`master`), compiled for the `opensim-unstable` package |
