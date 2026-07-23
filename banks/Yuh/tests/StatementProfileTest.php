<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Yuh\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function yuhParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function yuhFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Yuh statement', function () {
    $file = yuhParser()->parse(yuhFixture());

    expect($file->bank->key)->toBe('yuh')
        ->and($file->profile)->toBe('yuh.statement')
        ->and($file)->toHaveCount(3);
});

it('joins the activity kind and name into the label', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    expect($rows[0]->label)->toBe('Card payment Muster Boutique')
        ->and($rows[1]->label)->toBe('Incoming payment Salaire novembre');
});

it('picks whichever of the two currency columns is filled', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    // "DEBIT CURRENCY" on the debit row, "CREDIT CURRENCY" on the credit row.
    expect($rows[0]->currency)->toBe('CHF')
        ->and($rows[1]->currency)->toBe('CHF');
});

it('signs from the column', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    expect($rows[0]->amount)->toBe('-11.32')
        ->and($rows[1]->amount)->toBe('5200.00')
        ->and($rows[2]->amount)->toBe('-250.00');
});

it('keeps the columns it does not model in the raw row', function () {
    $rows = yuhParser()->parse(yuhFixture())->rows;

    // Quantity, asset and price per unit have no place in a bank statement
    // model, and are not thrown away either.
    expect($rows[2]->raw)->toContain('ACME', '125.00', 'BUY')
        // …and stay out of the modelled fields, which is the half of that
        // sentence nothing used to check.
        ->and($rows[2]->extras)->not->toHaveKeys(['ASSET', 'QUANTITY', 'PRICE PER UNIT']);
});

it('reports the card number as an extra, not only in the raw row', function () {
    // Added in v0.1.1. Before that the shared vocabulary had no term for a card
    // number, so this column reached Row::$raw alone and the README said so.
    // The claim and the behaviour must not drift apart again.
    $rows = yuhParser()->parse(yuhFixture())->rows;

    expect($rows[0]->extras)->toBe(['CARD NUMBER' => 'XXXX 0001'])
        ->and($rows[1]->extras)->toBe([]);
});

it('refuses an ordinary statement', function () {
    $csv = "DATE;DESCRIPTION;DEBIT;CREDIT\n31.10.2026;Zahlung;11.32;\n";

    expect(yuhParser()->supports($csv))->toBeFalse();
});

it('reads the slashed dates the real export prints', function () {
    // Upstream's import app validates Yuh dates against dd/mm/yyyy with
    // slashes, and its sample file prints them that way; dotted dates exist
    // only in an outdated comment. A file in the real convention was detected
    // as Yuh and then silently emptied to zero rows.
    $csv = "DATE;ACTIVITY TYPE;ACTIVITY NAME;DEBIT;DEBIT CURRENCY;CREDIT;CREDIT CURRENCY;CARD NUMBER;LOCALITY;RECIPIENT;SENDER;FEES/COMMISSION;BUY/SELL;QUANTITY;ASSET;PRICE PER UNIT\n"
        ."04/09/2026;Card payment;Muster Boutique;11.32;CHF;;;XXXX 0001;Lausanne;;;;;;;\n"
        ."02/11/2026;Incoming payment;Salaire novembre;;;5200.00;CHF;;;;Muster AG;;;;;\n";

    $file = yuhParser()->parse($csv);

    expect($file->profile)->toBe('yuh.statement')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->date->format('Y-m-d'))->toBe('2026-09-04')
        ->and($file->rows[0]->amount)->toBe('-11.32')
        ->and($file->rows[1]->amount)->toBe('5200.00');
});
