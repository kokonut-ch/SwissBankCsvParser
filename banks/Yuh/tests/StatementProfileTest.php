<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Yuh\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function yuhParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function yuhFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Yuh statement', function () {
    $file = yuhParser()->parse(yuhFixture());

    expect($file->bank->key)->toBe('yuh')
        ->and($file->profile)->toBe('yuh.statement')
        ->and($file)->toHaveCount(3);
});

it('joins the activity kind and name into the label', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    expect($rows[0]->label)->toBe('Card payment Muster Boutique')
        ->and($rows[1]->label)->toBe('Incoming payment Salaire novembre');
});

it('picks whichever of the two currency columns is filled', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    // "DEBIT CURRENCY" on the debit row, "CREDIT CURRENCY" on the credit row.
    expect($rows[0]->currency)->toBe('CHF')
        ->and($rows[1]->currency)->toBe('CHF');
});

it('signs from the column', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    expect($rows[0]->amount)->toBe('-11.32')
        ->and($rows[1]->amount)->toBe('5200.00')
        ->and($rows[2]->amount)->toBe('-250.00');
});

it('keeps the columns it does not model in the raw row', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    // Quantity, asset and price per unit have no place in a bank statement
    // model, and are not thrown away either.
    expect($rows[2]->raw)->toContain('ACME', '125.00', 'BUY');
});

it('refuses an ordinary statement', function () {
    $csv = "DATE;DESCRIPTION;DEBIT;CREDIT\n31.10.2026;Zahlung;11.32;\n";

    expect(yuhParser()->supports($csv))->toBeFalse();
});
