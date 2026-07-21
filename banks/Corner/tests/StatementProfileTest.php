<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Corner\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function cornerParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function cornerFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Cornèr statement whose every row is indented', function () {
    $file = cornerParser()->parse(cornerFixture());

    expect($file->bank->key)->toBe('corner')
        ->and($file->profile)->toBe('corner.statement')
        ->and($file)->toHaveCount(2);
});

it('folds the charge and reference lines into the booking above', function () {
    $rows = cornerParser()->parse(cornerFixture())->rows;

    // Each booking is followed by a run of lines carrying its charges and its
    // bank reference. Dropping them would lose most of what the statement says.
    expect($rows[0]->label)
        ->toBe('Pagamento Muster SA Spese 40,00- CHF Ns.rif: 2026LI60101010101ABCDEFG');
});

it('takes the signed amount at face value', function () {
    $rows = cornerParser()->parse(cornerFixture())->rows;

    expect($rows[0]->amount)->toBe('-40.00')
        ->and($rows[1]->amount)->toBe('236.50')
        ->and($rows[1]->label)->toBe('Accredito cliente');
});

it('reads day-first slashed dates', function () {
    $rows = cornerParser()->parse(cornerFixture())->rows;

    expect($rows[0]->date->format('Y-m-d'))->toBe('2026-12-31')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-12-31');
});

it('ignores the title rows above the heading row', function () {
    $file = cornerParser()->parse(cornerFixture());

    expect($file->rows[0]->label)->not->toContain('Elenco movimenti');
});

it('reads the German variant, whose description column is "Bezeichnung"', function () {
    // Without that heading in the vocabulary the file still matched — through
    // its "Detail" column — and the real description was silently replaced by
    // continuation text.
    $csv = "Konto-Nr.;Erfassungsdatum;Bezeichnung;Detail;Valutadatum;Betrag\n"
        ."330217/01;31.03.2026;Zahlung Muster AG;;31.03.2026;-1.10\n";

    $file = cornerParser()->parse($csv);

    expect($file->rows[0]->label)->toBe('Zahlung Muster AG')
        ->and($file->rows[0]->amount)->toBe('-1.10');
});
