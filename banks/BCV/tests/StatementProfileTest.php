<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\BCV\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function bcvParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function bcvFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a BCV statement', function () {
    $file = bcvParser()->parse(bcvFixture());

    expect($file->bank->key)->toBe('bcv')
        ->and($file->profile)->toBe('bcv.statement')
        ->and($file)->toHaveCount(2);
});

it('ignores the title row above the heading row', function () {
    $rows = bcvParser()->parse(bcvFixture())->rows;

    // "Transactions list;;;;;45950.98" precedes the real headings and must not
    // be read as one of them.
    expect($rows[0]->label)->toBe('Paiement fournisseur Muster SA')
        ->and($rows[1]->label)->toBe('Virement client');
});

it('signs from the column and reads dates and balances', function () {
    $rows = bcvParser()->parse(bcvFixture())->rows;

    expect($rows[0]->amount)->toBe('-1350')
        ->and($rows[0]->date->format('Y-m-d'))->toBe('2026-09-30')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-09-30')
        ->and($rows[0]->balance)->toBe('44600.98')
        ->and($rows[1]->amount)->toBe('4250');
});

it('refuses a statement that does not call its description column "Transactions"', function () {
    $csv = "Execution date;Description;Debit;Credit;Value date;Balance\n30.09.2026;Test;1350;;30.09.2026;10\n";

    expect(bcvParser()->supports($csv))->toBeFalse();
});
