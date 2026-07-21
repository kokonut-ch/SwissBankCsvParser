<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\Exceptions\UnsupportedFileException;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;
use Kokonut\SwissBankCsvParser\Value\Amount;
use Kokonut\SwissBankCsvParser\Value\DateParser;

/**
 * What a CSV file may not do to the process that reads it.
 *
 * A bank statement arrives from outside — uploaded by a user, fetched from a
 * mailbox — so every one of these cases is reachable by someone who chooses the
 * bytes.
 */
function hostile(string $body): string
{
    return "Datum;Buchungstext;Gutschrift;Belastung\n".$body;
}

it('treats a spreadsheet formula as text, and hands it back untouched', function (string $payload) {
    $file = (new SwissBankCsvParser)->parse(hostile("01.01.2026;{$payload};;100.00\n"));

    // Nothing is executed, and nothing is quietly rewritten either: a caller
    // that wants to neutralise this needs to see exactly what the file said.
    expect($file->rows[0]->label)->toBe($payload)
        ->and($file->rows[0]->raw[1])->toBe($payload);
})->with([
    'excel command' => ["=cmd|'/c calc'!A0"],
    'sum' => ['@SUM(1+1)*cmd'],
    'leading plus' => ['+AAAA'],
    'leading minus' => ['-2+3'],
    'hyperlink' => ['=HYPERLINK("http://example.invalid","click")'],
]);

it('reads a formula in the amount column as zero and says so', function (string $payload) {
    $file = (new SwissBankCsvParser)->parse(hostile("01.01.2026;Zahlung;;{$payload}\n"));

    // Never the result of the formula, and never null either: null is what a
    // balance line looks like, and this is not one. Zero, loudly.
    expect($file->rows[0]->amount)->toBe('0')
        ->and($file->hasWarning(Warning::AMOUNT_IS_FORMULA))->toBeTrue()
        ->and($file->warningsOf(Warning::AMOUNT_IS_FORMULA)[0]->context)
        ->toBe(['line' => 2, 'value' => $payload]);
})->with([
    'equals' => ['=1+1'],
    'at sign' => ['@SUM(1+1)'],
    'plus' => ['+1+1'],
    'command' => ["=cmd|'/c calc'!A0"],
]);

it('never mistakes a signed amount for a formula', function (string $payload, string $expected) {
    $file = (new SwissBankCsvParser)->parse(hostile("01.01.2026;Zahlung;{$payload};\n"));

    expect($file->rows[0]->amount)->toBe($expected)
        ->and($file->hasWarning(Warning::AMOUNT_IS_FORMULA))->toBeFalse();
})->with([
    'explicit plus' => ['+1200.00', '1200.00'],
    'negative' => ['-1200.00', '1200.00'],
    'plain' => ['1200.00', '1200.00'],
]);

it('treats markup, scripts and control characters as inert text', function (string $payload) {
    // Quoted, because several of these carry the delimiter themselves — that is
    // ordinary CSV, and the point here is what happens to the *content*.
    $quoted = '"'.str_replace('"', '""', $payload).'"';

    $file = (new SwissBankCsvParser)->parse(hostile("01.01.2026;{$quoted};;100.00\n"));

    expect($file)->toHaveCount(1)
        ->and($file->rows[0]->raw[1])->toBe($payload);
})->with([
    'script tag' => ['<script>alert(1)</script>'],
    'php open tag' => ['<?php system("id"); ?>'],
    'null byte' => ["abc\0def"],
    'ansi escape' => ["\e[2J\e[1;1H"],
    'path traversal' => ['../../../../etc/passwd'],
    'serialized object' => ['O:8:"stdClass":0:{}'],
]);

it('never reads a path named inside the file', function () {
    // The only filesystem access is the path the caller passed to parseFile();
    // nothing in the content can redirect it.
    $file = (new SwissBankCsvParser)->parse(hostile("01.01.2026;/etc/passwd;;100.00\n"));

    expect($file->rows[0]->label)->toBe('/etc/passwd');
});

it('survives a quote that is never closed', function () {
    // The reader consumes to end of input rather than looping: the rest of the
    // file collapses into one field. Lossy, unavoidable, and above all bounded —
    // it terminates, and what survives is reported honestly.
    $file = (new SwissBankCsvParser)->parse(hostile("01.01.2026;\"never closed;;100.00\n"));

    expect($file)->toHaveCount(1)
        ->and($file->rows[0]->label)->toBe('never closed;;100.00')
        ->and($file->rows[0]->amount)->toBeNull();
});

it('resists catastrophic backtracking on every regex that sees file content', function (string $label, callable $call) {
    $started = microtime(true);
    $call();

    // Each of these is fed 100k+ adversarial characters. A vulnerable pattern
    // would not come back in seconds, let alone in one.
    expect(microtime(true) - $started)->toBeLessThan(1.0, $label);
})->with([
    'currency suffix / spaces' => ['spaces', fn () => Lexicon::splitCurrency(str_repeat(' ', 60000).'X')],
    'currency suffix / words' => ['words', fn () => Lexicon::splitCurrency(str_repeat('a ', 50000).'CHF')],
    'amount / grouped digits' => ['grouped', fn () => Amount::parse(str_repeat('1.234', 20000).'x')],
    'amount / open bracket' => ['bracket', fn () => Amount::parse('('.str_repeat('9', 100000))],
    'date / long digits' => ['digits', fn () => DateParser::parse(str_repeat('1', 100000))],
    'whitespace collapse' => ['blanks', fn () => Text::normalise(str_repeat(" \t\n", 50000))],
    'lexicon lookup' => ['lexicon', fn () => Lexicon::matches(str_repeat('AB', 50000), Term::Credit)],
]);

it('reads a wide file without falling over', function () {
    $wide = str_repeat('c;', 2000)."\n".str_repeat('v;', 2000)."\n";

    expect(fn () => (new SwissBankCsvParser)->parse($wide))
        ->toThrow(UnsupportedFileException::class);
});

it('reads a long file in linear time', function () {
    $rows = 20000;
    $started = microtime(true);

    $file = (new SwissBankCsvParser)->parse(
        hostile(str_repeat("01.01.2026;Zahlung Muster AG;;100.00\n", $rows)),
    );

    expect($file)->toHaveCount($rows)
        ->and(microtime(true) - $started)->toBeLessThan(10.0);
});

it('refuses an empty or nonsensical file rather than guessing', function (string $csv) {
    expect((new SwissBankCsvParser)->supports($csv))->toBeFalse();
})->with([
    'binary' => ["\x00\x01\x02\x03\x04"],
    'xml' => ['<?xml version="1.0"?><Document><Stmt/></Document>'],
    'html' => ['<html><body><table><tr><td>01.01.2026</td></tr></table></body></html>'],
    'json' => ['{"date":"2026-01-01","amount":100}'],
    'one column' => ["a\nb\nc\n"],
]);
