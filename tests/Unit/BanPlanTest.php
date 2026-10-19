<?php

declare(strict_types=1);

/**
 * The plan to ban people from a grid: found by name or UUID, what they left deleted in the database of each service, a ban in every
 * estate, and the files for the administrator. Nothing is run. The databases are in memory (SQLite).
 */

use OpenSim\Config\GridConfig;
use OpenSim\Data\Connections;
use OpenSim\Data\OSPDO;
use OpenSimKit\Console\BanPlan;

const PLAN_ADA = '11111111-1111-1111-1111-111111111111';
const PLAN_BANNED_ALREADY = '22222222-2222-2222-2222-222222222222';
const PLAN_NOBODY = '55555555-5555-5555-5555-555555555555';

/** The files of a grid and its simulator, which keeps its estates in the database of its regions. */
function plan_grid(): GridConfig
{
    $dir = test_tmp() . '/banplan-' . bin2hex(random_bytes(4));
    mkdir("$dir/sims", 0o755, true);
    $db = static fn(string $name) => "ConnectionString = \"Data Source=localhost;Database=$name;User ID=os;Password=pw;\"\n";
    file_put_contents("$dir/Robust.HG.ini", "[DatabaseService]\n " . $db('robust'));
    file_put_contents("$dir/sims/alpha.ini", "[DatabaseService]\n " . $db('alpha_db'));

    return GridConfig::fromFolder($dir);
}

/** One database for all, with Ada, her things, and two estates. */
function plan_database(): OSPDO
{
    $pdo = new OSPDO(['driver' => 'sqlite', 'name' => ':memory:']);
    $pdo->exec('CREATE TABLE UserAccounts (PrincipalID CHAR(36) PRIMARY KEY, ScopeID CHAR(36), FirstName VARCHAR(64), LastName VARCHAR(64), Email VARCHAR(64), Created INT, UserLevel INT DEFAULT 0, UserFlags INT DEFAULT 0, UserTitle VARCHAR(64) DEFAULT \'\', active INT DEFAULT 1)');
    $pdo->exec('CREATE TABLE GridUser (UserID VARCHAR(255) PRIMARY KEY, HomeRegionID CHAR(36), LastRegionID CHAR(36), Online CHAR(5) DEFAULT \'false\', Login CHAR(16) DEFAULT \'0\', Logout CHAR(16) DEFAULT \'0\')');
    $pdo->exec('CREATE TABLE userprofile (useruuid CHAR(36) PRIMARY KEY, profileImage CHAR(36), profileAboutText TEXT, profileURL VARCHAR(255), profilePartner CHAR(36), profileWantToMask INT, profileWantToText TEXT, profileSkillsMask INT, profileSkillsText TEXT, profileLanguages TEXT, profileFirstImage CHAR(36), profileFirstText TEXT)');
    $pdo->exec('CREATE TABLE assets (id CHAR(36), CreatorID VARCHAR(255))');
    $pdo->exec('CREATE TABLE prims (UUID CHAR(36), OwnerID CHAR(36))');
    $pdo->exec('CREATE TABLE hg_traveling_data (SessionID CHAR(36), UserID CHAR(36), GridExternalName VARCHAR(255))');
    $pdo->exec('CREATE TABLE estate_settings (EstateID INTEGER PRIMARY KEY, EstateName VARCHAR(64), EstateOwner CHAR(36))');
    $pdo->exec('CREATE TABLE estate_map (RegionID CHAR(36), EstateID INT)');
    $pdo->exec('CREATE TABLE estate_managers (EstateID INT, uuid CHAR(36))');
    $pdo->exec('CREATE TABLE estate_users (EstateID INT, uuid CHAR(36))');
    $pdo->exec('CREATE TABLE estate_groups (EstateID INT, uuid CHAR(36))');
    $pdo->exec('CREATE TABLE estateban (EstateID INT, bannedUUID CHAR(36), bannedIp VARCHAR(16), bannedIpHostMask VARCHAR(16), bannedNameMask VARCHAR(255) DEFAULT NULL)');
    $pdo->exec("INSERT INTO UserAccounts (PrincipalID, FirstName, LastName) VALUES ('" . PLAN_ADA . "', 'Ada', 'Love')");
    $pdo->exec("INSERT INTO assets VALUES ('a1', '" . PLAN_ADA . "'), ('a2', '" . PLAN_ADA . "')");
    $pdo->exec("INSERT INTO prims VALUES ('p1', '" . PLAN_ADA . "')");
    $pdo->exec("INSERT INTO hg_traveling_data VALUES ('s1', '" . PLAN_ADA . "', 'other.example.org:8002')");
    $pdo->exec("INSERT INTO estate_settings VALUES (101, 'Alpha Land', '" . PLAN_ADA . "'), (102, 'Zoo', '" . PLAN_ADA . "')");
    $pdo->exec("INSERT INTO estateban VALUES (102, '" . PLAN_BANNED_ALREADY . "', '', '', NULL)");

    return $pdo;
}

