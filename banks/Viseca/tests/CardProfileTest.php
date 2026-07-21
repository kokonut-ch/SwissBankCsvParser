<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\MigrosBank\CardProfile as MigrosCardProfile;
use Kokonut\SwissBankCsvParser\Banks\Viseca\CardProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function visecaParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new CardProfile]));
}

function visecaFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/card.csv');
}

it('reads a Viseca card export', function () {
    $file = visecaParser()->parse(visecaFixture());

    expect($file->bank->key)->toBe('viseca')
        ->and($file->profile)->toBe('viseca.card')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->reference)->toBe('TX0000123')
        ->and($file->rows[0]->label)->toBe('Muster Shop Lausanne CH Card payment');
});

it('flips the issuer sign, so a purchase is money out', function () {
    $rows = visecaParser()->parse(visecaFixture())->rows;

    // Viseca writes the statement from the issuer's side: a purchase is printed
    // positive, a refund negative. Read at face value, every charge on the card
    // would come out as income.
    expect($rows[0]->amount)->toBe('-105.45')
        ->and($rows[0]->isDebit())->toBeTrue()
        ->and($rows[1]->amount)->toBe('40.00')
        ->and($rows[1]->isCredit())->toBeTrue();
});

it('is told apart from the near-identical Migros Bank card export', function () {
    $both = new SwissBankCsvParser(new ProfileRegistry([
        new CardProfile,
        new MigrosCardProfile,
    ]));

    $migros = (string) file_get_contents(
        dirname(__DIR__, 2).'/MigrosBank/fixtures/card.csv',
    );

    // The two files differ by one heading: StateType against CardId. Each
    // profile signs on its own, and neither claims the other's file.
    expect($both->detect(visecaFixture())->count())->toBe(1)
        ->and($both->detect(visecaFixture())->best()?->profile)->toBe('viseca.card')
        ->and($both->detect($migros)->count())->toBe(1)
        ->and($both->detect($migros)->best()?->profile)->toBe('migrosbank.card');
});

it('wins over Migros Bank on the real header, which carries both CardId and StateType', function () {
    $both = new SwissBankCsvParser(new ProfileRegistry([
        new CardProfile,
        new MigrosCardProfile,
    ]));

    // Banana documents Viseca's own export as carrying CardId *and* StateType.
    // Signing on what is present cannot separate the two banks — Migros Bank has
    // to declare StateType as disqualifying.
    $csv = 'TransactionId;CardId;Date;ValutaDate;Amount;Currency;MerchantName;MerchantPlace;'
        ."MerchantCountry;StateType;Details\n"
        ."TX0000123;XXXX0001;2026-09-15;2026-09-16;105.45;CHF;Muster Shop;Lausanne;CH;Booked;Card payment\n";

    $report = $both->detect($csv);

    expect($report->count())->toBe(1)
        ->and($report->best()?->profile)->toBe('viseca.card')
        ->and($both->parse($csv)->rows[0]->amount)->toBe('-105.45');
});
