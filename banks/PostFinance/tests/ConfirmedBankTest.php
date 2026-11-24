<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

/**
 * What a caller does once the user has said "this is PostFinance": parse with
 * PostFinance's profiles alone. A file refused here blocks that user, even when
 * the generic reader could have read it, so every layout has to be covered.
 */
function confirmedPostFinanceParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry(array_values(array_filter(
        (new ProfileRegistry)->all(),
        static fn (BankProfile $profile): bool => $profile->identity()->key === 'postfinance',
    ))));
}

/**
 * Every fixture, with the profile that must read it and how many rows carry
 * an amount.
 *
 * @return array<string, array{0: string, 1: int}>
 */
function postFinanceLayouts(): array
{
    return [
        'efinance-de.csv' => ['postfinance.efinance', 2],
        'efinance-fr.csv' => ['postfinance.efinance', 3],
        'efinance-it.csv' => ['postfinance.efinance', 2],
        'efinance-en.csv' => ['postfinance.efinance', 2],
        'efinance-de-latin1.csv' => ['postfinance.efinance', 2],
        'efinance-it-2016.csv' => ['postfinance.efinance', 3],
        'efinance-it-iso.csv' => ['postfinance.efinance', 3],
        'efinance-it-2009.csv' => ['postfinance.efinance', 3],
        'efinance-it-2003.csv' => ['postfinance.efinance', 3],
        'efinance-2010-en.csv' => ['postfinance.efinance-2010', 5],
        'efinance-2010-de.csv' => ['postfinance.efinance-2010', 3],
        'creditcard-de.csv' => ['postfinance.creditcard', 2],
        'creditcard-fr.csv' => ['postfinance.creditcard', 2],
        'creditcard-en.csv' => ['postfinance.creditcard', 2],
        'creditcard-de-2023.csv' => ['postfinance.creditcard', 2],
        'creditcard-it-2022.csv' => ['postfinance.creditcard', 3],
        'legacy.csv' => ['postfinance.legacy', 2],
    ];
}

it('reads every PostFinance layout once the bank is confirmed', function (string $fixture) {
    [$profile, $rows] = postFinanceLayouts()[$fixture];

    $file = confirmedPostFinanceParser()->parse((string) file_get_contents(__DIR__.'/../fixtures/'.$fixture));

    expect($file->profile)->toBe($profile)
        ->and(array_filter($file->rows, fn (Row $row) => $row->amount !== null))->toHaveCount($rows);
})->with(array_keys(postFinanceLayouts()));

it('has a line above for every fixture the bank ships', function () {
    // A fixture missing from the list would be a layout nobody checked under a
    // confirmed bank.
    $fixtures = array_map(basename(...), glob(__DIR__.'/../fixtures/*.csv') ?: []);
    sort($fixtures);

    $listed = array_keys(postFinanceLayouts());
    sort($listed);

    expect($fixtures)->toBe($listed);
});
