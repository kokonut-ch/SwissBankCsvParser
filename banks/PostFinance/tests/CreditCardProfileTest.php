<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\PostFinance\CreditCardProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function postFinanceCardParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new CreditCardProfile]));
}

function postFinanceCardFixture(string $name = 'creditcard-fr.csv'): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

it('reads a credit card statement', function () {
    $file = postFinanceCardParser()->parse(postFinanceCardFixture());

    expect($file->bank->key)->toBe('postfinance')
        ->and($file->profile)->toBe('postfinance.creditcard')
        ->and($file)->toHaveCount(2);
});

it('reports the card account as a number, never as an IBAN', function () {
    $account = postFinanceCardParser()->parse(postFinanceCardFixture())->account;

    expect($account->iban)->toBeNull()
        ->and($account->number)->toBe('0000 0000 0000 0001')
        ->and($account->holder)->toBe('JEAN DUPONT');
});

it('reads the booking date as the row date and the purchase date as the value date', function () {
    $rows = postFinanceCardParser()->parse(postFinanceCardFixture())->rows;

    expect($rows[0]->date->format('Y-m-d'))->toBe('2026-10-31')
        ->and($rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-10-28')
        ->and($rows[0]->amount)->toBe('-45.9')
        ->and($rows[1]->date->format('Y-m-d'))->toBe('2026-10-05')
        ->and($rows[1]->valueDate?->format('Y-m-d'))->toBe('2026-10-03')
        ->and($rows[1]->amount)->toBe('20');
});

it('ignores the billing period column and the footer', function () {
    $file = postFinanceCardParser()->parse(postFinanceCardFixture());

    expect(array_map(fn (Row $row) => $row->label, $file->rows))
        ->toBe(['Achat Boutique', 'Remboursement commande']);
});

it('reads the German card statement', function () {
    $file = postFinanceCardParser()->parse(postFinanceCardFixture('creditcard-de.csv'));

    expect($file->account->number)->toBe('0000 0000 0000 0001')
        ->and($file->account->holder)->toBe('HANS MUSTER')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->label)->toBe('Einkauf Boutique')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-10-28')
        ->and($file->rows[0]->amount)->toBe('-45.9')
        ->and($file->rows[1]->amount)->toBe('20');
});

it('reads the English card statement', function () {
    $file = postFinanceCardParser()->parse(postFinanceCardFixture('creditcard-en.csv'));

    expect($file->account->number)->toBe('0000 0000 0000 0001')
        ->and($file->account->holder)->toBe('JOHN SMITH')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->label)->toBe('Purchase Boutique')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-10-28')
        ->and($file->rows[0]->amount)->toBe('-45.9')
        ->and($file->rows[1]->amount)->toBe('20');
});

it('flips the printed sign in every language', function (string $fixture) {
    // The debit column already says the direction, so the printed sign is
    // discarded — a debit comes back negative regardless of what was printed.
    $rows = postFinanceCardParser()->parse(postFinanceCardFixture($fixture))->rows;

    expect($rows[0]->isDebit())->toBeTrue()
        ->and($rows[1]->isCredit())->toBeTrue();
})->with(['creditcard-fr.csv', 'creditcard-de.csv', 'creditcard-en.csv']);

it('does not claim an account statement', function () {
    $csv = (string) file_get_contents(__DIR__.'/../fixtures/efinance-fr.csv');

    expect(postFinanceCardParser()->supports($csv))->toBeFalse();
});
