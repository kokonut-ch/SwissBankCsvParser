<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Value;

/**
 * Just enough IBAN handling to decide whether a header field holds one.
 *
 * The mod-97 check matters more than it looks: header blocks are full of card
 * numbers, customer numbers and contract references that pass a shape test but
 * are not IBANs. Accepting one of those as an IBAN would be worse than
 * reporting none at all.
 */
final class Iban
{
    /** Strips spaces and punctuation and uppercases. Does not validate. */
    public static function normalise(string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $value));
    }

    /** Returns the normalised IBAN, or null when the value is not a valid one. */
    public static function parse(string $value): ?string
    {
        $candidate = self::normalise($value);

        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/', $candidate) !== 1) {
            return null;
        }

        return self::mod97($candidate) === 1 ? $candidate : null;
    }

    public static function isValid(string $value): bool
    {
        return self::parse($value) !== null;
    }

    /**
     * ISO 7064 mod-97-10: move the first four characters to the end, turn
     * letters into two-digit numbers, and take the remainder modulo 97 in
     * chunks so the number never exceeds native integer range.
     */
    private static function mod97(string $iban): int
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $digits = '';

        foreach (str_split($rearranged) as $character) {
            $digits .= ctype_alpha($character)
                ? (string) (ord($character) - 55)
                : $character;
        }

        $remainder = 0;

        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) ((string) $remainder.$chunk) % 97;
        }

        return $remainder;
    }
}
