Module binaries
===============

The DLLs the module packages ship, one folder per module, then one per OpenSimulator version (or `unstable`).
`packaging/opensim-modules.versions` names each file.

| Module | Versions | Built |
| --- | --- | --- |
| `opensimsearch/` | `0.9.2.2`, `0.9.3.0`, `unstable` | by us, from `modules/OpenSimSearch` against each core |

Upstream (https://github.com/kcozens/OpenSimSearch) has no release and no tag: its only binary is the
`bin/OpenSimSearch.Modules.dll` of its repository, last updated on 2018-07-14 (`1612abd`, .NET 4.6). No upstream DLL is
kept here for the earlier versions.
