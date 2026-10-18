<?php

use OpenSim\Provisioning\UiInterface;
use OpenSimKit\Setup\StartSetup;

/**
 * The start of the setup: what to set up, the basics, the options with their default, quick or advanced.
 *
 * @param list<mixed> $answers what the person answers, in the order of the questions (a form: the values by key; the others: the value)
 * @return array{0:UiInterface,1:\ArrayObject<int,string>} the interface, the questions it was asked (their labels or titles)
 */
function start_ui(array $answers): array
{
    $asked = new ArrayObject();
    $ui = new class ($answers, $asked) implements UiInterface {
        public function __construct(private array $answers, private ArrayObject $asked) {}

        private function next(string $what): mixed
        {
            $this->asked[] = $what;

            return array_shift($this->answers);
        }

        public function intro(string $title): void {}
        public function note(string $message): void {}
        public function entity(string $name): string { return $name; }
        public function warn(string $message): void {}
        public function error(string $message): void {}
        public function outro(string $message): void {}
        public function choose(string $label, array $options, ?string $default = null, ?string $hint = null): string { return (string) ($this->next($label) ?? $default); }
        public function checklist(string $label, array $options, array $defaults = [], ?string $hint = null): array { return (array) ($this->next($label) ?? $defaults); }
        public function text(string $label, string $default = '', ?\Closure $validate = null, ?string $hint = null): string { return (string) ($this->next($label) ?? $default); }
        public function secret(string $label, ?\Closure $validate = null, ?string $hint = null): string { return (string) $this->next($label); }
        public function confirm(string $label, bool $default = true): bool { return (bool) ($this->next($label) ?? $default); }
        public function spin(\Closure $callback, string $message): mixed { return $callback(); }

        public function form(array $fields, string $title = ''): array
        {
            $given = (array) ($this->next($title) ?? []);
            $values = [];
            foreach ($fields as $field) {
                $values[$field['key']] = $given[$field['key']] ?? (string) ($field['default'] ?? '');
            }

            return $values;
        }
    };

    return [$ui, $asked];
}

describe('start of the setup', function () {
    test('of a grid asks what to set up, the basics, the options and how to go on, nothing else', function () {
        [$ui, $asked] = start_ui([
            'grid',
            ['name' => 'Watta World', 'login' => 'watta.world:8002', 'owner' => 'Kylo Watta', 'password' => 'secret12', 'email' => 'kylo@example.org'],
            [],
            'advanced',
        ]);

        $start = (new StartSetup($ui))->ask();

        expect($start['type'])->toBe('grid')->and($start['mode'])->toBe('advanced')
            ->and($start['data']['grid'])->toMatchArray(['name' => 'Watta World', 'hostname' => 'watta.world', 'public_port' => 8002])
            ->and($start['data']['owner'])->toBe(['name' => 'Kylo Watta', 'password' => 'secret12', 'email' => 'kylo@example.org'])
            ->and($start['data']['simulators'])->toBe([[]])
            ->and(array_values((array) $asked))->toBe(['What do you want to set up?', 'Your grid', 'Options', 'How do you want to go on?']);
    });

    test('of a grid proposes the options with their default: Hypergrid, the search of the helpers, the beta currency, no more OSSL', function () {
        [$ui] = start_ui(['grid', ['name' => 'A', 'login' => 'a.example.org:8002', 'owner' => 'Ann Bee', 'password' => 'secret12']]);

        $grid = (new StartSetup($ui))->ask()['data']['grid'];

        expect($grid)->toMatchArray(['hypergrid' => true, 'economy' => 'beta', 'ossl' => false])
            // Quick is what is proposed
            ->and((new StartSetup(start_ui(['grid', ['name' => 'A', 'login' => 'a.example.org:8002', 'owner' => 'Ann Bee', 'password' => 'secret12']])[0]))->ask()['mode'])->toBe('quick');
    });

    test('of a simulator asks the grid it joins, and who owns its estate there', function () {
        [$ui, $asked] = start_ui(['simulator', '-', 'play.example.org:8002', 'Ann Bee']);

        $start = (new StartSetup($ui))->ask();

        expect($start['type'])->toBe('simulator')
            ->and($start['data']['join'])->toBe(['preset' => '', 'address' => 'play.example.org:8002'])
            ->and($start['data']['owner'])->toBe(['name' => 'Ann Bee'])
            ->and(array_values((array) $asked))->toBe(['What do you want to set up?', 'Which grid does it join?', 'Grid URL', 'Grid owner', 'How do you want to go on?']);
    });

    test('of a simulator that joins a grid there is a preset for asks no address', function () {
        [$ui, $asked] = start_ui(['simulator', 'osgrid', 'Ann Bee']);

        $join = (new StartSetup($ui))->ask()['data']['join'];

        expect($join)->toBe(['preset' => 'osgrid', 'address' => ''])
            ->and((array) $asked)->not->toContain('Grid URL');
    });
});
