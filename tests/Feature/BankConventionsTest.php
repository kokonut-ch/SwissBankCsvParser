<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

/**
 * The conventions that make banks/ work as a directory of independent folders.
 *
 * They are cheap to keep and expensive to rediscover, so they are asserted
 * rather than merely written down in banks/README.md.
 */

/** @return list<string> */
function bankDirectories(): array
{
    $directories = glob(dirname(__DIR__, 2).'/banks/*', GLOB_ONLYDIR);

    return $directories === false ? [] : array_values($directories);
}

/** @return list<BankProfile> */
function allProfiles(): array
{
    return (new SwissBankCsvParser)->profiles()->all();
}

it('gives every bank a README, a profile, a fixture and a test', function (string $directory) {
    $bank = basename($directory);

    expect(is_file($directory.'/README.md'))->toBeTrue("{$bank} has no README.md")
        ->and(glob($directory.'/*Profile.php'))->not->toBeEmpty("{$bank} has no profile")
        ->and(glob($directory.'/tests/*Test.php'))->not->toBeEmpty("{$bank} has no tests");

    // The generic reader is the one folder with nothing to hold a fixture of.
    if ($bank !== 'Generic') {
        expect(glob($directory.'/fixtures/*.csv'))->not->toBeEmpty("{$bank} has no fixtures");
    }
})->with(bankDirectories());

it('keeps every profile id unique', function () {
    $ids = array_map(static fn (BankProfile $p): string => $p->id(), allProfiles());

    expect($ids)->toBe(array_unique($ids));
});

it('prefixes every profile id with its own bank key', function () {
    foreach (allProfiles() as $profile) {
        // The generic reader is the one exception, and deliberately so: its ids
        // say "generic" because that is what read the file, while its bank key
        // says "unknown" because that is what it knows about the bank. Bending
        // either to satisfy the rule would make one of them lie.
        if ($profile->identity()->key === 'unknown') {
            expect($profile->id())->toStartWith('generic.');

            continue;
        }

        expect($profile->id())->toStartWith($profile->identity()->key.'.');
    }
});

it('discovers a profile for every bank folder', function () {
    // Discovery is by directory scan, so a folder that ships a profile the
    // registry cannot see is a silent failure — the bank simply never matches.
    $discovered = [];

    foreach (allProfiles() as $profile) {
        $discovered[(new ReflectionClass($profile))->getNamespaceName()] = true;
    }

    foreach (bankDirectories() as $directory) {
        $namespace = 'Kokonut\\SwissBankCsvParser\\Banks\\'.basename($directory);

        expect($discovered)->toHaveKey($namespace, basename($directory).' ships no discoverable profile');
    }
});

it('names every bank and gives it a country', function () {
    foreach (allProfiles() as $profile) {
        $identity = $profile->identity();

        expect($identity->key)->not->toBeEmpty()
            ->and($identity->name)->not->toBeEmpty();

        // "unknown" is the generic reader, which belongs to no country.
        if ($identity->key !== 'unknown') {
            expect($identity->country)->toHaveLength(2);
        }
    }
});
