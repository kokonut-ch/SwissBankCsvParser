<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Csv;

/** Small string helpers shared by the reader, the lexicon and the profiles. */
final class Text
{
    /**
     * Collapses runs of whitespace to a single space, trims, and folds curly
     * apostrophes onto the straight one — so "Date d’écriture" and
     * "Date d'écriture" are the same heading, which in real exports they are.
     */
    public static function normalise(string $value): string
    {
        $value = str_replace(["\u{2019}", "\u{2018}", "\u{00B4}"], "'", $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** Unwraps the ="…" notation banks use to stop Excel mangling IBANs and dates. */
    public static function unwrapExcelFormula(string $value): string
    {
        if (preg_match('/^="(.*)"$/s', $value, $matches) === 1) {
            return $matches[1];
        }

        return $value;
    }

    /** Case-insensitive, whitespace-insensitive, apostrophe-insensitive equality. */
    public static function equals(string $left, string $right): bool
    {
        return mb_strtolower(self::normalise($left), 'UTF-8')
            === mb_strtolower(self::normalise($right), 'UTF-8');
    }
}
