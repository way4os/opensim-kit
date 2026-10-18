<?php

namespace OpenSimKit\Setup;

use OpenSim\Provisioning\Slug;
use OpenSim\Runtime\Environment;
use OpenSimKit\Ui\EditConfig;
use OpenSimKit\Ui\PresetUi;
use OpenSim\Models\Grid;
use OpenSim\Provisioning\AccountImporter;
use OpenSim\Provisioning\AccountList;
use OpenSim\Provisioning\DatabaseService;
use OpenSim\Exceptions\SetupFailedException;
use OpenSim\Provisioning\SetupFile;
use OpenSim\Provisioning\SimulatorConfig;
use OpenSim\Provisioning\UiInterface;

/**
 * Makes a setup that is described in a file (see SetupFile): the grid, its simulators and their regions,
 * then the accounts. It is the setup wizard, answered from the file: the same questions, the same checks,
 * the same files, with nobody to type.
 */
final class SetupRunner
{
    /** The questions the user still answers when asked to (the plan to accept, the administrator of the database). */
    public const ASK = ['Enter the credentials', 'Administrator user', 'Password of ', 'Try again'];

    public function __construct(private UiInterface $ui) {}

    /**
     * The quick setup: the plan of the grid and of its first simulator are made first, shown together, and nothing
     * is written until the user accepts them (or edits them: EditConfig).
     *
     * @param array<string,mixed> $data  a setup (SetupFile::parse): a grid to make, its owner, one simulator
     * @return ?array{0:string,1:string} the nick of the grid, the instance of its simulator; null when abandoned
     * @throws EditConfig when the user does not accept the plan and wants to edit it
     */
    public function runQuick(array $data): ?array
    {
        $gridUi = new PresetUi($this->ui, SetupFile::gridPrompts($data), self::ASK);
        $prepared = (new NewGrid($gridUi))->prepare(null, SetupFile::gridValues($data));
        if ($prepared === null) {
            return null;
        }
        [$gridPlan, $profile] = $prepared;

        // The first simulator is planned for the grid as it will be: its ports, its database, its first region
        $simulatorUi = new PresetUi($this->ui, SetupFile::simulatorPrompts($data, 0), self::ASK);
        $simulatorPlan = (new NewSimulator($simulatorUi))->prepare(Grid::planned(Environment::forProfile($profile), $gridPlan), $profile, null, self::simulatorValues($data, 0));
        if ($simulatorPlan === null) {
            return null;
        }

        $this->ui->note(
            (new NewGrid($gridUi))->describe($gridPlan) . "\n\n" . (new NewSimulator($simulatorUi))->describe($simulatorPlan),
        );
        if (
            $this->ui->choose(
                _('Apply this configuration?'),
                ['yes' => _('Continue'), 'no' => _('Edit config')],
                'yes',
            ) !== 'yes'
        ) {
            throw new EditConfig();
        }

        $nick = (new NewGrid($gridUi))->complete($gridPlan, $profile, true);
        $grid = $nick === null ? null : Grid::find(Environment::forProfile($profile), $nick);

        return $grid === null ? null : (new NewSimulator($simulatorUi))->complete($simulatorPlan, $grid, $profile, true);
    }

    /**
     * A simulator that joins a grid that exists: the grid (a preset, or its address) and the owner of its estate are in the data, the
     * rest is what the setup proposes. Quick: the plan is shown and accepted or not; advanced: every question, the answers proposed.
     *
     * @param array<string,mixed> $data  `join` (preset, address), `owner`, `simulators`
     * @return ?array{0:string,1:string} the grid and the instance of the simulator; null when abandoned
     */
    public function runJoin(array $data, bool $advanced): ?array
    {
        $prompts = SetupFile::simulatorPrompts($data, 0);
        // The plan is accepted by the person, not by the table
        unset($prompts['Apply this configuration']);
        $join = $data['join'] ?? [];
        $prompts['Grid to join'] = (string) ($join['preset'] ?? '') !== '' ? (string) $join['preset'] : '-';
        $prompts['Address of the grid'] = (string) ($join['address'] ?? '');
        $ui = new PresetUi($this->ui, $prompts, [...self::ASK, 'Apply this configuration'], $advanced);

        return (new NewSimulator($ui))->run(null, null, true, self::simulatorValues($data, 0), $advanced);
    }

