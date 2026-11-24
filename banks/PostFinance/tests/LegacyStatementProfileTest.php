<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\PostFinance\LegacyStatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function postFinanceLegacyParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new LegacyStatementProfile]));
}

function postFinanceLegacyFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/legacy.csv');
}

it('reads a headerless statement from column positions', function () {
    $file = postFinanceLegacyParser()->parse(postFinanceLegacyFixture());

    expect($file->profile)->toBe('postfinance.legacy')
        ->and($file->rows[1]->label)->toBe('Paiement loyer')
        ->and($file->rows[1]->amount)->toBe('-1200.00')
        ->and($file->rows[2]->amount)->toBe('2500.00');
});

it('pivots two-digit years on 1970', function () {
    $rows = postFinanceLegacyParser()->parse(postFinanceLegacyFixture())->rows;

    expect($rows[1]->date->format('Y-m-d'))->toBe('2026-03-15')
        ->and($rows[1]->valueDate?->format('Y-m-d'))->toBe('2026-03-15');
});

it('keeps a balance-only line instead of silently dropping it', function () {
    // "Solde initial" carries a balance but no movement. Whether that belongs in
    // a ledger is the caller's decision, so the row is reported with a null
    // amount rather than discarded here.
    $rows = postFinanceLegacyParser()->parse(postFinanceLegacyFixture())->rows;

    expect($rows)->toHaveCount(3)
        ->and($rows[0]->label)->toBe('Solde initial')
        ->and($rows[0]->amount)->toBeNull()
        ->and($rows[0]->balance)->toBe('5000.00');
});

it('reports that no currency could be found', function () {
    $file = postFinanceLegacyParser()->parse(postFinanceLegacyFixture());

    // The file names no currency anywhere. Defaulting to CHF would be a guess,
    // and guessing is the caller's prerogative.
    expect($file->account->currency)->toBeNull()
        ->and($file->hasWarning(Warning::CURRENCY_NOT_DETECTED))->toBeTrue();
});

it('stays unconfident, because nothing in the file names the bank', function () {
    $report = postFinanceLegacyParser()->detect(postFinanceLegacyFixture());

    expect($report->best()?->profile)->toBe('postfinance.legacy')
        ->and($report->best()?->score)->toBeLessThan(0.5)
        ->and($report->isConfident())->toBeFalse();
});

it('does not claim the five-column layout, whose columns sit elsewhere', function () {
    // PostFinance also ships a headerless five-column layout with a movement-type
    // column inserted at position 1, which shifts credit and debit one to the
    // right. Accepting that width here would read the credit column as a debit
    // and negate it — a sign inversion with no symptom.
    $csv = "15.03.2026;Enregistrement;Paiement loyer;;1200.00\n"
        ."20.03.2026;Enregistrement;Salaire;2500.00;\n";

    expect(postFinanceLegacyParser()->supports($csv))->toBeFalse();
});

it('refuses a headed file whose columns sit elsewhere, rather than read the wrong ones', function () {
    // PostFinance's 2010 export. Its English headings are not in the shared
    // vocabulary, so it looks headerless, and by position its "Details" flag
    // would be the credit: every row would come back as 2.00.
    $csv = (string) file_get_contents(__DIR__.'/../fixtures/efinance-2010-en.csv');

    expect(postFinanceLegacyParser()->supports($csv))->toBeFalse();
});

it('never reports the Details flag as an amount once every profile is in play', function () {
    $file = (new SwissBankCsvParser)->parse((string) file_get_contents(__DIR__.'/../fixtures/efinance-2010-en.csv'));

    expect($file->profile)->not->toBe('postfinance.legacy')
        ->and(array_map(fn (Row $row) => $row->amount, $file->rows))
        ->toBe(['0.00', '-45.00', '-131.55', '1100.00', '-44.95']);
});

it('refuses a file whose cells contradict the layout', function (string $row) {
    // One bad line is enough: a layout that is wrong for one row may be wrong
    // for the others in ways that still parse.
    $csv = "15.03.26;Paiement loyer;;1200.00;15.03.26;3800.00\n".$row."\n";

    expect(postFinanceLegacyParser()->supports($csv))->toBeFalse();
})->with([
    'a date in the debit column' => '16.03.26;Achat;;16.03.26;-45.00;3755.00',
    'text in the credit column' => '16.03.26;Achat;CHF;45.00;16.03.26;3755.00',
    'money in both directions' => '16.03.26;Achat;10.00;45.00;16.03.26;3755.00',
    'a figure in the value-date column' => '16.03.26;Achat;;45.00;-45.00;3755.00',
    'text in the balance column' => '16.03.26;Achat;;45.00;16.03.26;Solde',
]);
