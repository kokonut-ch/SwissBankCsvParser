<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\UBS\CreditCardProfile;
use Kokonut\SwissBankCsvParser\Banks\UBS\SignedAmountStatementProfile;
use Kokonut\SwissBankCsvParser\Banks\UBS\SplitAmountStatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function ubsParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([
        new SplitAmountStatementProfile,
        new SignedAmountStatementProfile,
        new CreditCardProfile,
    ]));
}

function ubsFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

describe('split amount statement', function () {
    it('reads the wide portfolio export', function () {
        $file = ubsParser()->parse(ubsFixture('statement-split.csv'));

        expect($file->bank->key)->toBe('ubs')
            ->and($file->profile)->toBe('ubs.statement.split')
            ->and($file)->toHaveCount(2);
    });

    it('takes the IBAN and the currency from their columns', function () {
        $file = ubsParser()->parse(ubsFixture('statement-split.csv'));

        expect($file->account->iban)->toBe('CH9300762011623852957')
            ->and($file->account->currency)->toBeNull()
            ->and($file->rows[0]->currency)->toBe('CHF');
    });

    it('reads two-digit years', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-split.csv'))->rows;

        expect($rows[0]->date->format('Y-m-d'))->toBe('2026-06-30')
            ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-06-30');
    });

    it('builds the label from the numbered columns only', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-split.csv'))->rows;

        // The plain "Description" column holds "UBS Business Current Account",
        // the account's own name, repeated on every row. It must not end up in
        // the label.
        expect($rows[0]->label)->toBe('Zahlung Lieferant Muster AG Rechnung 4471')
            ->and($rows[1]->label)->toBe('Zahlungseingang Kunde Muster');
    });

    it('signs from the column and reads the reference and balance', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-split.csv'))->rows;

        expect($rows[0]->amount)->toBe('-1145.00')
            ->and($rows[0]->reference)->toBe('ZD81181TI0690091')
            ->and($rows[0]->balance)->toBe('7854.90')
            ->and($rows[1]->amount)->toBe('3069.00');
    });
});

describe('signed amount statement', function () {
    it('reads the modern e-banking export', function () {
        $file = ubsParser()->parse(ubsFixture('statement-signed.csv'));

        expect($file->profile)->toBe('ubs.statement.signed')
            ->and($file)->toHaveCount(2);
    });

    it('takes the sign from the amount and ignores the direction word', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-signed.csv'))->rows;

        // "Addebito/Accredito" repeats the direction in words. The sign already
        // says it; reading both would be a way to disagree with oneself.
        expect($rows[0]->amount)->toBe('-150.00')
            ->and($rows[1]->amount)->toBe('1240.55');
    });

    it('drops the clock time and falls back to the operation date', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-signed.csv'))->rows;

        // The second row has an empty booking-date column; the operation date
        // in the first column is the one UBS always fills.
        expect($rows[1]->date->format('Y-m-d'))->toBe('2026-09-20')
            ->and($rows[1]->label)->toBe('Bonifico Muster SA, Lugano Fattura 4471');
    });
});

describe('credit card', function () {
    it('reads a card statement', function () {
        $file = ubsParser()->parse(ubsFixture('creditcard.csv'));

        expect($file->profile)->toBe('ubs.creditcard')
            ->and($file)->toHaveCount(2);
    });

    it('reads the booking date from "Ecriture" and the purchase date as value date', function () {
        $rows = ubsParser()->parse(ubsFixture('creditcard.csv'))->rows;

        expect($rows[0]->date->format('Y-m-d'))->toBe('2026-11-15')
            ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-11-13')
            ->and($rows[0]->amount)->toBe('-189.00')
            ->and($rows[1]->amount)->toBe('40.00');
    });

    it('reads the settlement currency, not the original one', function () {
        $rows = ubsParser()->parse(ubsFixture('creditcard.csv'))->rows;

        expect($rows[0]->currency)->toBe('CHF')
            ->and($rows[0]->label)->toBe('www.example.ch LUGANO CHE');
    });
});

it('never lets two UBS profiles claim the same file', function (string $fixture, string $profile) {
    expect(ubsParser()->detect(ubsFixture($fixture))->count())->toBe(1)
        ->and(ubsParser()->detect(ubsFixture($fixture))->best()?->profile)->toBe($profile);
})->with([
    ['statement-split.csv', 'ubs.statement.split'],
    ['statement-signed.csv', 'ubs.statement.signed'],
    ['creditcard.csv', 'ubs.creditcard'],
]);

it('refuses an ordinary statement that names no UBS column', function () {
    $csv = "Buchungsdatum;Buchungstext;Belastung;Gutschrift;Saldo\n03.01.2026;Zahlung;100.00;;900.00\n";

    expect(ubsParser()->supports($csv))->toBeFalse();
});

it('prefers the booking date over the trade date when the two differ', function () {
    // UBS prints the trade date in the earlier column. Reading left to right
    // binds the row to the wrong date on every settled transaction — and in
    // French and Italian both headings are ordinary enough to be mistaken for
    // one another.
    $csv = 'Date de transaction;Date de comptabilisation;Date de valeur;Description 1;'
        ."Transaction no.;Débit;Crédit;Solde\n"
        ."28.06.2026;30.06.2026;30.06.2026;Paiement fournisseur;ZD811;1145.00;;7854.90\n";

    $file = ubsParser()->parse($csv);

    expect($file->rows[0]->date->format('Y-m-d'))->toBe('2026-06-30')
        ->and($file->rows[0]->amount)->toBe('-1145.00');
});

it('falls back to the trade date only when the booking date is empty', function () {
    $csv = "Data dell'operazione;Data di registrazione;Data di valuta;Moneta;"
        ."Importo della transazione;Descrizione1;N. di transazione;Note a piè di pagina\n"
        ."2026-09-20;;2026-09-20;CHF;1240.55;Bonifico;9999311;\n";

    $file = ubsParser()->parse($csv);

    expect($file->rows[0]->date->format('Y-m-d'))->toBe('2026-09-20');
});

it('reads the 2024 Italian booking-date label, Data di contabilizzazione', function () {
    // UBS renamed the Italian booking date from "Data di registrazione" to
    // "Data di contabilizzazione" around 2024. Unrecognised, the column was
    // invisible and the row date silently fell back to the trade date — the
    // same file in German or French kept the booking date.
    $csv = "Data dell'operazione;Data di contabilizzazione;Data di valuta;Moneta;"
        ."Importo della transazione;Descrizione1;N. di transazione;Note a piè di pagina\n"
        ."2026-09-27;2026-09-29;2026-09-27;CHF;-5.35;Pagamento;123456TI;\n";

    $file = ubsParser()->parse($csv);

    expect($file->rows[0]->date->format('Y-m-d'))->toBe('2026-09-29')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-09-27');
});
