<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Csv;

use Kokonut\SwissBankCsvParser\Dto\Warning;

/**
 * Turns the bytes of a CSV export into a {@see CsvDocument}.
 *
 * Everything here is bank-agnostic: encoding, line endings, which character
 * separates the fields, and the "Label: value" block banks like to print above
 * the table. No column is interpreted at this stage.
 */
final class Reader
{
    /** In rough order of likelihood for Swiss bank exports. */
    private const array DELIMITERS = [';', ',', "\t", '|'];

    /**
     * Most exports are Latin-1 or Windows-1252 rather than UTF-8. Windows-1252
     * is a superset of Latin-1, so decoding as 1252 is strictly safer: it also
     * recovers the euro sign and typographic quotes that Latin-1 loses.
     */
    private const string FALLBACK_ENCODING = 'Windows-1252';

    public static function read(string $contents): CsvDocument
    {
        $warnings = [];

        $contents = self::stripByteOrderMark($contents);

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = (string) mb_convert_encoding($contents, 'UTF-8', self::FALLBACK_ENCODING);
            $encoding = self::FALLBACK_ENCODING;
            $warnings[] = new Warning(
                Warning::ENCODING_CONVERTED,
                'The file was not valid UTF-8 and was decoded as '.self::FALLBACK_ENCODING.'.',
                ['encoding' => self::FALLBACK_ENCODING],
            );
        } else {
            $encoding = 'UTF-8';
        }

        $contents = self::normaliseLineEndings($contents);

        $delimiter = self::detectDelimiter($contents);
        $rows = self::tokenise($contents, $delimiter);

        return new CsvDocument(
            rows: $rows,
            delimiter: $delimiter,
            sourceEncoding: $encoding,
            metadata: self::extractMetadata($rows),
            warnings: $warnings,
        );
    }

    private static function stripByteOrderMark(string $contents): string
    {
        return str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents;
    }

    /**
     * Some exports opened on Windows come back with doubled carriage returns,
     * which would otherwise produce a blank row between every real one.
     */
    private static function normaliseLineEndings(string $contents): string
    {
        $contents = str_replace("\r\r\n", "\n", $contents);

        return str_replace(["\r\n", "\r"], "\n", $contents);
    }

    /**
     * Picks the delimiter that yields the most consistent table, rather than
     * the one that simply occurs most often — a description field full of
     * commas would otherwise beat the real separator.
     */
    private static function detectDelimiter(string $contents): string
    {
        $sample = self::sample($contents);
        $best = self::DELIMITERS[0];
        $bestScore = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $score = self::scoreDelimiter($sample, $delimiter);

            if ($score > $bestScore) {
                $best = $delimiter;
                $bestScore = $score;
            }
        }

        return $best;
    }

    /**
     * Score = the most common column count, times how many rows agree on it.
     * A delimiter that never splits anything scores zero.
     */
    private static function scoreDelimiter(string $sample, string $delimiter): int
    {
        $counts = [];

        foreach (self::tokenise($sample, $delimiter) as $row) {
            $width = count($row);

            if ($width > 1) {
                $counts[$width] = ($counts[$width] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return 0;
        }

        $agreement = max($counts);
        $width = (int) array_search($agreement, $counts, true);

        return $width * $agreement;
    }

    /** The first 50 lines are plenty to recognise the shape of a table. */
    private static function sample(string $contents): string
    {
        $lines = explode("\n", $contents);

        return implode("\n", array_slice($lines, 0, 50));
    }

    /**
     * Real RFC 4180 parsing, so quoted fields containing the delimiter or a
     * line break survive intact.
     *
     * @return list<list<string>>
     */
    private static function tokenise(string $contents, string $delimiter): array
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $contents);
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            // fgetcsv reports a blank line as [null]; keep it as an empty row so
            // row indexes still line up with the file.
            $rows[] = $row === [null]
                ? []
                : array_map(static fn (?string $cell): string => (string) $cell, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Collects the "Label: value" preamble many banks print above the table:
     * account, currency, period, card holder, and so on.
     *
     * The whole file is scanned rather than the first N rows, because some
     * exports repeat or append that block. First occurrence wins.
     *
     * @param  list<list<string>>  $rows
     * @return array<string, string>
     */
    private static function extractMetadata(array $rows): array
    {
        $metadata = [];

        foreach ($rows as $row) {
            $label = trim($row[0] ?? '');

            if ($label === '' || ! str_ends_with($label, ':')) {
                continue;
            }

            $label = rtrim(rtrim($label, ':'));

            if ($label === '' || isset($metadata[$label])) {
                continue;
            }

            $value = trim(Text::unwrapExcelFormula(trim($row[1] ?? '')));

            if ($value !== '') {
                $metadata[$label] = $value;
            }
        }

        return $metadata;
    }
}
