<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\EFG\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function efgParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function efgFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads an EFG statement', function () {
    $file = efgParser()->parse(efgFixture());

    expect($file->bank->key)->toBe('efg')
        ->and($file->profile)->toBe('efg.statement')
        ->and($file)->toHaveCount(2);
});

it('takes the signed amount at face value, unlike a card statement', function () {
    $rows = efgParser()->parse(efgFixture())->rows;

    expect($rows[0]->amount)->toBe('-1145.00')
        ->and($rows[0]->isDebit())->toBeTrue()
        ->and($rows[1]->amount)->toBe('411.04')
        ->and($rows[0]->balance)->toBe('5467.92');
});

it('joins the transaction kind and the description', function () {
    $rows = efgParser()->parse(efgFixture())->rows;

    expect($rows[0]->label)->toBe('Bonifico Muster SA fattura 4471')
        ->and($rows[0]->date->format('Y-m-d'))->toBe('2026-10-31');
});

it('refuses an Italian statement without the DIV column', function () {
    $csv = "Data registrazione;Data valuta;Descrizione;Importo;Saldo\n"
        ."31/10/2026;31/10/2026;Test;-1145.00;5467.92\n";

    expect(efgParser()->supports($csv))->toBeFalse();
});

it('reads the per-row currency from the DIV column', function () {
    // The real export fills DIV on every row — its own import rules even
    // require a three-letter code there to accept a line. With the column
    // empty in the fixture, the currency was never exercised at all.
    $rows = efgParser()->parse(efgFixture())->rows;

    expect($rows[0]->currency)->toBe('CHF')
        ->and($rows[1]->currency)->toBe('CHF');
});
