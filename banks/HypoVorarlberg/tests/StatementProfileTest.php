<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\HypoVorarlberg\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function hypoParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function hypoFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a Hypo Vorarlberg statement and reports it as Austrian', function () {
    $file = hypoParser()->parse(hypoFixture());

    expect($file->bank->key)->toBe('hypovorarlberg')
        ->and($file->bank->country)->toBe('at')
        ->and($file)->toHaveCount(3);
});

it('reads comma decimals and German thousands', function () {
    $rows = hypoParser()->parse(hypoFixture())->rows;

    expect($rows[0]->amount)->toBe('-40.51')
        ->and($rows[1]->amount)->toBe('-52.32')
        // "1.240,55" — dot for thousands, comma for the decimal.
        ->and($rows[2]->amount)->toBe('1240.55');
});

it('reads ISO dates', function () {
    $rows = hypoParser()->parse(hypoFixture())->rows;

    expect($rows[0]->date->format('Y-m-d'))->toBe('2026-12-31')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-12-31');
});

it('tolerates the dotted ISO date form, though no real export attests it', function () {
    // Every published sample uses dashes. The dotted form is kept as a
    // defensive tolerance — this is the test that actually exercises it.
    $csv = "IBAN;Auszugsnummer;Buchungsdatum;Waehrung;Betrag;Buchungstext\n"
        ."AT611904300234573201;12;2026.12.31;EUR;-40,51;Abschluss\n";

    $rows = hypoParser()->parse($csv)->rows;

    expect($rows[0]->date->format('Y-m-d'))->toBe('2026-12-31')
        ->and($rows[0]->amount)->toBe('-40.51');
});

it('takes the IBAN and the currency from their columns', function () {
    $file = hypoParser()->parse(hypoFixture());

    expect($file->account->iban)->toBe('AT611904300234573201')
        ->and($file->rows[0]->currency)->toBe('EUR');
});

it('joins the booking text, the movement text and the counterparty', function () {
    $rows = hypoParser()->parse(hypoFixture())->rows;

    expect($rows[0]->label)->toBe('Abschluss Kontoführung')
        ->and($rows[1]->label)->toBe('SEPA-Zahlung Rechnung 4471 Muster GmbH')
        ->and($rows[1]->reference)->toBe('REF0002');
});

it('keeps the SEPA detail it does not model', function () {
    $rows = hypoParser()->parse(hypoFixture())->rows;

    expect($rows[0]->raw)->toContain('2026-12-31-21.35.45.616362', 'Giro')
        ->and($rows[1]->extras)->toBe(['Kategorie' => 'Freizeit & Genuss']);
});

it('refuses an ordinary German-language statement', function () {
    $csv = "IBAN;Buchungsdatum;Valutadatum;Waehrung;Betrag;Buchungstext\n"
        ."AT611904300234573201;2026.12.31;2026.12.31;EUR;-40,51;Abschluss\n";

    expect(hypoParser()->supports($csv))->toBeFalse();
});
