<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\BancaStato\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function bancaStatoParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function bancaStatoFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Banca Stato statement', function () {
    $file = bancaStatoParser()->parse(bancaStatoFixture());

    expect($file->bank->key)->toBe('bancastato')
        ->and($file->profile)->toBe('bancastato.statement')
        ->and($file)->toHaveCount(2);
});

it('signs from the debit and credit columns and reads Swiss thousands', function () {
    $rows = bancaStatoParser()->parse(bancaStatoFixture())->rows;

    expect($rows[0]->amount)->toBe('439.20')
        ->and($rows[0]->balance)->toBe('62409.47')
        ->and($rows[1]->amount)->toBe('-293.00');
});

it('folds the detail line into the booking above', function () {
    $rows = bancaStatoParser()->parse(bancaStatoFixture())->rows;

    expect($rows[0]->label)->toContain('Accredito cliente Muster SA')
        ->and($rows[0]->label)->toContain('importo totale originale: CHF 439.20');
});

it('reads the external reference', function () {
    $rows = bancaStatoParser()->parse(bancaStatoFixture())->rows;

    expect($rows[0]->reference)->toBe('RIF0001')
        ->and($rows[1]->reference)->toBe('RIF0002');
});

it('refuses an ordinary Italian statement', function () {
    $csv = "Data;Data valuta;Descrizione;Addebiti;Accrediti;Saldo\n07.10.2026;07.10.2026;Test;;439.20;100\n";

    expect(bancaStatoParser()->supports($csv))->toBeFalse();
});

it('does not claim the layout that numbers its orders instead of referencing them', function () {
    // Banca Stato ships a variant whose reference column is "Numero di ordine",
    // not "Rif.Esterno". Signing on the description heading alone would claim it
    // and then drop its reference on the floor without a word.
    $csv = 'Data;Data valuta;Numero di ordine;Tipo di ordine;Testo di contabilizzazione;'
        ."Addebiti;Accrediti;Saldo\n"
        ."11.10.2026;11.10.2026;ORD001;Pagamento;Descrizione Pagamento;208.85;;5000.00\n";

    expect(bancaStatoParser()->supports($csv))->toBeFalse();
});
