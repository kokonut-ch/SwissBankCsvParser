<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Cler\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\Candidate;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

/** The parser a caller builds once the user has confirmed the bank: Bank Cler's profile only. */
function clerParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function clerFixture(string $name = 'statement.csv'): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

/** The Bank Cler candidate of a free detection, if any. */
function clerCandidate(string $csv): ?Candidate
{
    foreach ((new SwissBankCsvParser)->detect($csv) as $candidate) {
        if ($candidate->profile === 'cler.statement') {
            return $candidate;
        }
    }

    return null;
}

/**
 * Every fixture, with how many rows carry an amount and the currency the
 * headings announce.
 *
 * @return array<string, array{0: int, 1: string}>
 */
function clerLayouts(): array
{
    return [
        'statement.csv' => [2, 'CHF'],
        'statement-it-usd.csv' => [2, 'USD'],
        'statement-de.csv' => [3, 'CHF'],
        'statement-de-usd.csv' => [2, 'USD'],
    ];
}

it('reads a Cler statement', function () {
    $file = clerParser()->parse(clerFixture());

    expect($file->bank->key)->toBe('cler')
        ->and($file->profile)->toBe('cler.statement')
        ->and($file)->toHaveCount(2);
});

it('takes the currency from the bracketed amount heading', function () {
    $file = clerParser()->parse(clerFixture());

    // "Importo di addebito (CHF)": there is no header block to read it from.
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

it('reads the French variant, which has no value date column', function () {
    // The real export (test/testcases/csv_clerbank_example_format1_CHF_20251002.csv
    // upstream) drops "Date valeur" entirely in French: booking date, order type,
    // label, debit, credit, balance, and nothing else.
    $csv = "Date de comptabilisation;Type d'ordre;Libellé;Montant débité (CHF);Montant crédité (CHF);Solde (CHF)\n"
        ."05.11.2026;Paiement en Suisse;Boutique Muster;50.00;;5417.92\n"
        ."03.11.2026;Virement de compte;Virement client;;200.00;5467.92\n";

    $file = clerParser()->parse($csv);

    expect($file->profile)->toBe('cler.statement')
        ->and($file->account->currency)->toBe('CHF')
        ->and($file->rows[0]->amount)->toBe('-50.00')
        ->and($file->rows[0]->isDebit())->toBeTrue()
        ->and($file->rows[1]->amount)->toBe('200.00')
        ->and($file->rows[1]->isCredit())->toBeTrue();
});

it('reads an Italian USD account', function () {
    $file = clerParser()->parse(clerFixture('statement-it-usd.csv'));

    expect($file->account->currency)->toBe('USD')
        ->and($file->rows[0]->currency)->toBe('USD')
        ->and($file->rows[0]->amount)->toBe('-980.50')
        ->and($file->rows[0]->balance)->toBe('3019.50')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-11-14')
        ->and($file->rows[1]->amount)->toBe('4000.00')
        ->and($file->rows[1]->currency)->toBe('USD');
});

it('reads the German layout once the bank is confirmed', function () {
    // The 2026 German export (test/testcases/csv_clerbank_example_format1_CHF_20260724.csv
    // upstream) has no value date column, like the French one.
    $file = clerParser()->parse(clerFixture('statement-de.csv'));

    expect($file->bank->key)->toBe('cler')
        ->and($file->profile)->toBe('cler.statement')
        ->and($file)->toHaveCount(3)
        ->and($file->account->currency)->toBe('CHF')
        ->and($file->rows[0]->date->format('Y-m-d'))->toBe('2026-11-05')
        ->and($file->rows[0]->valueDate)->toBeNull()
        ->and($file->rows[0]->amount)->toBe('-184.30')
        ->and($file->rows[0]->balance)->toBe('5233.62')
        ->and($file->rows[0]->currency)->toBe('CHF')
        ->and($file->rows[0]->extras)->toBe(['Auftragsart' => 'QR-Rechnung'])
        ->and($file->rows[1]->amount)->toBe('4200.00')
        ->and($file->rows[2]->amount)->toBe('-2.00')
        ->and($file->rows[2]->label)->toBe('Kontoführungsgebühr')
        ->and($file->rows[2]->balance)->toBe('1217.92');
});

it('reads a German USD account, value date included', function () {
    $file = clerParser()->parse(clerFixture('statement-de-usd.csv'));

    expect($file->account->currency)->toBe('USD')
        ->and($file->rows[0]->currency)->toBe('USD')
        ->and($file->rows[0]->amount)->toBe('-1250.00')
        ->and($file->rows[0]->balance)->toBe('8750.00')
        ->and($file->rows[1]->amount)->toBe('10000.00')
        ->and($file->rows[1]->valueDate?->format('Y-m-d'))->toBe('2026-11-11');
});

it('keeps a text spread over several quoted lines as one label', function (string $fixture, string $label) {
    // Every published sample quotes multi-line texts: name, street, town, then
    // the payment reference, each on its own physical line.
    $rows = clerParser()->parse(clerFixture($fixture))->rows;

    expect($rows[0]->label)->toBe($label);
})->with([
    'German' => ['statement-de.csv', 'Muster Energie AG Musterstrasse 1 4051 Basel Pers. Ref.: Rechnung 2026-118'],
    'German USD' => ['statement-de-usd.csv', 'Example Supplies Inc 100 Sample Street Springfield Invoice 2026-0042'],
    'Italian USD' => ['statement-it-usd.csv', 'Example Travel LLC Via Esempio 12 6900 Lugano Fattura 2026/0042'],
]);

it('takes the currency from the heading in every language', function (string $headings, string $currency) {
    $csv = str_replace('XXX', $currency, $headings)."\n"
        ."05.11.2026;Zahlung;Muster AG;50.00;;950.00\n"
        ."03.11.2026;Gutschrift;Beispiel GmbH;;1000.00;1000.00\n";

    $file = clerParser()->parse($csv);

    expect($file->account->currency)->toBe($currency)
        ->and(array_map(static fn (Row $row): ?string => $row->currency, $file->rows))->toBe([$currency, $currency])
        ->and($file->rows[0]->amount)->toBe('-50.00')
        ->and($file->rows[1]->amount)->toBe('1000.00');
})->with([
    'German' => 'Buchungsdatum;Auftragsart;Text;Belastungsbetrag (XXX);Gutschriftsbetrag (XXX);Saldo (XXX)',
    'Italian' => 'Data di registrazione;Tipo di ordine;Testo;Importo di addebito (XXX);Importo di accredito (XXX);Saldo (XXX)',
    'French' => "Date de comptabilisation;Type d'ordre;Libellé;Montant débité (XXX);Montant crédité (XXX);Solde (XXX)",
])->with(['CHF', 'EUR', 'USD']);

it('reads every fixture once the bank is confirmed', function (string $fixture) {
    [$rows, $currency] = clerLayouts()[$fixture];

    $file = clerParser()->parse(clerFixture($fixture));

    expect($file->profile)->toBe('cler.statement')
        ->and($file->account->currency)->toBe($currency)
        ->and(array_filter($file->rows, static fn (Row $row): bool => $row->amount !== null))->toHaveCount($rows);
})->with(array_keys(clerLayouts()));

it('has a line above for every fixture the bank ships', function () {
    $fixtures = array_map(basename(...), glob(__DIR__.'/../fixtures/*.csv') ?: []);
    sort($fixtures);

    $listed = array_keys(clerLayouts());
    sort($listed);

    expect($fixtures)->toBe($listed);
});

it('identifies the Italian layout confidently', function (string $fixture) {
    $report = (new SwissBankCsvParser)->detect(clerFixture($fixture));

    expect($report->best()?->profile)->toBe('cler.statement')
        ->and($report->best()?->bank->name)->toBe('Bank Cler')
        ->and($report->isConfident())->toBeTrue();
})->with(['statement.csv', 'statement-it-usd.csv']);

it('never claims the German layout in free detection', function (string $fixture) {
    // Basler Kantonalbank, Bank Cler's parent, exports exactly these headings,
    // with or without "Valutadatum". Nothing in the file says Bank Cler, so the
    // generic reader keeps it, and the bank has to be named by whoever uploaded
    // the file.
    $csv = clerFixture($fixture);
    $report = (new SwissBankCsvParser)->detect($csv);

    expect($report->best()?->profile)->toBe('generic.split-columns')
        ->and($report->isConfident())->toBeFalse()
        ->and(clerCandidate($csv)?->score)->toBeLessThan(0.30)
        ->and((new SwissBankCsvParser)->parse($csv)->bank->key)->toBe('unknown');
})->with(['statement-de.csv', 'statement-de-usd.csv']);

it('refuses a statement without an order type column', function () {
    $csv = "Data di registrazione;Testo;Importo di addebito (CHF);Importo di accredito (CHF)\n"
        ."31.10.2026;Test;11.32;\n";

    expect(clerParser()->supports($csv))->toBeFalse();
});

it('leaves other banks\' German order type exports alone', function () {
    // Thurgauer Kantonalbank prints "Auftragsart" too, beside its own amount
    // headings: "Belastung (CHF)", "Gutschrift (CHF)".
    $csv = "Buchungsdatum;Valutadatum;Auftragsart;Buchungstext;Betrag Einzelzahlung (CHF);Belastung (CHF);Gutschrift (CHF);Saldo in (CHF)\n"
        ."28.02.2026;28.02.2026;Zahlung;Muster AG;;150.00;;3020.50\n";

    expect(clerParser()->supports($csv))->toBeFalse()
        ->and(clerCandidate($csv))->toBeNull();
});
