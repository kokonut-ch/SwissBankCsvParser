<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\BEKB\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function bekbParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function bekbFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a BEKB statement', function () {
    $file = bekbParser()->parse(bekbFixture());

    expect($file->bank->key)->toBe('bekb')
        ->and($file->profile)->toBe('bekb.statement')
        ->and($file)->toHaveCount(2);
});

it('takes the signed amount and ignores the direction sentence', function () {
    $rows = bekbParser()->parse(bekbFixture())->rows;

    // "Gutschrift per 27.06.2026" spells the direction out in words. The sign
    // already says it.
    // Both balances, so the chain stays checked: the file is newest first,
    // and the older balance plus the newer amount equals the newer balance.
    expect($rows[0]->amount)->toBe('629.74')
        ->and($rows[0]->balance)->toBe('10974.14')
        ->and($rows[1]->amount)->toBe('-53.30')
        ->and($rows[1]->balance)->toBe('10344.40');
});

it('joins the booking text, the counterparty and the message', function () {
    $rows = bekbParser()->parse(bekbFixture())->rows;

    expect($rows[0]->label)->toBe('Zahlungseingang Muster SA TR1234567890 -123')
        ->and($rows[1]->label)->toBe('Verkaufspunkt/Debitkarte SAG Schweiz AG');
});

it('reads the booking date and the value date separately', function () {
    $rows = bekbParser()->parse(bekbFixture())->rows;

    expect($rows[1]->date->format('Y-m-d'))->toBe('2026-06-24')
        ->and($rows[1]->valueDate?->format('Y-m-d'))->toBe('2026-06-23');
});

it('refuses an ordinary cantonal statement', function () {
    $csv = "Datum;Valuta;Buchungstext;Betrag;Saldo\n27.06.2026;27.06.2026;Zahlung;629.74;11176.55\n";

    expect(bekbParser()->supports($csv))->toBeFalse();
});
