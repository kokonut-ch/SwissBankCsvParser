<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Swisscard\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function swisscardParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function swisscardFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Swisscard statement', function () {
    $file = swisscardParser()->parse(swisscardFixture());

    expect($file->bank->key)->toBe('swisscard')
        ->and($file->profile)->toBe('swisscard.statement')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->currency)->toBe('CHF');
});

it('flips the issuer sign, so a purchase is money out', function () {
    $rows = swisscardParser()->parse(swisscardFixture())->rows;

    // Swisscard prints a purchase positive — it is what you owe — and a refund
    // negative. Read at face value, every charge would come out as income.
    expect($rows[0]->amount)->toBe('-189.00')
        ->and($rows[0]->isDebit())->toBeTrue()
        ->and($rows[1]->amount)->toBe('40.00')
        ->and($rows[1]->isCredit())->toBeTrue()
        ->and($rows[2]->amount)->toBe('-56.50');
});

it('ignores the debit/credit word column', function () {
    $rows = swisscardParser()->parse(swisscardFixture())->rows;

    // The word column agrees with the sign here. It is not consulted, so that
    // the two can never disagree.
    expect($rows[0]->raw)->toContain('Debit')
        ->and($rows[1]->raw)->toContain('Credit');
});

it('keeps the registered category as an extra', function () {
    $rows = swisscardParser()->parse(swisscardFixture())->rows;

    expect($rows[2]->extras)->toBe([
        'Card number' => 'XXXX 0001',
        'Status' => 'Booked',
        'Registered Category' => 'Restaurants, Bar',
    ])->and($rows[2]->label)->toBe('RESTAURANT DU PONT');
});

it('reports the card and the booking status, which decide what a row means', function () {
    // A statement can cover several cards, and it lists pending authorisations
    // beside settled ones. Neither belongs in the neutral model, and neither
    // may be left to positional indexing into $raw: this file's own column
    // names are inferred, so those positions are the least reliable thing here.
    $rows = swisscardParser()->parse(swisscardFixture())->rows;

    expect($rows[0]->extras['Card number'])->toBe('XXXX 0001')
        ->and($rows[0]->extras['Status'])->toBe('Booked');
});

it('refuses a statement without the registered category column', function () {
    $csv = "Transaction date;Description;Currency;Amount\n13.11.2026;Shop;CHF;189.00\n";

    expect(swisscardParser()->supports($csv))->toBeFalse();
});
