<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use OpenSim\Installer\Config;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim profile: the installs the tools know about.
 *
 *   opensim profile list
 *   opensim profile add NAME --core DIR [--etc DIR] [--data DIR] [--default]
 *   opensim profile default NAME
 *   opensim profile remove NAME
 *
 * A profile gives the base directories of an install (core, config, data); without --etc and --data they are the core
 * directory, as in an install by the book (/path/to/opensim/bin). The default profile is only the default of the new grids: a
 * grid that exists follows its own install.
 */
final class ProfileCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim profile list | add NAME --core DIR [--etc DIR] [--data DIR] [--default] | default NAME | remove NAME\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('profile')->setDescription('The installs the tools know about (profiles of opensim.conf)');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        $action = array_shift($args) ?? '';
        $config = new Config(getenv('OPENSIM_CONF') ?: null);
        $fail = static function (string $message, int $code = 1) use ($err): int {
            self::say($err, "profile: $message\n");

            return $code;
        };

        switch ($action) {
            case 'list':
                $default = $config->defaultProfile();
                foreach ($config->profiles() as $name) {
                    $p = $config->profile($name);
                    self::say($out, sprintf("%s%s\t%s\n", $name, $name === $default ? ' (default)' : '', $p['CoreDirectory'] ?? ''));
                }

                return self::ok();

            case 'add':
                $name = array_shift($args) ?? '';
                $opts = ['core' => null, 'etc' => null, 'data' => null];
                $default = false;
                while ($args) {
                    $a = array_shift($args);
                    if ($a === '--default') {
                        $default = true;
                    } elseif (preg_match('/^--(core|etc|data)(?:=(.*))?$/', $a, $m)) {
                        $opts[$m[1]] = $m[2] ?? array_shift($args);
                    } else {
                        return $fail(sprintf(_('Unknown option: %s'), $a), 2);
                    }
                }
                if ($name === '' || !$opts['core']) {
                    self::say($err, self::USAGE);

                    return 2;
                }
                foreach ($opts as $dir) {
                    if ($dir !== null && !is_dir($dir)) {
                        return $fail(sprintf(_('No such directory: %s'), $dir));
                    }
                }
                try {
                    $config->addProfile($name, $opts['core'], $opts['etc'], $opts['data'], $default);
                } catch (\InvalidArgumentException $e) {
                    return $fail($e->getMessage());
                }
                self::say($out, sprintf(_("Profile %s added\n"), $name));

                return self::ok();

            case 'default':
                $name = array_shift($args) ?? '';
                if (!in_array($name, $config->profiles(), true)) {
                    return $fail(sprintf(_('Unknown profile: %s'), $name));
                }
                $config->setDefaultProfile($name);

                return self::ok();

            case 'remove':
                $name = array_shift($args) ?? '';
                try {
                    if (!$config->removeProfile($name)) {
                        return $fail(sprintf(_('Unknown profile: %s'), $name));
                    }
                } catch (\RuntimeException $e) {
                    return $fail($e->getMessage());
                }
                self::say($out, sprintf(_("Profile %s removed\n"), $name));

                return self::ok();

            default:
                self::say($err, self::USAGE);

                return 2;
        }
    }
}
