<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Generic\SignedAmountProfile;
use Kokonut\SwissBankCsvParser\Banks\Generic\SplitColumnsProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\Exceptions\UnsupportedFileException;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

function genericParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([
        new SplitColumnsProfile,
        new SignedAmountProfile,
    ]));
}

it('reads an unknown bank that splits credit and debit', function () {
    $csv = <<<'CSV'
    Buchungsdatum;Buchungstext;Valuta;Gutschrift;Belastung;Saldo
    04.02.2026;Zahlung Lieferant;05.02.2026;;1'240.55;8'759.45
    06.02.2026;Zahlungseingang;06.02.2026;3'000.00;;11'759.45
    CSV;

    $file = genericParser()->parse($csv);

    expect($file->profile)->toBe('generic.split-columns')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->amount)->toBe('-1240.55')
        ->and($file->rows[0]->balance)->toBe('8759.45')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-02-05')
        ->and($file->rows[1]->amount)->toBe('3000.00');
});

it('reads an unknown bank that uses one signed column', function () {
    $csv = <<<'CSV'
    Booked At;Text;Credit/Debit Amount;Balance
    2026-02-04;Supplier payment;-1240.55;8759.45
    2026-02-06;Incoming payment;3000.00;11759.45
    CSV;

    $file = genericParser()->parse($csv);

    expect($file->profile)->toBe('generic.signed-amount')
        ->and($file->rows[0]->amount)->toBe('-1240.55')
        ->and($file->rows[1]->amount)->toBe('3000.00');
});

it('always says when it fell back to guessing', function () {
    $csv = <<<'CSV'
    Datum;Buchungstext;Gutschrift;Belastung
    04.02.2026;Zahlung;;100.00
    CSV;

    $file = genericParser()->parse($csv);

    expect($file->hasWarning(Warning::GENERIC_PROFILE_USED))->toBeTrue()
        ->and($file->bank->key)->toBe('unknown');
});

it('never sounds confident', function () {
    $csv = <<<'CSV'
    Datum;Buchungstext;Valuta;Gutschrift;Belastung;Saldo;Referenz
    04.02.2026;Zahlung;05.02.2026;;100.00;900.00;R-1
    CSV;

    $report = genericParser()->detect($csv);

    expect($report->best()?->score)->toBeLessThanOrEqual(0.30)
        ->and($report->isConfident())->toBeFalse();
});

it('reads the header block of banks it cannot name', function () {
    // Migros Bank and Valiant ship an export that is indistinguishable from one
    // another's — same preamble, same four columns, same wording. Neither can
    // honestly be claimed, so the generic reader picks up what the preamble says
    // and leaves the bank unidentified.
    $csv = <<<'CSV'
    Kontoauszug bis: 04.09.2026 ;;;
    ;;;
    Kontonummer: 543.278.22;;;
    Bezeichnung: Privat;;;
    Saldo: CHF 38547.70;;;
    ;;;
    Datum;Buchungstext;Betrag;Valuta
    04.09.26;Zahlungseingang;1838.00;04.09.26
    04.09.26;Zahlung Muster AG;-204.45;04.09.26
    CSV;

    $file = genericParser()->parse($csv);

    expect($file->bank->key)->toBe('unknown')
        ->and($file->account->number)->toBe('543.278.22')
        // "Saldo: CHF 38547.70" — the ISO code is picked out of the value.
        ->and($file->account->currency)->toBe('CHF')
        ->and($file->account->holder)->toBe('Privat')
        ->and($file)->toHaveCount(2)
        ->and($file->rows[0]->amount)->toBe('1838.00')
        ->and($file->rows[1]->amount)->toBe('-204.45');
});

it('refuses a file that is not a statement at all', function () {
    genericParser()->parse("foo,bar,baz\n1,2,3\n");
})->throws(UnsupportedFileException::class);
