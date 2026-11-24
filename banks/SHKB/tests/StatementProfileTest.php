<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\SHKB\StatementProfile;
use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use Kokonut\SwissBankCsvParser\Detection\Candidate;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\Exceptions\UnsupportedFileException;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

/** The parser a caller builds once the user has confirmed the bank: Schaffhauser Kantonalbank's profile only. */
function shkbParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
}

function shkbFixture(): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/statement.csv');
}

/** The Schaffhauser Kantonalbank candidate of a free detection, if any. */
function shkbCandidate(string $csv): ?Candidate
{
    foreach ((new SwissBankCsvParser)->detect($csv) as $candidate) {
        if ($candidate->profile === 'shkb.statement') {
            return $candidate;
        }
    }

    return null;
}

it('reads the statement once the bank is confirmed', function () {
    $file = shkbParser()->parse(shkbFixture());

    expect($file->bank->key)->toBe('shkb')
        ->and($file->bank->name)->toBe('Schaffhauser Kantonalbank')
        ->and($file->profile)->toBe('shkb.statement')
        ->and($file->hasWarning(Warning::GENERIC_PROFILE_USED))->toBeFalse()
        // Newest first, as the bank prints them, and with two-digit years.
        ->and(array_map(static fn (Row $row): string => $row->date->format('Y-m-d'), $file->rows))
        ->toBe(['2026-09-30', '2026-09-29', '2026-09-29', '2026-09-28', '2026-09-24'])
        ->and(array_map(static fn (Row $row): ?string => $row->amount, $file->rows))
        ->toBe(['-12.00', '4250.00', '-2799.60', '-54.95', '-42.00'])
        ->and($file->rows[1]->label)->toBe('Zahlungseingang Beispiel GmbH, Rechnung 2026-117')
        // Paid by card on the 25th, booked on the 28th.
        ->and($file->rows[3]->valueDate?->format('Y-m-d'))->toBe('2026-09-25')
        ->and($file->rows[3]->currency)->toBe('CHF');
});

it('reads the preamble, and keeps the designation out of the holder', function () {
    $file = shkbParser()->parse(shkbFixture());

    expect($file->period->from)->toBeNull()
        // "Kontoauszug bis: 30.09.2026 ", trailing space and all.
        ->and($file->period->to?->format('Y-m-d'))->toBe('2026-09-30')
        ->and($file->account->iban)->toBeNull()
        ->and($file->account->number)->toBe('123.456-7 890')
        // "Saldo: CHF 8450.35": the ISO code is picked out of the value.
        ->and($file->account->currency)->toBe('CHF')
        // "Bezeichnung: Kontokorrent BUSINESS CHF" is the kind of account, not
        // its holder, whose name is on the unlabelled address lines.
        ->and($file->account->holder)->toBeNull()
        ->and($file->metadata['Bezeichnung'] ?? null)->toBe('Kontokorrent BUSINESS CHF');
});

it('never claims the file in free detection', function () {
    // Migros Bank's older layout, Valiant, acrevis, Schwyzer KB, Nidwaldner KB
    // and Zuger KB print exactly this file. Nothing in it says Schaffhauser
    // Kantonalbank, so the generic reader keeps it, and the bank has to be
    // named by whoever uploaded it.
    $csv = shkbFixture();
    $report = (new SwissBankCsvParser)->detect($csv);
    $file = (new SwissBankCsvParser)->parse($csv);

    expect($report->best()?->profile)->toBe('generic.signed-amount')
        ->and($report->isConfident())->toBeFalse()
        ->and(shkbCandidate($csv)?->score)->toBe(0.25)
        ->and($file->bank->key)->toBe('unknown')
        // The generic reader reads the same rows, unattributed.
        ->and(array_map(static fn (Row $row): ?string => $row->amount, $file->rows))
        ->toBe(['-12.00', '4250.00', '-2799.60', '-54.95', '-42.00']);
});

it('is read as this bank by a parser restricted to its key', function () {
    // What banks/SHKB/README.md shows a caller doing once the bank is named.
    $shkb = array_values(array_filter(
        (new SwissBankCsvParser)->profiles()->all(),
        static fn (BankProfile $profile): bool => $profile->identity()->key === 'shkb',
    ));

    $file = (new SwissBankCsvParser(new ProfileRegistry($shkb)))->parse(shkbFixture());

    expect($shkb)->toHaveCount(1)
        ->and($file->profile)->toBe('shkb.statement')
        ->and($file)->toHaveCount(5);
});

it('reads four-digit years and a running balance column', function () {
    // Nidwaldner KB's sample of the layout dates its rows dd.mm.yyyy, and one of
    // Clientis Bank Aareland's prints a balance between amount and value date.
    $csv = <<<'CSV'
    Kontoauszug bis: 30.09.2026 ;;;;
    Kontonummer: 123.456-7 890;;;;
    Saldo: CHF 8450.35;;;;
    ;;;;
    Datum;Buchungstext;Betrag;Saldo;Valuta
    30.09.2026;Kontoführungsgebühr;-12.00;8450.35;30.09.2026
    29.09.2026;Zahlungseingang Beispiel GmbH;4250.00;8462.35;29.09.2026
    CSV;

    $rows = shkbParser()->parse($csv)->rows;

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->date->format('Y-m-d'))->toBe('2026-09-30')
        ->and($rows[0]->balance)->toBe('8450.35')
        ->and($rows[1]->amount)->toBe('4250.00')
        ->and($rows[1]->balance)->toBe('8462.35');
});

it('reads no other layout, even one that begins with the same columns', function (string $csv) {
    expect(shkbParser()->supports($csv))->toBeFalse()
        ->and(fn () => shkbParser()->parse($csv))->toThrow(UnsupportedFileException::class);
})->with([
    'a message and a reference column' => "Datum;Buchungstext;Mitteilung;Referenznummer;Betrag;Saldo;Valuta\n12.02.2026;Zahlung;;12 34567;-350.40;4849.60;12.02.2026\n",
    'another booking text heading' => "Buchungsdatum;Avisierungstext;Betrag\n04.02.2026;Zahlung;-100.00\n",
    'English headings' => "Booked At;Text;Credit/Debit Amount;Balance\n2026-02-04;Supplier payment;-1240.55;8759.45\n",
    'split credit and debit' => "Datum;Buchungstext;Gutschrift;Belastung;Valuta\n04.02.2026;Zahlung;;100.00;04.02.2026\n",
]);
