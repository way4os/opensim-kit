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

    test('the instructions give the address, and the way to reach it from another machine', function () {
        $text = SetupMode::instructions('http://127.0.0.1:8123/?t=abc', 8123, false, true);

        expect($text)->toContain('http://127.0.0.1:8123/?t=abc')
            ->toContain('ssh -L 8123:127.0.0.1:8123')
            ->and(SetupMode::instructions('http://127.0.0.1:8123/?t=abc', 8123, true, false))->not->toContain('ssh');
    });
});
