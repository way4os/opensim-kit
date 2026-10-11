<?php

declare(strict_types=1);

namespace OpenSimKit\Console;

/**
 * The help of `opensim`: the commands, by what they are for.
 */
final class Help
{
    public const USAGE = 'opensim [-hvq] <command> [instance] [action] [options]';

    public static function text(): string
    {
        return 'usage: ' . self::USAGE . <<<'HELP'


Type 'opensim <command> --help' or 'opensim help <command>' for specific help on a command.

Commands:
    status
    start [target...]
    stop|restart [now] [target...]
    enable|disable <instance...>
    enable|disable <grid> search [<url prefix>]
    enable|disable <grid> economy [beta|gloebit] [<url prefix>]
    enable|disable <grid> profiles
    console|screen <instance>
    command <instance> <text>|-
    ports [instance...] [--publish|--ufw]

Grids
    setup
    import [grid] FILE [--grid] [--simulator] [--users] [--apply]
    web [grid] [show|check]
    web [grid] snippet <caddy|nginx|apache>
    next <grid> location [X,Y] [COUNT] [-e X,Y]

Archives and backups
    save iar [GRID [SIM]] [options] [--keep N] FIRST LAST PATH [PASSWORD] [FILE]
    load iar [GRID [SIM]] [-m|--merge] FIRST LAST PATH [PASSWORD] [FILE]
    save oar [GRID [REGION|SIM]] [options] [--keep N] [FILE]
    load oar [GRID [REGION|SIM]] [--merge] [options] [FILE]
    backup [GRID [SIM]] [--logs] [--archives] [--output DIR] [--keep N]

This machine
    next port [-e PORT] [MIN] [COUNT]
    profile list|add|default|remove
    oar pack|info|check|unpack
    rest --url http://HOST:PORT --user USER (-- <command> | - | --repl)
    install-dotnet  install the .NET runtime OpenSim needs (as root)

A target is a grid (its Robust and its simulators), a simulator, or robust:<grid> for the Robust alone.

    -h|--help       display this help
    -v|--verbose    verbose mode
    -q|--quiet      quiet mode

HELP;
    }
}
