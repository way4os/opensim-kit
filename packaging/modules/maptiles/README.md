# Map tiles in a folder of their own (opensim-<release>-maptiles)

OpenSim.Region.CoreModules.dll of a release built again with the upstream commits that came after it and let a simulator say
where Warp3DImageModule writes its tile (`[Map] MapTilesDirectory`). Without them, OpenSimulator 0.9.3.0 writes
`MAP-<region uuid>.png` in its working folder, the bin of the core, which our packages install read-only: the region does not
start ("A generic error occurred in GDI+").

For 0.9.3.0: branch `0.9.3.0` at `1f4a6dd1d3ff653ecc2748a7764106c30ac4ef9d` (the release), with `patches/` in order
(upstream 68b42d860, 460ed81f4, acb3dd2d6, as `git format-patch` gives them). Built again with:

    packaging/remote-build "$BUILD_HOST" 1f4a6dd1d3ff653ecc2748a7764106c30ac4ef9d build/opensim-0.9.3.0-maptiles \
        packaging/modules/maptiles/0.9.3.0/patches/*.patch
    cp build/opensim-0.9.3.0-maptiles/bin/OpenSim.Region.CoreModules.dll packaging/modules/maptiles/0.9.3.0/
