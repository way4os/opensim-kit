# Viewer links: the app commands of Firestorm

What a link can ask of the viewer beyond a teleport (a profile, the map, a grid to add...), read from the code of Firestorm, `master` at `69355fade6` (2026-11-17), `indra/newview` unless said. Not tried in a viewer: what the code says. The base of a later set of formats of the engine, "viewer actions", given like the teleport links (`Destination`).

## The forms

- `secondlife:///app/<command>/<params>`: the app link of the viewers.
- `hop:///app/<command>/<params>`: the same command. A hop link whose path starts with `app` is an app link (`fsslurl.cpp:346`), like a `secondlife` one; with a grid, `hop://host:port/app/...`, the grid is passed to the command (`llurldispatcher.cpp:196`), and only `teleport` (`llurldispatcher.cpp:180`) and `region` (`llviewerregion.cpp:156`) read it.
- `secondlife://host:port/app/...` passes a grid the same way (`fsslurl.cpp:184`).

The scheme matters only for a link opened from outside the viewer, which the system hands to it:

| System | Schemes the installer registers | Source |
|---|---|---|
| Windows | `secondlife`, `x-grid-location-info`, `x-grid-info`, `hop` (OpenSim builds only) | `installers/windows/installer_template.nsi:717-740` |
| macOS | `secondlife`, `x-grid-location-info`, `hop` | `Info-Firestorm.plist:75-80` |
| Linux | `secondlife` only | `linux_tools/register_secondlifeprotocol.sh:36-46` |

So `hop://` from a web browser reaches Firestorm on Windows and macOS, not on Linux (unless the user registers it).

## Where a link comes from, and the trust it gets

Each command has a level for the links that do not come from a trusted place (`llcommandhandler.h:64-69`, applied in `llcommandhandler.cpp:112-167`):

- `BLOCK`: never.
- `CLICK_ONLY`: only when the link was clicked (`nav_type` `clicked`) and the command accepts it.
- `THROTTLE`: when the command accepts it (`canHandleUntrusted()`, true unless the command says otherwise, `llcommandhandler.h:84-89`); one every 5 seconds unless clicked (`llcommandhandler.cpp:39`, `:152-165`); none before the viewer is initialised (`STATE_FIRST`, `:139`).

| Where | Trusted | `nav_type` | Source |
|---|---|---|---|
| Chat, notecards, any text of the viewer | yes (text widgets are trusted by default) | `clicked` | `llurldispatcher.cpp:539-550`, `indra/llui/lltextbase.cpp:191` |
| Built-in browser (web floaters, profiles, search) | no, unless the page is set trusted | `clicked` | `llmediactrl.cpp:92`, `:1137`, `llviewermedia.cpp:3411` |
| Login page | yes (`trusted_content="true"`) | `clicked` | `skins/default/xui/en/panel_fs_login.xml:29` |
| A web browser, through the system, viewer running | no | Windows `external` (`llviewerwindow.cpp:1906`); macOS empty (`llappviewermacosx.cpp:496`); Linux empty (`llappviewerlinux.cpp:482`) | |
| A web browser, through the system, viewer not running | no | Windows: only a location (the login location) or `gridmanager` is acted on (`llstartup.cpp:4412`, `:4452-4457`); macOS: kept and dispatched once the app runs (`llappviewermacosx.cpp:102-105`) | |

A location link (`secondlife://.../Region/x/y/z`, `hop://host:port/Region/x/y/z`) is not a command: on the login page it sets the location of the login (`llurldispatcher.cpp:216-226`), else it asks the map for the region and offers the teleport (`:266`).

## The commands

"Browser" is a link from a web page through the system; "viewer" a link clicked in the chat or the built-in browser; "login" a link of the login page. ✓ works, ✗ refused, ~ partly (see the note).

