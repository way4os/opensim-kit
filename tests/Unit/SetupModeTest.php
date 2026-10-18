<?php

use OpenSimKit\Setup\SetupMode;

describe('where the setup is gone through', function () {
    test('an option decides, the question is not asked', function () {
        $ask = static fn(): string => throw new RuntimeException('asked');

        expect(SetupMode::resolve(['--web'], $ask))->toBe(SetupMode::WEB)
            ->and(SetupMode::resolve(['--terminal'], $ask))->toBe(SetupMode::TERMINAL);
    });

    test('without an option the person chooses, and without a person it is the terminal', function () {
        expect(SetupMode::resolve([], static fn(): string => SetupMode::WEB))->toBe(SetupMode::WEB)
            ->and(SetupMode::resolve([], null))->toBe(SetupMode::TERMINAL);
    });

    test('a script that asks the questions is the folder of its pipes, whatever else is said', function () {
        $ask = static fn(): string => throw new RuntimeException('asked');

        expect(SetupMode::resolve(['--frontend', '/tmp/pipes'], $ask))->toBe(SetupMode::SCRIPT)
            ->and(SetupMode::resolve(['--frontend=/tmp/pipes', '--web'], $ask))->toBe(SetupMode::SCRIPT)
            ->and(SetupMode::frontend(['--frontend', '/tmp/pipes']))->toBe('/tmp/pipes')
            ->and(SetupMode::frontend(['--frontend=/tmp/p']))->toBe('/tmp/p')
            ->and(SetupMode::frontend(['--frontend']))->toBeNull()
            ->and(SetupMode::frontend(['--web']))->toBeNull();
    });

    test('instructions give the address, and the way to reach it from another machine', function () {
        $text = SetupMode::instructions('http://127.0.0.1:8123/?t=abc', 8123, false, true);

        expect($text)->toContain('http://127.0.0.1:8123/?t=abc')
            ->toContain('ssh -L 8123:127.0.0.1:8123')
            ->and(SetupMode::instructions('http://127.0.0.1:8123/?t=abc', 8123, true, false))->not->toContain('ssh');
    });

    test('port is the one asked for, when it is a port a person may listen on', function () {
        expect(SetupMode::port(['--web', '--port=8123']))->toBe(8123)
            ->and(SetupMode::port(['--port', '8123', '--web']))->toBe(8123)
            ->and(SetupMode::port(['--web']))->toBeNull()
            ->and(SetupMode::port(['--port=80']))->toBeNull()
            ->and(SetupMode::port(['--port=abc']))->toBeNull();
    });
});

describe('where the web side listens and who may come', function () {
    $ssh = ['SSH_CONNECTION' => '203.0.113.7 51234 198.51.100.20 22'];

    test('on the machine itself, nothing leaves it', function () {
        expect(SetupMode::network(['--web'], [], 'vonda'))
            ->toBe(['listen' => '127.0.0.1', 'allow' => null, 'host' => '127.0.0.1', 'open' => false]);
    });

    test('over SSH, it listens everywhere for the address the person connected from only, and gives the address they connected to', function () use ($ssh) {
        expect(SetupMode::network(['--web'], $ssh, 'vonda'))
            ->toBe(['listen' => '0.0.0.0', 'allow' => ['203.0.113.7'], 'host' => '198.51.100.20', 'open' => true]);
    });

    test('options say otherwise: the machine only, other addresses, anyone, another name', function () use ($ssh) {
        expect(SetupMode::network(['--listen', 'local'], $ssh)['open'])->toBeFalse()
            ->and(SetupMode::network(['--allow=192.0.2.1, 192.0.2.2'], $ssh)['allow'])->toBe(['192.0.2.1', '192.0.2.2'])
            ->and(SetupMode::network(['--allow=any'], $ssh)['allow'])->toBeNull()
            ->and(SetupMode::network(['--host=grid.example.org'], $ssh)['host'])->toBe('grid.example.org')
            ->and(SetupMode::network(['--listen=all', '--allow=any', '--host=vonda'], [], 'x'))->toBe(['listen' => '0.0.0.0', 'allow' => null, 'host' => 'vonda', 'open' => true]);
    });

    test('open to the network without knowing who comes is refused, as is what is not an address', function () use ($ssh) {
        expect(fn() => SetupMode::network(['--listen=all'], [], 'vonda'))->toThrow(InvalidArgumentException::class, '--allow')
            ->and(fn() => SetupMode::network(['--listen=somewhere'], $ssh))->toThrow(InvalidArgumentException::class, '--listen')
            ->and(fn() => SetupMode::network(['--allow=not-an-ip'], $ssh))->toThrow(InvalidArgumentException::class, '--allow');
    });

    test('an IPv4 address that arrives as an IPv6 one is the same address', function () {
        expect(\OpenSimKit\Web\WizardServer::address('::ffff:203.0.113.7'))->toBe('203.0.113.7')
            ->and(\OpenSimKit\Web\WizardServer::address('2001:DB8::1'))->toBe('2001:db8::1');
    });

    test('instructions of an open server say who can reach it, that it is not encrypted, and the firewall', function () {
        $text = SetupMode::instructions('http://198.51.100.20:8123/?t=abc', 8123, false, true, true, ['203.0.113.7']);

        expect($text)->toContain('http://198.51.100.20:8123/?t=abc')->toContain('203.0.113.7')->toContain('not encrypted')->toContain('firewall')
            ->not->toContain('This is a remote session');
    });
});
