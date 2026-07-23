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

it('ignores the whole preamble above the heading row', function () {
    $rows = bcvParser()->parse(bcvFixture())->rows;

    // Eight lines — the title row, the account number and holder, empty
    // "Balance :" and "Curr. :" labels — precede the real headings, and none
    // of them is a booking.
    expect($rows[0]->label)->toBe('Paiement fournisseur Muster SA')
        ->and($rows[1]->label)->toBe('Virement client');
});

it('signs from the column and reads Swiss thousands and dates', function () {
    $rows = bcvParser()->parse(bcvFixture())->rows;

    // The Balance column is empty on every row of the published sample, so the
    // balance is honestly null rather than invented.
    expect($rows[0]->amount)->toBe('-1350.00')
        ->and($rows[0]->date->format('Y-m-d'))->toBe('2026-09-30')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-09-30')
        ->and($rows[0]->balance)->toBeNull()
        ->and($rows[1]->amount)->toBe('4250.00');
});

it('refuses a statement that does not call its description column "Transactions"', function () {
    $csv = "Execution date;Description;Debit;Credit;Value date;Balance\n30.09.2026;Test;1350;;30.09.2026;10\n";

    expect(bcvParser()->supports($csv))->toBeFalse();
});
