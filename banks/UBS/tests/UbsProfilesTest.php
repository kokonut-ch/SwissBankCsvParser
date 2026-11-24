<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\UBS\CreditCardProfile;
use Kokonut\SwissBankCsvParser\Banks\UBS\SignedAmountStatementProfile;
use Kokonut\SwissBankCsvParser\Banks\UBS\SplitAmountStatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Dto\Warning;
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

describe('portfolio export', function () {
    it('reads a reversed credit as money out', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-portfolio-de.csv'))->rows;

        // "-500.00" under "Gutschrift" reverses the credit below it: the
        // balance drops from 8920.00 to 8420.00 on that row. Read by the column
        // alone, it would be 500 received.
        expect($rows[0]->amount)->toBe('-500.00')
            ->and($rows[0]->balance)->toBe('8420.00')
            ->and($rows[1]->balance)->toBe('8920.00')
            ->and($rows[2]->amount)->toBe('-120.00');
    });

    it('rejoins a description that an unquoted semicolon split in two', function () {
        $file = ubsParser()->parse(ubsFixture('statement-portfolio-de.csv'));

        // "Rechnung 4471 ; Teilzahlung" is not quoted, so the row comes out one
        // cell too wide and its credit lands in the balance column.
        expect($file)->toHaveCount(4)
            ->and($file->rows[1]->amount)->toBe('500.00')
            ->and($file->rows[1]->balance)->toBe('8920.00')
            ->and($file->rows[1]->label)->toBe('Gutschrift Muster AG Rechnung 4471 ; Teilzahlung')
            ->and($file->rows[1]->raw)->toHaveCount(21);
    });

    it('leaves a row alone when no unquoted semicolon explains its extra cell', function () {
        // A space on one side of the delimiter only: not what a split " ; "
        // looks like, so the row is not guessed back into shape.
        $csv = 'Abschluss;Buchungsdatum;Valuta;Beschreibung 1;Beschreibung 2;Transaktions-Nr.;Belastung;Gutschrift;Saldo'."\n"
            .'03.08.2026;03.08.2026;03.08.2026;Zahlungseingang;Kunde ;Muster;ZD81215TI0000001;;8540.00;8540.00'."\n";

        $rows = ubsParser()->parse($csv)->rows;

        expect($rows[0]->raw)->toHaveCount(10)
            ->and($rows[0]->raw[4])->toBe('Kunde ');
    });
});

describe('current e-banking export', function () {
    it('reads "Devise" as the currency heading', function () {
        // The French export renamed "Monnaie" to "Devise" in 2026.
        $file = ubsParser()->parse(ubsFixture('statement-current-fr.csv'));

        expect($file->profile)->toBe('ubs.statement.split')
            ->and($file)->toHaveCount(4)
            ->and(array_map(fn (Row $row): ?string => $row->currency, $file->rows))->toBe(['CHF', 'CHF', 'CHF', 'CHF'])
            ->and($file->hasWarning(Warning::CURRENCY_NOT_DETECTED))->toBeFalse();
    });
});

describe('dates', function () {
    it('dates a row by its booking date and keeps the trade date in extras', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-current-fr.csv'))->rows;

        // A card payment made on Friday 24 July and booked on Monday 27 July.
        expect($rows[1]->date->format('Y-m-d'))->toBe('2026-07-27')
            ->and($rows[1]->valueDate?->format('Y-m-d'))->toBe('2026-07-24')
            ->and($rows[1]->extras)->toBe(['Date de transaction' => '2026-07-24']);
    });

    it('falls back to the trade date while the booking date is empty', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-current-fr.csv'))->rows;

        // A card payment UBS has not booked yet: a trade time, no booking date.
        expect($rows[0]->raw[2])->toBe('')
            ->and($rows[0]->date->format('Y-m-d'))->toBe('2026-07-28')
            ->and($rows[0]->extras)->toBe(['Date de transaction' => '2026-07-28']);
    });

    it('keeps the trade date of the portfolio export in extras', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-portfolio-de.csv'))->rows;

        expect($rows[2]->date->format('Y-m-d'))->toBe('2026-08-13')
            ->and($rows[2]->valueDate?->format('Y-m-d'))->toBe('2026-08-11')
            ->and($rows[2]->extras)->toBe(['Abschluss' => '11.08.2026']);
    });

    it('keeps the trade date of the signed export in extras', function () {
        $rows = ubsParser()->parse(ubsFixture('statement-signed.csv'))->rows;

        expect($rows[1]->extras)->toBe(["Data dell'operazione" => '2026-09-20']);
    });

    it('reads the French and Italian portfolio wordings of the trade date', function (string $csv, string $heading) {
        $rows = ubsParser()->parse($csv)->rows;

        expect($rows[0]->date->format('Y-m-d'))->toBe('2026-08-11')
            ->and($rows[0]->extras)->toBe([$heading => '11.08.2026']);
    })->with([
        'French' => [
            'Relation bancaire;Date de conclusion;Date de comptabilisation;Date de valeur;Description 1;'
            ."N° de transaction;Débit;Crédit;Solde\n"
            .";11.08.2026;;11.08.2026;Paiement carte;9930813BN0000001;120.00;;8420.00\n",
            'Date de conclusion',
        ],
        'Italian' => [
            'Relazione bancaria;Data di chiusura;Data di registrazione;Valuta;Descrizione 1;'
            ."N. di transazione;Addebito;Accredito;Saldo\n"
            .";11.08.2026;;11.08.2026;Pagamento carta;9930813BN0000001;120.00;;8420.00\n",
            'Data di chiusura',
        ],
    ]);
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

    it('never folds a purchase not yet booked into the booked one above', function () {
        $file = ubsParser()->parse(ubsFixture('creditcard-pending.csv'));

        // The carried balance and the two purchases without "Ecriture" have no
        // booking date: none of them is a row, and none lends its text to one.
        expect($file->profile)->toBe('ubs.creditcard')
            ->and($file)->toHaveCount(2)
            ->and($file->rows[0]->label)->toBe('www.example.ch LUGANO CHE')
            ->and($file->rows[1]->label)->toBe('Example Store London GBR')
            ->and($file->rows[1]->amount)->toBe('-16.68')
            ->and($file->rows[1]->currency)->toBe('CHF');
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
    ['statement-portfolio-de.csv', 'ubs.statement.split'],
    ['statement-current-fr.csv', 'ubs.statement.split'],
    ['statement-signed.csv', 'ubs.statement.signed'],
    ['creditcard.csv', 'ubs.creditcard'],
    ['creditcard-pending.csv', 'ubs.creditcard'],
]);

it('refuses an ordinary statement that names no UBS column', function () {
    $csv = "Buchungsdatum;Buchungstext;Belastung;Gutschrift;Saldo\n03.01.2026;Zahlung;100.00;;900.00\n";

    expect(ubsParser()->supports($csv))->toBeFalse();
});

it('prefers the booking date over the trade date when the two differ', function () {
    // UBS prints the trade date in the earlier column. Reading left to right
    // binds the row to the wrong date on every settled transaction, and in
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
    // invisible and the row date silently fell back to the trade date, while
    // the same file in German or French kept the booking date.
    $csv = "Data dell'operazione;Data di contabilizzazione;Data di valuta;Moneta;"
        ."Importo della transazione;Descrizione1;N. di transazione;Note a piè di pagina\n"
        ."2026-09-27;2026-09-29;2026-09-27;CHF;-5.35;Pagamento;123456TI;\n";

    $file = ubsParser()->parse($csv);

    expect($file->rows[0]->date->format('Y-m-d'))->toBe('2026-09-29')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-09-27');
});
