<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Value;

/**
 * Reads the amount notations found in Swiss bank exports and returns an exact
 * decimal string. Never a float: a parser has no business rounding money.
 *
 * Handled, because all of it turns up in real files:
 *   1'234.56    Swiss thousands, straight or typographic apostrophe
 *   1 234.56    space, non-breaking space, thin space, narrow no-break space
 *   1234,56     comma decimal
 *   1,234.56    English thousands
 *   1.234,56    German thousands
 *   (1234.56)   parenthesised negative
 *   1234.56-    trailing minus
 *   CHF 1234.56 leading or trailing ISO currency code
 */
final class Amount
{
    /** Characters banks use as a thousands separator, all of which we drop. */
    private const array GROUPING = ["'", "\u{2019}", "\u{2018}", ' ', "\u{00A0}", "\u{202F}", "\u{2009}"];

    /**
     * Returns the amount as a signed decimal string, or null when the cell
     * holds no readable amount — including when it is simply empty, which is
     * the normal case for the unused half of a credit/debit column pair.
     */
    public static function parse(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        $negative = false;

        // (1234.56) — accounting parentheses.
        if (preg_match('/^\((.*)\)$/', $value, $matches) === 1) {
            $negative = true;
            $value = trim($matches[1]);
        }

        // A currency code glued to the number, either side.
        $value = (string) preg_replace('/^[A-Za-z]{3}\s*|\s*[A-Za-z]{3}$/u', '', $value);
        $value = trim($value);

        // 1234.56- — trailing sign, common in German-language exports.
        if (str_ends_with($value, '-')) {
            $negative = true;
            $value = rtrim(substr($value, 0, -1));
        }

        $value = str_replace(self::GROUPING, '', $value);

        if (str_starts_with($value, '-')) {
            $negative = ! $negative;
            $value = substr($value, 1);
        } elseif (str_starts_with($value, '+')) {
            $value = substr($value, 1);
        }

        $value = self::resolveDecimalSeparator($value);

        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            return null;
        }

        return self::sign($value, $negative);
    }

    /**
     * Whether the cell is a spreadsheet formula rather than a number.
     *
     * The test is deliberately two-sided: the value must open with one of the
     * characters a spreadsheet treats as the start of an expression — `=`, `@`,
     * `+`, `-`, or a leading tab or carriage return — **and** must not be
     * readable as an amount. Without that second half, "-150.50" and "+1200"
     * would be flagged, and they are simply amounts.
     */
    public static function looksLikeFormula(?string $raw): bool
    {
        if ($raw === null) {
            return false;
        }

        $value = trim($raw, " \u{00A0}\u{202F}");

        if ($value === '' || ! in_array($value[0], ['=', '@', '+', '-', "\t", "\r"], true)) {
            return false;
        }

        return self::parse($value) === null;
    }

    /** Drops the sign, e.g. for files that split credit and debit into two columns. */
    public static function abs(string $amount): string
    {
        return ltrim($amount, '-');
    }

    /** Flips the sign, leaving a zero amount unsigned. */
    public static function negate(string $amount): string
    {
        return str_starts_with($amount, '-')
            ? substr($amount, 1)
            : self::sign($amount, true);
    }

    /**
     * Decides which of "." and "," is the decimal mark, then normalises to ".".
     *
     * A repeated separator is only treated as grouping when the groups are
     * genuinely three digits wide. Without that check "15.03.2026" would be
     * read as the amount 15032026 — which matters, because a date sitting in a
     * column a profile expected to hold money is exactly the situation these
     * checks exist to catch. Anything that fits no rule is returned untouched
     * and fails the final numeric check.
     */
    private static function resolveDecimalSeparator(string $value): string
    {
        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $decimal = strrpos($value, ',') > strrpos($value, '.') ? ',' : '.';
            $grouping = $decimal === ',' ? '.' : ',';

            if (substr_count($value, $decimal) > 1) {
                return $value;
            }

            [$whole, $fraction] = explode($decimal, $value, 2);

            return self::isGrouped($whole, $grouping)
                ? str_replace($grouping, '', $whole).'.'.$fraction
                : $value;
        }

        if ($hasComma) {
            if (substr_count($value, ',') === 1) {
                return str_replace(',', '.', $value);
            }

            return self::isGrouped($value, ',') ? str_replace(',', '', $value) : $value;
        }

        if ($hasDot && substr_count($value, '.') > 1) {
            return self::isGrouped($value, '.') ? str_replace('.', '', $value) : $value;
        }

        return $value;
    }

    /** True for 1'234, 1'234'567 — three-digit groups all the way down. */
    private static function isGrouped(string $value, string $mark): bool
    {
        return preg_match('/^\d{1,3}('.preg_quote($mark, '/').'\d{3})+$/', $value) === 1;
    }

    /** Applies the sign, never producing "-0" or "-0.00". */
    private static function sign(string $value, bool $negative): string
    {
        if (! $negative || preg_match('/^0*(\.0*)?$/', $value) === 1) {
            return $value;
        }

        return '-'.$value;
    }
}
