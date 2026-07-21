<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Cler\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function clerParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function clerFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Cler statement', function () {
    $file = clerParser()->parse(clerFixture());

    expect($file->bank->key)->toBe('cler')
        ->and($file->profile)->toBe('cler.statement')
        ->and($file)->toHaveCount(2);
});

it('takes the currency from the bracketed amount heading', function () {
    $file = clerParser()->parse(clerFixture());

    // "Importo di addebito (CHF)" — there is no header block to read it from.
    expect($file->account->currency)->toBe('CHF')
        ->and($file->rows[0]->currency)->toBe('CHF');
});

it('signs from the column', function () {
    $rows = clerParser()->parse(clerFixture())->rows;

    expect($rows[0]->amount)->toBe('-11.32')
        ->and($rows[0]->balance)->toBe('5467.92')
        ->and($rows[1]->amount)->toBe('411.04');
});

it('keeps the order type as an extra', function () {
    $rows = clerParser()->parse(clerFixture())->rows;

    expect($rows[0]->extras)->toBe(['Tipo di ordine' => 'Pagamento in Svizzera']);
});

it('refuses a statement without an order type column', function () {
    $csv = "Data di registrazione;Testo;Importo di addebito (CHF);Importo di accredito (CHF)\n"
        ."31.10.2026;Test;11.32;\n";

    expect(clerParser()->supports($csv))->toBeFalse();
});
