<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Swisscard\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function swisscardParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function swisscardFixture(string $name = 'statement.csv'): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

/**
 * Every fixture, with how many rows carry an amount.
 *
 * @return array<string, int>
 */
function swisscardLayouts(): array
{
    return [
        'statement.csv' => 3,
        'statement-it.csv' => 2,
        'statement-fr.csv' => 3,
        'statement-legacy-de.csv' => 2,
        'statement-legacy-it.csv' => 2,
    ];
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

it('reads the French twelve-column layout', function () {
    // Published upstream in November 2026. Its date and category headings,
    // "Date transaction" and "Catégorie enregistrée", are Swisscard's own
    // wording: without them the file was refused by every profile.
    $file = swisscardParser()->parse(swisscardFixture('statement-fr.csv'));

    expect($file->profile)->toBe('swisscard.statement')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->date->format('Y-m-d'))->toBe('2026-11-18')
        ->and($file->rows[0]->label)->toBe('MUSTER SHOP GENÈVE');
});

it('flips the issuer sign on the French layout too', function () {
    $rows = swisscardParser()->parse(swisscardFixture('statement-fr.csv'))->rows;

    // A purchase ("Débit") is printed positive, the card payment ("Crédit")
    // negative, exactly as in the other languages.
    expect($rows[0]->amount)->toBe('-74.50')
        ->and($rows[0]->isDebit())->toBeTrue()
        ->and($rows[2]->amount)->toBe('1200.00')
        ->and($rows[2]->isCredit())->toBeTrue();
});

it('takes the French currency and amount, never the foreign pair', function () {
    // "Monnaie étrangère" and "Montant étranger" sit beside "Monnaie" and
    // "Montant". Only whole headings match, so the settled CHF pair is read.
    $rows = swisscardParser()->parse(swisscardFixture('statement-fr.csv'))->rows;

    expect($rows[1]->currency)->toBe('CHF')
        ->and($rows[1]->amount)->toBe('-23.15');
});

it('reports the card, the status and the registered category under their French headings', function () {
    $rows = swisscardParser()->parse(swisscardFixture('statement-fr.csv'))->rows;

    // The merchant category ("Catégorie de commerçant") is not the registered
    // one and stays in $raw, as "Merchant Category" does in English. A card
    // payment has no registered category, so the key is absent.
    expect($rows[1]->extras)->toBe([
        'Numéro de carte' => 'XXXX 0001',
        'Statut' => 'Comptabilisée',
        'Catégorie enregistrée' => 'Restaurants, Bar',
    ])->and($rows[2]->extras)->toBe([
        'Numéro de carte' => 'XXXX 0001',
        'Statut' => 'Comptabilisée',
    ]);
});

it('signs a French file on its debit/credit heading alone', function () {
    // The 2023 layout's category heading is plain, so the word column is its
    // only signature. The French spelling signs as the other three do.
    $csv = "Date transaction,Description,Numéro de carte,Monnaie,Montant,Débit/Crédit,Statut,Catégorie\n"
        ."13.11.2026,MUSTER SHOP,XXXX 0001,CHF,50.00,Débit,Comptabilisée,Shopping\n";

    $rows = swisscardParser()->parse($csv)->rows;

    expect($rows[0]->amount)->toBe('-50.00')
        ->and($rows[0]->currency)->toBe('CHF');
});

it('identifies every layout in free detection', function (string $fixture) {
    // The generic reader takes card signs at face value, so a Swisscard file it
    // won would read every purchase as income.
    $csv = swisscardFixture($fixture);
    $report = (new SwissBankCsvParser)->detect($csv);
    $file = (new SwissBankCsvParser)->parse($csv);

    expect($report->best()?->profile)->toBe('swisscard.statement')
        ->and($report->isConfident())->toBeTrue()
        ->and($file->bank->key)->toBe('swisscard')
        ->and(array_filter($file->rows, static fn (Row $row): bool => $row->amount !== null))
        ->toHaveCount(swisscardLayouts()[$fixture]);
})->with(array_keys(swisscardLayouts()));

it('reads every fixture once the bank is confirmed', function (string $fixture) {
    $file = swisscardParser()->parse(swisscardFixture($fixture));

    expect($file->profile)->toBe('swisscard.statement')
        ->and(array_filter($file->rows, static fn (Row $row): bool => $row->amount !== null))
        ->toHaveCount(swisscardLayouts()[$fixture]);
})->with(array_keys(swisscardLayouts()));

it('has a line above for every fixture the bank ships', function () {
    $fixtures = array_map(basename(...), glob(__DIR__.'/../fixtures/*.csv') ?: []);
    sort($fixtures);

    $listed = array_keys(swisscardLayouts());
    sort($listed);

    expect($fixtures)->toBe($listed);
});
