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