    /**
     * @param array<string,mixed> $data  a setup (SetupFile::parse)
     * @param list<string> $ask  the questions still asked of the user (self::ASK for the quick setup)
     * @param string $resultFile  where the passwords made for the accounts of the list are written
     * @param bool $prefill  every question is asked, the file giving the answers proposed (to edit a setup)
     * @return ?array{0:string,1:string} the nick of the grid, the instance of its last simulator; null when abandoned
     */
    public function run(array $data, array $ask = [], string $resultFile = '', bool $prefill = false): ?array
    {
        $profile = (new InstallConfig())->profile();
        $etcRoot = (string) ($profile['EtcRoot'] ?? '');
        $nick = (string) ($data['grid']['nick'] ?? '');

        if (trim((string) ($data['grid']['name'] ?? '')) !== '') {
            if ($nick !== '' && is_dir("$etcRoot/grids/$nick")) {
                $this->ui->error(sprintf(_("The grid '%s' exists already: this setup makes new ones."), $nick));

                throw new SetupFailedException('grid exists');
            }
            $nick = (new NewGrid(new PresetUi($this->ui, SetupFile::gridPrompts($data), $ask, $prefill)))->run(null, SetupFile::gridValues($data), $prefill);
            if ($nick === null) {
                return null;
            }
        } elseif ($nick === '') {
            // No grid in the file: the simulators join the only grid there is
            $grids = array_map('basename', glob("$etcRoot/grids/*", GLOB_ONLYDIR) ?: []);
            $nick = count($grids) === 1 ? $grids[0] : '';
            if ($nick === '') {
                $this->ui->error(_('This setup has no grid, and there is not exactly one on this machine to use.'));

                throw new SetupFailedException('no grid');
            }
        }

        // An owner whose password is given hashed is made in the database, as the accounts of a list are: the
        // console of Robust makes accounts from a password
        $owner = $data['owner'] ?? null;
        if (is_array($owner) && ($owner['password_hash'] ?? '') !== '' && ($owner['password'] ?? '') === '') {
            [$first, $lastName] = array_pad(explode(' ', trim((string) $owner['name']), 2), 2, '');
            $this->users(
                $profile,
                $nick,
                [
                    [
                        'first' => $first,
                        'last' => $lastName,
                        'email' => (string) ($owner['email'] ?? ''),
                        'password_hash' => (string) $owner['password_hash'],
                        'password_salt' => (string) ($owner['password_salt'] ?? ''),
                    ],
                ],
                $resultFile,
            );
        }

        $last = null;
        foreach ($data['simulators'] ?? [] as $i => $simulator) {
            $made = (new NewSimulator(new PresetUi($this->ui, SetupFile::simulatorPrompts($data, $i), $ask, $prefill)))->run(
                $nick,
                null,
                false,
                self::simulatorValues($data, $i),
                $prefill,
            );
            if ($made === null) {
                return null;
            }
            $last = $made;
            foreach (array_slice($simulator['regions'] ?? [], 1, null, true) as $j => $_) {
                (new NewSimulator(new PresetUi($this->ui, SetupFile::regionPrompts($data, $i), $ask, $prefill)))->addRegion(
                    $made[0],
                    $this->simulatorName($made),
                    SetupFile::regionValues($data, $i, $j),
                    $prefill,
                );
            }
        }

        if (!empty($data['users'])) {
            $this->users($profile, $nick, $data['users'], $resultFile);
        }

        return [$nick, $last[1] ?? ''];
    }

    /**
     * What a setup file says of a simulator, its estate and its first region, by the names of the fields.
     *
     * @param array<string,mixed> $data
     * @return array{simulator:array<string,mixed>,owner:array<string,string>,region:array<string,mixed>}
     */
    private static function simulatorValues(array $data, int $i): array
    {
        return [
            'simulator' => SetupFile::simulatorValues($data, $i),
            'owner' => SetupFile::owner($data, $i),
            'region' => SetupFile::regionValues($data, $i, 0),
        ];
    }

    /** @param array{0:string,1:string} $made nick and instance of a simulator */
    private function simulatorName(array $made): string
    {
        $etcRoot = (string) ((new InstallConfig())->profile()['EtcRoot'] ?? '');
        if (($name = SimulatorConfig::readName("$etcRoot/grids/{$made[0]}/sims/{$made[1]}.ini")) !== null) {
            return $name;
        }
        $prefix = Slug::slug($made[0]) . '_';

        return str_starts_with($made[1], $prefix) ? substr($made[1], strlen($prefix)) : $made[1];
    }

    /** @param list<array<string,mixed>> $users */
    private function users(array $profile, string $nick, array $users, string $resultFile): void
    {
        $grid = Grid::find(Environment::forProfile($profile), $nick);
        $list = AccountList::parse((string) json_encode(['accounts' => $users]), 'json');
        if ($grid === null || $grid->isRemote() || $list['accounts'] === []) {
            $this->ui->warn(_('The accounts of the list were not made: no grid of this machine to make them in.'));

            return;
        }

        $done = AccountImporter::forDatabase(new DatabaseService($this->ui))->run($grid, $list['accounts'], true, true);
        if ($done['error'] !== null) {
            $this->ui->error($done['error']);

            return;
        }
        $counts = [];
        foreach ($done['results'] as $row) {
            $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
        }
        $this->ui->note(
            sprintf(
                _('Accounts: %s'),
                implode(', ', array_map(static fn(string $s, int $n): string => "$n $s", array_keys($counts), $counts)),
            ),
        );

        $generated = array_filter(
            $done['results'],
            static fn(array $row): bool => $row['generated'] && $row['status'] !== AccountImporter::EXISTS,
        );
        if ($generated !== []) {
            $resultFile = $resultFile !== '' ? $resultFile : 'users-result-' . date('Ymd-His') . '.csv';
            $umask = umask(0o077);
            file_put_contents($resultFile, AccountImporter::resultCsv($done['results']));
            umask($umask);
            chmod($resultFile, 0o600);
            $this->ui->note(
                sprintf(_('The passwords made for the accounts are in %s (only you can read it).'), $resultFile),
            );
        }
    }
}
