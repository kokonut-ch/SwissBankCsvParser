<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\PostFinance\EFinance2010Profile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function postFinance2010Parser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new EFinance2010Profile]));
}

function postFinance2010Fixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

it('reads the English export, debit before credit', function () {
    $file = postFinance2010Parser()->parse(postFinance2010Fixture('efinance-2010-en.csv'));

    expect($file->bank->key)->toBe('postfinance')
        ->and($file->profile)->toBe('postfinance.efinance-2010')
        ->and(array_map(fn (Row $row) => $row->amount, $file->rows))
        ->toBe(['0.00', '-45.00', '-131.55', '1100.00', '-44.95']);
});

it('reads the dates, the labels and the balances', function () {
    $rows = postFinance2010Parser()->parse(postFinance2010Fixture('efinance-2010-en.csv'))->rows;

    expect($rows[1]->date->format('Y-m-d'))->toBe('2010-10-29')
        ->and($rows[1]->label)->toBe('E-FINANCE 01-23456-7 - SAMPLE SUPPLIER LTD')
        ->and($rows[1]->balance)->toBe('4180.20')
        ->and($rows[2]->balance)->toBeNull()
        // A purchase is booked days after it was made.
        ->and($rows[4]->date->format('Y-m-d'))->toBe('2010-09-14')
        ->and($rows[4]->valueDate?->format('Y-m-d'))->toBe('2010-09-10');
});

it('leaves the Details flag out of the amount and the label', function () {
    $rows = postFinance2010Parser()->parse(postFinance2010Fixture('efinance-2010-en.csv'))->rows;

    // The flag is "2" on every booked row. Read as money it would be the
    // amount of every line; read as text it would trail every label.
    expect($rows[1]->raw[2])->toBe('2')
        ->and($rows[1]->extras)->toBe([])
        ->and(array_filter($rows, fn (Row $row) => str_ends_with($row->label, ' 2')))->toBe([]);
});

it('reports that the file names no currency', function () {
    $file = postFinance2010Parser()->parse(postFinance2010Fixture('efinance-2010-en.csv'));

    expect($file->account->currency)->toBeNull()
        ->and($file->hasWarning(Warning::CURRENCY_NOT_DETECTED))->toBeTrue();
});

it('reads the German export, with a description over several lines', function () {
    $file = postFinance2010Parser()->parse(postFinance2010Fixture('efinance-2010-de.csv'));

    expect($file)->toHaveCount(3)
        ->and($file->rows[1]->label)->toBe('KAUF/DIENSTLEISTUNG VOM 23.12.2013 KARTEN NR. 00000001 MUSTER LADEN AG BERN')
        ->and($file->rows[1]->amount)->toBe('-124.00')
        ->and($file->rows[1]->valueDate?->format('Y-m-d'))->toBe('2013-12-23')
        ->and($file->rows[1]->extras)->toBe(['Kategorie' => 'Haushalt'])
        ->and($file->rows[2]->amount)->toBe('2500.00');
});

it('identifies both languages confidently', function (string $fixture, string $heading) {
    $report = (new SwissBankCsvParser)->detect(postFinance2010Fixture($fixture));

    expect($report->best()?->profile)->toBe('postfinance.efinance-2010')
        ->and($report->best()?->reasons)->toContain(sprintf('distinctive heading "%s"', $heading))
        ->and($report->isConfident())->toBeTrue();
})->with([
    ['efinance-2010-en.csv', 'BookingText'],
    ['efinance-2010-de.csv', 'Familienmitglied'],
]);

it('does not claim the German headings without the family-member column', function () {
    // Every one of these headings is ordinary German that other banks print too.
    $csv = "Buchung;Buchungstext;Details;Valuta;Belastung;Gutschrift;Saldo\n"
        ."31.12.2013;Zinsabschluss;;31.12.2013;-0.15;;2549.30\n";

    expect(postFinance2010Parser()->supports($csv))->toBeFalse();
});

it('does not claim the current e-finance export', function () {
    expect(postFinance2010Parser()->supports(postFinance2010Fixture('efinance-de.csv')))->toBeFalse();
});
