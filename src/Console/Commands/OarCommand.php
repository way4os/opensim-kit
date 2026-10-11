<?php

declare(strict_types=1);

namespace OpenSimKit\Console\Commands;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * opensim oar: OpenSimulator archives (see OpenSim_Oar of the engine).
 *
 *   opensim oar pack DIR [FILE]     make an OAR from a folder laid out as its content (FILE: DIR without -src, .oar)
 *   opensim oar info FILE           what the archive holds
 *   opensim oar check FILE          what is wrong in it, exit code 1 when something is
 *   opensim oar unpack FILE [DIR]   extract it (DIR: FILE without .oar, -src)
 */
final class OarCommand extends ScriptCommand
{
    protected const USAGE = "usage: opensim oar pack DIR [FILE] | info FILE | check FILE | unpack FILE [DIR]\n";

    protected function configure(): void
    {
        parent::configure();
        $this->setName('oar')->setDescription('Pack, read, check and unpack OpenSimulator archives');
    }

    protected function main(array $args, OutputInterface $out, OutputInterface $err): int
    {
        // The engine classes refuse to run outside a project that uses them
        defined('OPENSIM_ENGINE') || define('OPENSIM_ENGINE', true);

        [$action, $source, $target] = array_pad($args, 3, null);
        if ($action === null || $source === null) {
            self::say($err, self::USAGE);

            return 2;
        }

        try {
            switch ($action) {
                case 'pack':
                    $dir = rtrim($source, '/');
                    $target ??= preg_replace('/-src$/', '', $dir) . '.oar';
                    \OpenSim_Oar::pack($dir, $target);
                    self::say($out, "$target\n");

                    break;

                case 'info':
                    foreach (\OpenSim_Oar::info($source) as $key => $value) {
                        self::say($out, sprintf("%-16s %s\n", $key, is_array($value) ? implode('x', $value) : var_export($value, true)));
                    }

                    break;

                case 'check':
                    $problems = \OpenSim_Oar::check($source);
                    self::say($out, $problems === [] ? "ok\n" : implode("\n", $problems) . "\n");

                    return $problems === [] ? 0 : 1;

                case 'unpack':
                    $target ??= preg_replace('/\.oar$/', '', $source) . '-src';
                    \OpenSim_Oar::unpack($source, $target);
                    self::say($out, "$target\n");

                    break;

                default:
                    self::say($err, self::USAGE);

                    return 2;
            }
        } catch (\RuntimeException $e) {
            self::say($err, 'oar: ' . $e->getMessage() . "\n");

            return 1;
        }

        return self::ok();
    }
}
