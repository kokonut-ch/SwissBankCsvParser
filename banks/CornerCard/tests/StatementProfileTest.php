<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Corner\StatementProfile as CornerBankProfile;
use Kokonut\SwissBankCsvParser\Banks\CornerCard\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function cornerCardParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function cornerCardFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Cornèrcard statement', function () {
    $file = cornerCardParser()->parse(cornerCardFixture());

    expect($file->bank->key)->toBe('cornercard')
        ->and($file->profile)->toBe('cornercard.statement')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->currency)->toBe('CHF');
});

it('flips the issuer sign, so a purchase is money out', function () {
    $rows = cornerCardParser()->parse(cornerCardFixture())->rows;

    expect($rows[0]->amount)->toBe('-49.90')
        ->and($rows[0]->isDebit())->toBeTrue()
        ->and($rows[1]->amount)->toBe('100.00')
        ->and($rows[1]->isCredit())->toBeTrue();
});

it('reads day-first slashed dates', function () {
    $rows = cornerCardParser()->parse(cornerCardFixture())->rows;

    expect($rows[0]->date->format('Y-m-d'))->toBe('2026-11-02')
        ->and($rows[2]->date->format('Y-m-d'))->toBe('2026-11-01');
});

it('needs all three of Card, Currency and Status, not just one', function () {
    // No single heading here names Cornèrcard, so a file carrying only some of
    // them is not claimed.
    $missingStatus = "Date;Description;Card;Currency;Amount\n02/11/2026;Shop;**2618;CHF;49.90\n";

    expect(cornerCardParser()->supports($missingStatus))->toBeFalse()
        ->and(cornerCardParser()->supports(cornerCardFixture()))->toBeTrue();
});

it('is not confused with the bank account statement of the same group', function () {
    $both = new SwissBankCsvParser(new ProfileRegistry([
        new StatementProfile,
        new CornerBankProfile,
    ]));

    $bankStatement = (string) file_get_contents(
        dirname(__DIR__, 2).'/Corner/fixtures/statement.csv',
    );

    expect($both->detect(cornerCardFixture())->best()?->profile)->toBe('cornercard.statement')
        ->and($both->detect($bankStatement)->best()?->profile)->toBe('corner.statement');
});
