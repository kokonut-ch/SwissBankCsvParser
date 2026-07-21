<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Neon\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function neonParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function neonFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

it('reads a neon statement', function () {
    $file = neonParser()->parse(neonFixture());

    expect($file->bank->key)->toBe('neon')
        ->and($file->profile)->toBe('neon.statement')
        ->and($file)->toHaveCount(3);
});

it('takes the signed amount at face value', function () {
    $rows = neonParser()->parse(neonFixture())->rows;

    expect($rows[0]->amount)->toBe('-5.00')
        ->and($rows[1]->amount)->toBe('2000.00');
});

it('joins the description and the subject', function () {
    $rows = neonParser()->parse(neonFixture())->rows;

    expect($rows[1]->label)->toBe('Muster AG Salaire novembre')
        ->and($rows[0]->label)->toBe('App Store');
});

it('does not mistake the currency of a foreign purchase for the account currency', function () {
    $file = neonParser()->parse(neonFixture());

    // The third row was paid in USD. That says nothing about the account, and
    // the file says nothing else, so the currency stays unknown.
    expect($file->account->currency)->toBeNull()
        ->and($file->rows[2]->currency)->toBeNull()
        ->and($file->hasWarning(Warning::CURRENCY_NOT_DETECTED))->toBeTrue();
});

it('keeps the category as an extra', function () {
    $rows = neonParser()->parse(neonFixture())->rows;

    expect($rows[0]->extras)->toBe(['Category' => 'shopping']);
});

it('refuses a statement without its product columns', function () {
    $csv = "\"Date\";\"Amount\";\"Description\"\n\"2026-12-30\";\"-5.00\";\"App\"\n";

    expect(neonParser()->supports($csv))->toBeFalse();
});
