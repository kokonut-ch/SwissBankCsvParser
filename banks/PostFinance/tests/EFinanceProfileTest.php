<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\PostFinance\EFinanceProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function postFinanceEFinanceParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new EFinanceProfile]));
}

function postFinanceFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

it('reads a French e-finance export', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-fr.csv'));

    expect($file->bank->key)->toBe('postfinance')
        ->and($file->profile)->toBe('postfinance.efinance')
        ->and($file->account->iban)->toBe('CH9300762011623852957')
        ->and($file->account->currency)->toBe('CHF')
        ->and($file->period->from?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($file->period->to?->format('Y-m-d'))->toBe('2026-03-31')
        ->and($file->warnings)->toBe([]);
});

it('keeps the rows in file order rather than sorting them', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-fr.csv'));

    expect(array_map(fn (Row $row) => $row->date->format('Y-m-d'), $file->rows))
        ->toBe(['2026-03-10', '2026-01-10', '2026-02-10']);
});

it('signs amounts from the column, not from the printed sign', function () {
    $rows = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-fr.csv'))->rows;

    // The debit column prints "-150.5"; the column already says it is a debit,
    // so the printed sign must not flip it back to a credit.
    expect($rows[0]->amount)->toBe('-150.5')
        ->and($rows[0]->isDebit())->toBeTrue()
        // Swiss thousands apostrophe.
        ->and($rows[1]->amount)->toBe('1200.00')
        ->and($rows[1]->isCredit())->toBeTrue()
        ->and($rows[2]->amount)->toBe('-25');
});

it('ignores the preamble, the blank separators and the legal footer', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-fr.csv'));

    expect($file)->toHaveCount(3)
        ->and(array_map(fn (Row $row) => $row->label, $file->rows))
        ->toBe(['Paiement fournisseur', 'Virement client', 'Frais bancaires']);
});

it('keeps recognised but non-core columns as extras', function () {
    $rows = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-fr.csv'))->rows;

    expect($rows[0]->extras)->toBe([
        'Type de transaction' => 'Enregistrement comptable',
        'Catégorie' => 'Achats',
    ]);
});

it('reads the German export with German headings', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-de.csv'));

    expect($file->account->iban)->toBe('CH5604835012345678009')
        ->and($file->account->currency)->toBe('CHF')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->label)->toBe('Miete Dezember')
        ->and($file->rows[0]->amount)->toBe('-1450')
        ->and($file->rows[1]->amount)->toBe('5200.75');
});

it('reads the Italian export with Italian headings', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-it.csv'));

    // "Moneta", not "Valuta": in Italian that word means currency, but German
    // uses it for the value date, so the profile has to name this one itself.
    expect($file->account->iban)->toBe('CH9300762011623852957')
        ->and($file->account->currency)->toBe('CHF')
        ->and($file->period->from?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->label)->toBe('Pagamento fornitore')
        ->and($file->rows[0]->amount)->toBe('-150.5')
        ->and($file->rows[1]->amount)->toBe('1200.00')
        ->and($file->rows[0]->extras)->toBe([
            'Tipo di movimento' => 'Registrazione contabile',
            'Categoria' => 'Acquisti',
        ]);
});

it('reads the English export with English headings', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-en.csv'));

    expect($file->account->iban)->toBe('CH9300762011623852957')
        ->and($file->account->currency)->toBe('CHF')
        ->and($file->period->to?->format('Y-m-d'))->toBe('2026-03-31')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->label)->toBe('Supplier payment')
        ->and($file->rows[0]->amount)->toBe('-150.5')
        ->and($file->rows[1]->amount)->toBe('1200.00')
        ->and($file->rows[0]->extras)->toBe([
            'Booking type' => 'Booking entry',
            'Category' => 'Purchases',
        ]);
});

it('identifies the format in each of the four languages', function (string $fixture, string $heading) {
    $report = postFinanceEFinanceParser()->detect(postFinanceFixture($fixture));

    expect($report->best()?->profile)->toBe('postfinance.efinance')
        ->and($report->best()?->reasons)->toContain(sprintf('distinctive heading "%s"', $heading));
})->with([
    ['efinance-de.csv', 'Avisierungstext'],
    ['efinance-fr.csv', 'Texte de notification'],
    ['efinance-it.csv', 'Testo di avviso'],
    ['efinance-en.csv', 'Notification text'],
    ['efinance-it-2016.csv', "Testo d'avviso"],
]);

