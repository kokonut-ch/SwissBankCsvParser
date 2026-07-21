<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\PostFinance\LegacyStatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
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
