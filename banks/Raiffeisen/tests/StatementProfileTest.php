<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Raiffeisen\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function raiffeisenParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function raiffeisenFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

it('reads a Raiffeisen statement', function () {
    $file = raiffeisenParser()->parse(raiffeisenFixture('statement.csv'));

    expect($file->bank->key)->toBe('raiffeisen')
        ->and($file->profile)->toBe('raiffeisen.statement')
        ->and($file)->toHaveCount(3);
});

it('drops the clock time from an ISO timestamp', function () {
    $rows = raiffeisenParser()->parse(raiffeisenFixture('statement.csv'))->rows;

    // "2026-07-02 00:00:00.0" — a statement line is about a day.
    expect($rows[0]->date->format('Y-m-d H:i:s'))->toBe('2026-07-02 00:00:00')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-07-02');
});

it('takes the signed amount at face value', function () {
    $rows = raiffeisenParser()->parse(raiffeisenFixture('statement.csv'))->rows;

    expect($rows[0]->amount)->toBe('-1145.00')
        ->and($rows[0]->isDebit())->toBeTrue()
        ->and($rows[1]->amount)->toBe('10.00')
        ->and($rows[1]->isCredit())->toBeTrue();
});

it('joins the text and details columns', function () {
    $rows = raiffeisenParser()->parse(raiffeisenFixture('statement.csv'))->rows;

    expect($rows[2]->label)->toBe('Prelevamento Bancomat Bancomat Lugano');
});

it('folds a continuation line into the row above', function () {
    $file = raiffeisenParser()->parse(raiffeisenFixture('statement.csv'));

    // The line carrying only "Muster AG, 8000 Zürich" belongs to the booking
    // above it — it is where the counterparty is named.
    expect($file)->toHaveCount(3)
        ->and($file->rows[0]->label)->toBe('Zahlung Lieferant Rechnung 4471 Muster AG, 8000 Zürich');
});

it('takes the account IBAN from the column, there being no header block', function () {
    $account = raiffeisenParser()->parse(raiffeisenFixture('statement.csv'))->account;

    expect($account->iban)->toBe('CH9300762011623852957')
        ->and($account->number)->toBeNull();
});

it('reads the older export with two-digit years and no IBAN column', function () {
    $file = raiffeisenParser()->parse(raiffeisenFixture('statement-legacy.csv'));

    expect($file)->toHaveCount(2)
        ->and($file->rows[0]->date->format('Y-m-d'))->toBe('2013-01-03')
        ->and($file->rows[0]->label)->toBe('Gutschrift Daniel Muster Hauptstrasse 76a 9105 Musterdorf CHF 195.00')
        ->and($file->rows[1]->amount)->toBe('-465')
        ->and($file->account->iban)->toBeNull();
});

it('refuses a file that does not carry its signature heading', function () {
    // Same shape, ordinary headings. Raiffeisen must not claim it.
    $csv = "Datum;Buchungstext;Betrag;Saldo\n03.01.2026;Zahlung;-100.00;900.00\n";

    expect(raiffeisenParser()->supports($csv))->toBeFalse();
});
