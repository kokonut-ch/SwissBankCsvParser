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

it('reads the 2023 layout, whose category has no Registered prefix', function () {
    // The older export is eight quoted, comma-separated columns and its
    // category heading is plain "Kategorie" — no "Registrierte" prefix, so the
    // registered-category signature never fires. With nothing else to sign on,
    // the file fell through to the generic reader, which took the
    // issuer-signed amounts at face value and turned every purchase into
    // income.
    $csv = (string) file_get_contents(__DIR__.'/../fixtures/statement-legacy-de.csv');

    $file = swisscardParser()->parse($csv);

    expect($file->profile)->toBe('swisscard.statement')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->amount)->toBe('-23.70')
        ->and($file->rows[0]->isDebit())->toBeTrue()
        ->and($file->rows[1]->amount)->toBe('12.00')
        ->and($file->rows[1]->isCredit())->toBeTrue();
});

it('reads the Italian statement, where Valuta is the currency', function () {
    // Italian "Valuta" means currency, not value date — the shared lexicon
    // deliberately lists it under ValueDate only, so this profile has to claim
    // it for Currency explicitly or the currency of every Italian row is lost.
    $csv = (string) file_get_contents(__DIR__.'/../fixtures/statement-legacy-it.csv');

    $file = swisscardParser()->parse($csv);

    expect($file->profile)->toBe('swisscard.statement')
        ->and($file->rows[0]->currency)->toBe('CHF')
        ->and($file->rows[0]->valueDate)->toBeNull()
        ->and($file->rows[0]->amount)->toBe('-39.00')
        ->and($file->rows[1]->amount)->toBe('163.60');
});

it('reads the Italian twelve-column layout, pending foreign rows included', function () {
    // A purchase in a foreign currency that is still pending has no CHF amount
    // yet: Valuta and Importo are both empty, only the foreign pair is filled.
    // The row is kept with a null amount — dropping it is the caller's call.
    $csv = (string) file_get_contents(__DIR__.'/../fixtures/statement-it.csv');

    $file = swisscardParser()->parse($csv);

    expect($file->profile)->toBe('swisscard.statement')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->currency)->toBe('CHF')
        ->and($file->rows[0]->amount)->toBe('-20.85')
        ->and($file->rows[1]->amount)->toBeNull()
        ->and($file->rows[1]->currency)->toBeNull()
        ->and($file->rows[2]->amount)->toBe('15.00');
});

it('lets the sign win when the debit/credit word disagrees', function () {
    // The word column is not consulted, so a line where it contradicts the
    // sign must follow the sign. Without such a line, "ignored" is untestable.
    $csv = "Transaction date;Description;Card number;Currency;Amount;Debit/Credit;Status;Registered Category\n"
        ."13.11.2026;MUSTER SHOP;XXXX 0001;CHF;50.00;Credit;Booked;Shopping\n";

    $rows = swisscardParser()->parse($csv)->rows;

    expect($rows[0]->amount)->toBe('-50.00')
        ->and($rows[0]->isDebit())->toBeTrue();
});

it('refuses a statement without the registered category column', function () {
    $csv = "Transaction date;Description;Currency;Amount\n13.11.2026;Shop;CHF;189.00\n";

    expect(swisscardParser()->supports($csv))->toBeFalse();
});
