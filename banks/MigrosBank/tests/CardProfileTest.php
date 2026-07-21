<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\MigrosBank\CardProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function migrosCardParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new CardProfile]));
}

function migrosCardFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/card.csv');
}

it('reads a Migros Bank card export', function () {
    $file = migrosCardParser()->parse(migrosCardFixture());

    expect($file->bank->key)->toBe('migrosbank')
        ->and($file->profile)->toBe('migrosbank.card')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->currency)->toBe('CHF');
});

it('builds the label from the merchant columns', function () {
    $rows = migrosCardParser()->parse(migrosCardFixture())->rows;

    expect($rows[0]->label)->toBe('Muster Shop Lausanne CH Card payment')
        // Empty pieces are skipped rather than leaving a double space.
        ->and($rows[1]->label)->toBe('Example SA Genève CH');
});

it('takes the signed amount at face value', function () {
    $rows = migrosCardParser()->parse(migrosCardFixture())->rows;

    expect($rows[0]->amount)->toBe('-105.45')
        ->and($rows[2]->amount)->toBe('40.00')
        ->and($rows[2]->isCredit())->toBeTrue();
});

it('reads the transaction id as the reference and the valuta date', function () {
    $rows = migrosCardParser()->parse(migrosCardFixture())->rows;

    expect($rows[0]->reference)->toBe('TX0000123')
        ->and($rows[0]->date->format('Y-m-d'))->toBe('2026-09-15')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-09-16');
});

it('refuses the account statement, which names no bank', function () {
    // Migros Bank's account statement is byte-identical in shape to Valiant's.
    $csv = "Datum;Buchungstext;Betrag;Valuta\n04.09.26;Zahlungseingang;1838.00;04.09.26\n";

    expect(migrosCardParser()->supports($csv))->toBeFalse();
});
