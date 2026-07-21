<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Value\DateParser;

it('reads the notations Swiss banks actually print', function (string $raw, string $expected) {
    expect(DateParser::parse($raw)?->format('Y-m-d'))->toBe($expected);
})->with([
    'swiss dotted' => ['15.03.2026', '2026-03-15'],
    'swiss dotted unpadded' => ['1.3.2026', '2026-03-01'],
    'iso' => ['2026-03-15', '2026-03-15'],
    'slashes' => ['15/03/2026', '2026-03-15'],
    'dashes' => ['15-03-2026', '2026-03-15'],
    'dotted iso' => ['2026.03.15', '2026-03-15'],
    'compact' => ['20260315', '2026-03-15'],
    'two digit year' => ['15.03.26', '2026-03-15'],
]);

it('does not let a four-digit format swallow a two-digit year', function () {
    // createFromFormat would read "15.03.26" as the year 26 under "d.m.Y",
    // without complaining. The shape check is what stops it.
    expect(DateParser::parse('15.03.26', ['d.m.Y']))->toBeNull()
        ->and(DateParser::parse('15.03.26')?->format('Y'))->toBe('2026');
});

it('pivots two-digit years on 1970', function () {
    expect(DateParser::parse('01.01.69')?->format('Y-m-d'))->toBe('2069-01-01')
        ->and(DateParser::parse('01.01.70')?->format('Y-m-d'))->toBe('1970-01-01');
});

it('starts the day at midnight', function () {
    expect(DateParser::parse('15.03.2026')?->format('H:i:s'))->toBe('00:00:00');
});

it('rejects anything that is not exactly a date', function (string $raw) {
    expect(DateParser::parse($raw))->toBeNull();
})->with([
    'empty' => [''],
    'blank' => ['   '],
    'text' => ['Date de début'],
    'label with colon' => ['Date de début:'],
    'a range' => ['30.09.2026 − 27.10.2026'],
    'trailing text' => ['15.03.2026 booked'],
    'impossible day' => ['31.02.2026'],
    'month out of range' => ['15.13.2026'],
    'a number' => ['1234.56'],
]);

it('only accepts the formats it was given', function () {
    expect(DateParser::parse('2026-03-15', ['d.m.Y']))->toBeNull()
        ->and(DateParser::parse('2026-03-15', ['Y-m-d'])?->format('Y-m-d'))->toBe('2026-03-15');
});
