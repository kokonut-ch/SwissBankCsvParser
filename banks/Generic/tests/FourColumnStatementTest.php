<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\Generic\FourColumnStatement;
use Kokonut\SwissBankCsvParser\Csv\Reader;
use Kokonut\SwissBankCsvParser\Profiles\ColumnMap;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

it('holds a match on the shared layout below the generic reader, and says why', function () {
    $columns = new ColumnMap(['booking_date' => 0, 'description' => 1, 'amount' => 2], headerRow: 0);

    $capped = FourColumnStatement::cap(new ProfileMatch(0.55, ['all required columns present'], $columns), 'Valiant');
    $lower = FourColumnStatement::cap(new ProfileMatch(0.10, [], $columns), 'Valiant');

    expect($capped->score)->toBe(0.25)
        ->and($capped->score)->toBeLessThan(0.30)
        ->and($capped->reasons)->toBe([
            'all required columns present',
            'four-column layout shared with Valiant: the bank is not named',
        ])
        ->and($capped->columns)->toBe($columns)
        // A ceiling, never a floor.
        ->and($lower->score)->toBe(0.10);
});

it('tells the layout from a richer export that begins the same way', function (string $headings, bool $isLayout) {
    $document = Reader::read($headings."\n30.09.26;Zahlung;-12.00;30.09.26\n");
    $columns = new ColumnMap(['booking_date' => 0, 'description' => 1, 'amount' => 2], headerRow: 0);

    expect(FourColumnStatement::isLayout($document, $columns))->toBe($isLayout);
})->with([
    'the four columns' => ['Datum;Buchungstext;Betrag;Valuta', true],
    'with a running balance' => ['Datum;Buchungstext;Betrag;Saldo;Valuta', true],
    'with trailing empty cells' => ['Datum;Buchungstext;Betrag;Valuta;;', true],
    'with a message column' => ['Datum;Buchungstext;Mitteilung;Betrag;Valuta', false],
]);
