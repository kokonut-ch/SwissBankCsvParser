<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\ZKB\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function zkbParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function zkbFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a ZKB statement', function () {
    $file = zkbParser()->parse(zkbFixture());

    expect($file->bank->key)->toBe('zkb')
        ->and($file->profile)->toBe('zkb.statement')
        ->and($file)->toHaveCount(3);
});

it('takes the currency from the amount heading', function () {
    $file = zkbParser()->parse(zkbFixture());

    // "Belastung CHF" — the header block is absent entirely.
    expect($file->account->currency)->toBe('CHF')
        ->and($file->rows[0]->currency)->toBe('CHF');
});

it('signs amounts from the column and reads Swiss thousands', function () {
    $rows = zkbParser()->parse(zkbFixture())->rows;

    expect($rows[0]->amount)->toBe('-3202.00')
        ->and($rows[1]->amount)->toBe('243.03')
        ->and($rows[2]->amount)->toBe('-1023.00')
        ->and($rows[2]->balance)->toBe('26910.77');
});

it('joins a description spread over four columns', function () {
    $rows = zkbParser()->parse(zkbFixture())->rows;

    expect($rows[1]->label)->toBe('Gutschrift Auftraggeber l987654321 PAYOUT Herr Muster, Zürich');
});

it('folds a continuation line into the row above', function () {
    $file = zkbParser()->parse(zkbFixture());

    expect($file)->toHaveCount(3)
        ->and($file->rows[2]->label)
        ->toBe('Lastschrift: Grundversicherung Grundversicherung Muster AG Rechnung Nr. 4471');
});

it("prefers the bank's own reference over the generic one", function () {
    $rows = zkbParser()->parse(zkbFixture())->rows;

    expect($rows[0]->reference)->toBe('L123456789');
});

it('reads the value date and the balance', function () {
    $rows = zkbParser()->parse(zkbFixture())->rows;

    // All three balances, so the chain stays checked: the file is newest
    // first, and each older balance plus the newer amount must equal the
    // newer balance.
    expect($rows[0]->date->format('Y-m-d'))->toBe('2026-02-11')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-02-10')
        ->and($rows[0]->balance)->toBe('23951.80')
        ->and($rows[1]->balance)->toBe('27153.80')
        ->and($rows[2]->balance)->toBe('26910.77');
});

it('does not claim the old six-column layout, which names no bank', function () {
    $csv = "\"Datum\";\"Buchungstext\";\"Konto\";\"Whg\";\"Belastung\";\"Gutschrift\"\n"
        ."\"13.02.2026\";\"Zahlung Muster AG\";\"CH9300762011623852957\";\"CHF\";\"75.30\";\"\"\n";

    expect(zkbParser()->supports($csv))->toBeFalse();
});
