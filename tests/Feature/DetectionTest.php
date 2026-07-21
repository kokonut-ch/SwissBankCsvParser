<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\Exceptions\UnreadableFileException;
use Kokonut\SwissBankCsvParser\Exceptions\UnsupportedFileException;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function statementFixture(string $bank, string $name): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/banks/'.$bank.'/fixtures/'.$name);
}

it('discovers every profile shipped under banks/', function () {
    $parser = new SwissBankCsvParser;

    expect($parser->profiles()->banks())->toContain('postfinance', 'unknown')
        ->and($parser->profiles()->get('postfinance.efinance'))->not->toBeNull();
});

it('identifies a known bank confidently', function () {
    $report = (new SwissBankCsvParser)->detect(statementFixture('PostFinance', 'efinance-fr.csv'));

    expect($report->best()?->profile)->toBe('postfinance.efinance')
        ->and($report->best()?->bank->name)->toBe('PostFinance')
        ->and($report->best()?->score)->toBeGreaterThan(0.6)
        ->and($report->isConfident())->toBeTrue();
});

it('ranks the generic fallback below the bank that owns the file', function () {
    $report = (new SwissBankCsvParser)->detect(statementFixture('PostFinance', 'efinance-fr.csv'));

    // The generic reader can read this file too, and says so — it just never
    // outranks a bank that identifies itself.
    expect($report->count())->toBeGreaterThan(1)
        ->and($report->candidates[1]->score)->toBeLessThan($report->candidates[0]->score);
});

it('explains itself, so a UI can show why', function () {
    $report = (new SwissBankCsvParser)->detect(statementFixture('PostFinance', 'efinance-fr.csv'));

    expect($report->best()?->reasons)->toContain('distinctive heading "Texte de notification"');
});

it('tells the two PostFinance layouts apart', function () {
    $parser = new SwissBankCsvParser;

    expect($parser->detect(statementFixture('PostFinance', 'creditcard-fr.csv'))->best()?->profile)
        ->toBe('postfinance.creditcard')
        ->and($parser->detect(statementFixture('PostFinance', 'efinance-fr.csv'))->best()?->profile)
        ->toBe('postfinance.efinance');
});

it('falls back to the generic reader for a bank it does not know', function () {
    $csv = "Buchungsdatum;Buchungstext;Gutschrift;Belastung\n04.02.2026;Zahlung;;100.00\n";

    $file = (new SwissBankCsvParser)->parse($csv);

    expect($file->bank->key)->toBe('unknown')
        ->and($file->hasWarning(Warning::GENERIC_PROFILE_USED))->toBeTrue();
});

it('refuses a file that is not a bank statement', function () {
    (new SwissBankCsvParser)->parse("foo,bar,baz\n1,2,3\n");
})->throws(UnsupportedFileException::class);

it('refuses an XML file', function () {
    (new SwissBankCsvParser)->parse('<?xml version="1.0"?><Document><Stmt/></Document>');
})->throws(UnsupportedFileException::class);

it('refuses an empty file', function () {
    (new SwissBankCsvParser)->parse('');
})->throws(UnreadableFileException::class);

it('reports a missing file rather than pretending it was unreadable', function () {
    (new SwissBankCsvParser)->parseFile('/no/such/statement.csv');
})->throws(UnreadableFileException::class);

it('reads from disk', function () {
    $file = (new SwissBankCsvParser)->parseFile(
        dirname(__DIR__, 2).'/banks/PostFinance/fixtures/efinance-fr.csv',
    );

    expect($file)->toHaveCount(3);
});
