<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Value;

use DateTimeImmutable;

/**
 * Reads the date notations found in Swiss bank exports.
 *
 * Strict on purpose: a cell only yields a date when it matches a format
 * completely, with no leftover characters. That strictness is what lets the
 * extractors tell a data row from a heading, a subtotal or a legal footer —
 * the presence of a real date in the date column is the signal.
 */
final class DateParser
{
    /**
     * Tried in order, so the less ambiguous formats come first. Notably
     * "d.m.Y" before "d.m.y", otherwise 15.03.2026 would be read as year 20.
     *
     * @var list<string>
     */
    public const array COMMON = ['d.m.Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y.m.d', 'Ymd', 'd.m.y', 'd/m/y'];

    /**
     * @param  list<string>  $formats  PHP date formats, tried in order.
     */
    public static function parse(string $raw, array $formats = self::COMMON): ?DateTimeImmutable
    {
        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        foreach ($formats as $format) {
            // createFromFormat is lenient about field widths: "d.m.Y" happily
            // reads "15.03.26" as the year 26, and raises no warning while doing
            // it. Checking the shape first is what keeps a two-digit year out of
            // a four-digit format.
            if (preg_match(self::pattern($format), $value) !== 1) {
                continue;
            }

            // The "!" resets every unspecified field, so the result is midnight
            // rather than "today's time on that date".
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date === false) {
                continue;
            }

            $errors = DateTimeImmutable::getLastErrors();

            // Any warning means PHP had to be lenient — trailing characters, or
            // a rolled-over day such as 31.02. Both mean "this is not that format".
            if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            return $date;
        }

        return null;
    }

    /**
     * True when the cell holds a date in one of the given formats.
     *
     * @param  list<string>  $formats
     */
    public static function looksLikeDate(string $raw, array $formats = self::COMMON): bool
    {
        return self::parse($raw, $formats) !== null;
    }

    /**
     * A regex the input must satisfy before the format is even attempted.
     *
     * Separated formats such as "d.m.Y" allow one or two digits for day and
     * month, because banks are inconsistent about padding. Formats with no
     * separators at all, such as "Ymd", must be exact — there is nothing else
     * to tell the fields apart.
     */
    private static function pattern(string $format): string
    {
        /** @var array<string, string> $cache */
        static $cache = [];

        if (isset($cache[$format])) {
            return $cache[$format];
        }

        $separated = preg_match('/[^djmnYy]/', $format) === 1;

        $tokens = [
            'd' => $separated ? '\d{1,2}' : '\d{2}',
            'j' => '\d{1,2}',
            'm' => $separated ? '\d{1,2}' : '\d{2}',
            'n' => '\d{1,2}',
            'Y' => '\d{4}',
            'y' => '\d{2}',
        ];

        $pattern = '';

        foreach (str_split($format) as $character) {
            $pattern .= $tokens[$character] ?? preg_quote($character, '/');
        }

        return $cache[$format] = '/^'.$pattern.'$/';
    }
}
