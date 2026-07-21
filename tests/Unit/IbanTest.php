<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Value\Iban;

it('accepts a valid IBAN however it was printed', function (string $raw) {
    expect(Iban::parse($raw))->toBe('CH9300762011623852957');
})->with([
    'compact' => ['CH9300762011623852957'],
    'grouped' => ['CH93 0076 2011 6238 5295 7'],
    'lowercase' => ['ch9300762011623852957'],
]);

it('rejects things that merely look like an IBAN', function (string $raw) {
    expect(Iban::parse($raw))->toBeNull();
})->with([
    // Right shape, wrong checksum — this is the case that matters, because
    // card numbers and customer references pass a shape test.
    'bad checksum' => ['CH9300762011623852958'],
    'card number' => ['0000 0000 0000 0001'],
    'too short' => ['CH93 0076'],
    'empty' => [''],
    'words' => ['Compte de carte'],
]);

it('normalises without judging', function () {
    expect(Iban::normalise('ch93 0076-2011'))->toBe('CH9300762011');
});