afterEach(fn() => Connections::using(null));

describe('ban plan', function () {
    test('deletes what the people left and bans them in every estate, once', function () {
        $pdo = plan_database();
        Connections::using(static fn(): OSPDO => $pdo);

        $plan = BanPlan::make(plan_grid(), [PLAN_ADA, 'Ada Love', PLAN_BANNED_ALREADY]);

        expect($plan->problems)->toBe([])
            ->and(array_map(static fn($u): string => $u->label(), $plan->users))->toBe(['Ada.Love', PLAN_BANNED_ALREADY])
            ->and($plan->cleanup)->toContain('-- Ada.Love (' . PLAN_ADA . ')')
                ->toContain('-- Delete 2 rows from robust.assets')
                ->toContain("DELETE FROM `alpha_db`.`prims` WHERE OwnerID = '" . PLAN_ADA . "';")
            ->and($plan->deletes())->toBe(3)
            ->and($plan->ban)->toContain("INSERT INTO `alpha_db`.estateban (EstateID, bannedUUID, bannedIp, bannedIpHostMask) VALUES (101, '" . PLAN_ADA . "', '', '');")
            // Ada is banned in both estates, the other one in the estate that does not ban them already
            ->and($plan->bans())->toBe(3)
            ->and(implode("\n", $plan->ban))->not->toContain("VALUES (102, '" . PLAN_BANNED_ALREADY . "'")
            ->and($plan->traveling)->toBe(["s1\t" . PLAN_ADA . "\tother.example.org:8002"]);
    })->skip(fn() => !extension_loaded('pdo_sqlite'), 'pdo_sqlite is missing');

    test('says what it cannot do: a name nobody has, a text that is not a name, a table whose database is unknown', function () {
        $pdo = plan_database();
        Connections::using(static fn(): OSPDO => $pdo);

        $plan = BanPlan::make(plan_grid(), ['Nobody Here', "O'Neil Smith", 'single']);

        expect($plan->users)->toBe([])
            ->and($plan->problems)->toBe([
                "no user found for 'Nobody Here'",
                "'O'Neil Smith' is neither a name (First Last, First.Last@grid) nor a UUID",
                "'single' is neither a name (First Last, First.Last@grid) nor a UUID",
            ]);
    })->skip(fn() => !extension_loaded('pdo_sqlite'), 'pdo_sqlite is missing');

    test('is in files for the administrator, nothing is run', function () {
        $pdo = plan_database();
        Connections::using(static fn(): OSPDO => $pdo);
        $plan = BanPlan::make(plan_grid(), [PLAN_ADA]);
        $files = $plan->files('2026-10-12');

        expect(array_keys($files))->toBe(['users', 'cleanup', 'ban', 'hg_traveling_data'])
            ->and($files['users'])->toBe(PLAN_ADA . "\tAda\tLove\t\n")
            ->and($files['cleanup'])->toStartWith("-- Delete accounts and data 2026-10-12\n")
            ->and($files['ban'])->toContain('INSERT INTO');
        // The accounts are still there
        expect((int) $pdo->get_var('SELECT COUNT(*) FROM UserAccounts'))->toBe(1)
            ->and((int) $pdo->get_var('SELECT COUNT(*) FROM estateban'))->toBe(1);
    })->skip(fn() => !extension_loaded('pdo_sqlite'), 'pdo_sqlite is missing');
});
