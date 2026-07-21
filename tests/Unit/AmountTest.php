<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Value\Amount;

it('reads the notations Swiss banks actually print', function (string $raw, ?string $expected) {
    expect(Amount::parse($raw))->toBe($expected);
})->with([
    'plain' => ['1234.56', '1234.56'],
    'swiss apostrophe' => ["1'234.56", '1234.56'],
    'typographic apostrophe' => ['1’234.56', '1234.56'],
    'space thousands' => ['1 234.56', '1234.56'],
    'non-breaking space' => ["1\u{00A0}234.56", '1234.56'],
    'narrow no-break space' => ["1\u{202F}234.56", '1234.56'],
    'comma decimal' => ['1234,56', '1234.56'],
    'english thousands' => ['1,234.56', '1234.56'],
    'german thousands' => ['1.234,56', '1234.56'],
    'repeated dot thousands' => ['1.234.567', '1234567'],
    'repeated comma thousands' => ['1,234,567', '1234567'],
    'explicit plus' => ['+1234.56', '1234.56'],
    'negative' => ['-1234.56', '-1234.56'],
    'accounting parentheses' => ['(1234.56)', '-1234.56'],
    'trailing minus' => ['1234.56-', '-1234.56'],
    'leading currency code' => ['CHF 1234.56', '1234.56'],
    'trailing currency code' => ['1234.56 EUR', '1234.56'],
    'integer' => ['25', '25'],
    'zero' => ['0.00', '0.00'],
    'empty' => ['', null],
    'blank' => ['   ', null],
    'not a number' => ['Paiement fournisseur', null],
    'date is not an amount' => ['15.03.2026', null],
]);

it('treats null as no amount rather than as zero', function () {
    expect(Amount::parse(null))->toBeNull();
});

it('never produces a negative zero', function () {
    expect(Amount::parse('(0.00)'))->toBe('0.00')
        ->and(Amount::negate('0'))->toBe('0');
});

it('flips and drops signs', function () {
    expect(Amount::negate('150.5'))->toBe('-150.5')
        ->and(Amount::negate('-150.5'))->toBe('150.5')
        ->and(Amount::abs('-150.5'))->toBe('150.5')
        ->and(Amount::abs('150.5'))->toBe('150.5');
});

it('keeps the scale the bank printed, so nothing is silently rounded', function () {
    expect(Amount::parse('150.5'))->toBe('150.5')
        ->and(Amount::parse('150.50'))->toBe('150.50')
        ->and(Amount::parse('0.123456789'))->toBe('0.123456789');
});