it('decodes a Latin-1 export and says so', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-de-latin1.csv'));

    expect($file->hasWarning(Warning::ENCODING_CONVERTED))->toBeTrue()
        ->and($file->rows[0]->label)->toBe('Miete Dezember')
        ->and($file->account->currency)->toBe('CHF');
});

it('takes the currency from the amount heading', function () {
    // The header block is dropped, leaving "Crédit en CHF" as the only mention
    // of a currency anywhere in the file.
    $csv = <<<'CSV'
    Date;Texte de notification;Crédit en EUR;Débit en EUR
    10.01.2026;Virement;100.00;
    CSV;

    $file = postFinanceEFinanceParser()->parse($csv);

    expect($file->account->currency)->toBe('EUR')
        ->and($file->rows[0]->currency)->toBe('EUR');
});

it('does not claim a credit card statement', function () {
    expect(postFinanceEFinanceParser()->supports(postFinanceFixture('creditcard-fr.csv')))->toBeFalse();
});

it('reads the older Italian wording, with signed amounts and grouped balances', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-it-2016.csv'));

    // "+104.70" in the credit column, "-38.70" in the debit column, and a
    // "Saldo " heading with a trailing space over an empty last column.
    expect($file->account->iban)->toBe('CH9300762011623852957')
        ->and(array_map(fn (Row $row) => $row->amount, $file->rows))->toBe(['104.70', '1817.33', '-38.70'])
        ->and($file->rows[1]->balance)->toBe('5302.34')
        ->and($file->rows[2]->valueDate?->format('Y-m-d'))->toBe('2016-07-05');
});

it('reads rows dated in ISO notation', function () {
    $rows = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-it-iso.csv'))->rows;

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->date->format('Y-m-d'))->toBe('2016-11-14')
        ->and($rows[1]->amount)->toBe('-746.44')
        ->and($rows[2]->date->format('Y-m-d'))->toBe('2016-11-11')
        ->and($rows[2]->valueDate?->format('Y-m-d'))->toBe('2016-11-14');
});

it('reads the 2009 export, tab-separated and dated without separators', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-it-2009.csv'));

    expect($file->profile)->toBe('postfinance.efinance')
        ->and($file->rows)->toHaveCount(3)
        ->and($file->rows[0]->date->format('Y-m-d'))->toBe('2009-04-01')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2009-03-31')
        ->and($file->rows[0]->label)->toBe('ACQUISTO/SERVIZIO DEL 31.03.2009 CARTA N. 00000001 NEGOZIO ESEMPIO')
        ->and(array_map(fn (Row $row) => $row->amount, $file->rows))->toBe(['-71.8', '-85.9', '4570.00']);
});

it('reads the 2003 export and keeps its closing balance out of the last label', function () {
    $file = postFinanceEFinanceParser()->parse(postFinanceFixture('efinance-it-2003.csv'));

    expect($file->rows)->toHaveCount(4)
        // The opening balance line carries no movement.
        ->and($file->rows[0]->label)->toBe('Saldo')
        ->and($file->rows[0]->amount)->toBeNull()
        ->and($file->rows[0]->balance)->toBe('50078.40')
        ->and(array_map(fn (Row $row) => $row->amount, array_slice($file->rows, 1)))->toBe(['-28.60', '26.80', '100.00'])
        // "Saldo al 03.09.2003;CHF 50176.60" follows the table. It is not the
        // rest of the last booking.
        ->and($file->rows[3]->label)->toBe('VERSAMENTO 25-1026-4,ESEMPIO SAGL');
});

it('does not take an ordinary Italian description heading for a signature', function () {
    $report = postFinanceEFinanceParser()->detect(postFinanceFixture('efinance-it-2003.csv'));

    expect($report->best()?->profile)->toBe('postfinance.efinance')
        ->and($report->isConfident())->toBeFalse();
});