| Command | Purpose | Example | Level | Browser | Viewer | Login | Source |
|---|---|---|---|---|---|---|---|
| `teleport` | teleport, after a confirmation | `app/teleport/host:port:Region/x/y/z` | CLICK_ONLY | ✗ | ✓ | ~ asks, then nothing | `llurldispatcher.cpp:368` |
| `region` | a location, as `secondlife://grid/secondlife/Region/x/y/z` | `app/region/Region%20Name/128/128/25` (integer coordinates only) | THROTTLE | ✓ | ✓ | ✓ sets the login location | `llviewerregion.cpp:141` |
| `worldmap` | the map at a region, by its name (a `host:port:Region` name links a region of another grid, as the map search does) | `app/worldmap/host:port:Region/x/y/z` | THROTTLE, clicked or external | ~ Windows only | ✓ | ✗ needs the session (`:164`) | `llfloaterworldmap.cpp:138` |
| `worldmap_global` | the map at global coordinates | `app/worldmap_global/x/y/z` | THROTTLE, clicked or external | ~ Windows only | ✓ | ? | `llfloaterworldmap.cpp:195` |
| `maptrackavatar` | the map tracking an avatar | `app/maptrackavatar/<uuid>` | THROTTLE, clicked or external | ~ Windows only | ✓ | ? | `llfloaterworldmap.cpp:241` |
| `gridmanager` | add a grid to the viewer and select it | `app/gridmanager/addgrid/<login URI, URL-encoded>` | THROTTLE | ✓ (also launching the viewer) | ✓ | ✓ updates the grid list | `fsgridhandler.cpp:1449`, `llstartup.cpp:4452-4457` |
| `agent` | an avatar: `about` (profile), `inspect`, `im`, `pay`, `requestfriend`, `offerteleport`, `mute`, `block`... | `app/agent/<uuid>/about` | THROTTLE; `about`, `inspect`, `reportAbuse` always, the others clicked or external | ~ `about`/`inspect` everywhere, the others Windows only | ✓ | ? | `llpanelprofile.cpp:350` |
| `profile` | the profile of the user (web profile) | `app/profile/...` | THROTTLE | ✓ | ✓ | ? | `llpanelprofile.cpp:324` |
| `agentself` | the own avatar of the user (Firestorm) | `app/agentself/...` | THROTTLE | ✓ | ✓ | ? | `llpanelprofile.cpp:507` |
| `group` | a group: `about`, `inspect`, `create` | `app/group/<uuid>/about` | THROTTLE; `create` clicked or external only | ✓ (not `create` on macOS/Linux) | ✓ | ✗ needs the session (`:111`) | `llgroupactions.cpp:79` |
| `parcel` | a place: its page (picture, description, teleport) | `app/parcel/<parcel id>/about` | THROTTLE | ✓ | ✓ | ? | `llpanelplaces.cpp:100` |
| `event` | an event: `about`, `enable` (a reminder) | `app/event/<id>/about` | THROTTLE | ✓ | ✓ | ? | `lleventnotifier.cpp:45` |
| `pick` | a pick: show, `create`, `edit` | `app/pick/<uuid>/...` | THROTTLE; `create` clicked or external only | ✓ (not `create` on macOS/Linux) | ✓ | ✗ needs the session (`:102`) | `llpanelprofilepicks.cpp:70` |
| `classified` | a classified: `about`, `create`, `edit` | `app/classified/<uuid>/about` | THROTTLE; `create` clicked or external only | ✓ (not `create` on macOS/Linux) | ✓ | ✗ needs the session (`:114`) | `llpanelprofileclassifieds.cpp:82` |
| `inventory` | the inventory: `show`, `select`, `filters` | `app/inventory/<uuid>/select` | THROTTLE, clicked or external | ~ Windows only | ✓ | ? | `llviewerinventory.cpp:243` |
| `search` | the search floater, with a query | `app/search/<category>/<terms>` | CLICK_ONLY | ✗ | ✓ | ? | `llfloatersearch.cpp:46` |
| `openfloater` | a floater of the viewer, the allowed ones from outside | `app/openfloater/<name>` | THROTTLE, a list of floaters | ~ | ✓ | ? | `llviewerfloaterreg.cpp:251` |
| `experience` | an experience profile | `app/experience/<uuid>/profile` | THROTTLE | ✓ | ✓ | ? | `llfloaterexperienceprofile.cpp:93` |
| `sharewithavatar` | share an item with an avatar | `app/sharewithavatar/<uuid>` | THROTTLE | ✓ | ✓ | ? | `llshareavatarhandler.cpp:37` |
| `voicecallavatar` | a voice call | `app/voicecallavatar/<uuid>` | THROTTLE, clicked or external | ~ Windows only | ✓ | ? | `llvoicecallhandler.cpp:37` |
| `chat` | say a text on a channel | `app/chat/<channel>/<text>` | THROTTLE | ✓ | ✓ | ? | `fsnearbychathub.cpp:948` (the one of `llfloaterimnearbychat.cpp:1064` is not built: `#if 0`) |
| `objectim` | the IM of an object | `app/objectim/<uuid>/...` | THROTTLE | ✓ | ✓ | ? | `fschathistory.cpp:88` |
| `appearance` | the appearance floater | `app/appearance/show` | THROTTLE | ✓ | ✓ | ? | `llappearancemgr.cpp:155` |
| `help`, `fshelp` | help pages | `app/help/...` | CLICK_ONLY, THROTTLE | ✗, ✓ | ✓ | ? | `llviewerhelp.cpp:47`, `fsslurlcommand.cpp:282` |
| `object` | an object: `inspect`, `zoomin`, `block` | `app/object/<uuid>/inspect` | BLOCK | ✗ | ✓ chat only (trusted) | ? | `llchatitemscontainerctrl.cpp:70` |
| `firestorm` | Firestorm actions: `teleportto`, `offerteleport`, `groupjoin`, `zoom`, `track`... | `app/firestorm/<uuid>/<verb>` | BLOCK | ✗ | ✓ chat only (trusted) | ? | `fsslurlcommand.cpp:47` |
| `balance`, `wear_folder`, `add_folder`, `remove_folder` | the balance, outfits | `app/wear_folder/<uuid>` | BLOCK | ✗ | ✓ chat only (trusted) | ? | `llstatusbar.cpp:1931`, `llappearancemgr.cpp:5330-5370` |
| `location_login` | the location of the login | `app/location_login/...` | BLOCK | ✗ | ✗ | ✓ | `fspanellogin.cpp:100` |

"?" is not checked: the command may need the session (most open floaters that ask the grid).

## Notes for the engine

- From a web page, `hop:///app/gridmanager/addgrid/<login URI>` (OpenSimWorld's "Add Grid") works on Windows and macOS, the viewer running or not; `secondlife:///app/...` reaches every system.
- From a web page, the profile of an avatar (`app/agent/<uuid>/about`) and a place (`app/parcel/<id>/about`, `app/region/...`) work; the map (`app/worldmap/...`) only on Windows, and not before the login.
- In the viewer (chat, built-in browser), every command but the `BLOCK` ones works; the `BLOCK` ones work in the chat only.
- A teleport from a web page is never a command (`app/teleport` is `CLICK_ONLY`): it is a location link (`hop://host:port/Region/x/y/z`, `Destination::HOP`).
