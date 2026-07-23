<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Twint\SettlementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function twintParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new SettlementProfile]));
}

function twintFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/settlement.csv');
}

it('reads a TWINT merchant report', function () {
    $file = twintParser()->parse(twintFixture());

    expect($file->bank->key)->toBe('twint')
        ->and($file->profile)->toBe('twint.settlement')
        ->and($file)->toHaveCount(2);
});

it('reads dot-separated ISO dates and skips the report preamble', function () {
    $rows = twintParser()->parse(twintFixture())->rows;

    // "2026.11.01" — and the "Von:" / "Bis:" preamble is not a booking.
    expect($rows[0]->date->format('Y-m-d'))->toBe('2026-11-01')
        ->and($rows[1]->date->format('Y-m-d'))->toBe('2026-11-03');
});

it('takes the currency from the bracketed amount heading', function () {
    $file = twintParser()->parse(twintFixture());

    expect($file->account->currency)->toBe('CHF')
        ->and($file->rows[0]->amount)->toBe('49.35')
        ->and($file->rows[1]->amount)->toBe('-12.00');
});

it('does not net the fee off the transaction amount', function () {
    $rows = twintParser()->parse(twintFixture())->rows;

    // The 0.65 transaction cost is what the merchant owes TWINT. Subtracting it
    // here would be an accounting decision, and this is a parser.
    expect($rows[0]->amount)->toBe('49.35')
        ->and($rows[0]->raw)->toContain('0.65');
});

it('reads the English report, where the status column is named State', function () {
    $csv = (string) file_get_contents(__DIR__.'/../fixtures/settlement-en.csv');

    $file = twintParser()->parse($csv);

    // TWINT's English export prints "State" where the German one prints
    // "Status". Both must reach extras — a "Failed" line carries an amount, and
    // without the state a failed payment reads as money actually cashed.
    expect($file->profile)->toBe('twint.settlement')
        ->and($file->account->currency)->toBe('CHF')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->extras['State'] ?? null)->toBe('Settled')
        ->and($file->rows[1]->extras['State'] ?? null)->toBe('Failed')
        ->and($file->rows[1]->amount)->toBe('20.00');
});

it('keeps the status of the German report in extras', function () {
    $rows = twintParser()->parse(twintFixture())->rows;

    expect($rows[0]->extras['Status'] ?? null)->toBe('Erfolgreich');
});

it('exposes the TWINT Order ID as the row reference', function () {
    $rows = twintParser()->parse(twintFixture())->rows;

    expect($rows[0]->reference)->toBe('2r0vrh8j-6o52')
        ->and($rows[1]->reference)->toBe('7c7a-2p68-p68j');
});

it('exposes the TWINT order ID as the row reference in the English report', function () {
    $csv = (string) file_get_contents(__DIR__.'/../fixtures/settlement-en.csv');

    $rows = twintParser()->parse($csv)->rows;

    expect($rows[0]->reference)->toBe('2r0vrh8j-6o52')
        ->and($rows[1]->reference)->toBe('7c7a-2p68-p68j');
});

it('refuses a file without a TWINT identifier', function () {
    $csv = "\"Datum\";\"Typ\";\"Betrag Transaktion (CHF)\"\n\"2026.11.01\";\"Zahlung\";\"49.35\"\n";

    expect(twintParser()->supports($csv))->toBeFalse();
});
