<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Generic;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Detection\DetectionReport;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\ColumnMap;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * The four-column account statement that several Swiss banks print
 * identically, preamble included:
 *
 *     Kontoauszug bis: 30.09.2026 ;;;
 *     Kontonummer: 123.456.78;;;
 *     Bezeichnung: Privatkonto;;;
 *     Saldo: CHF 12345.60;;;
 *     <holder and address lines>
 *     Datum;Buchungstext;Betrag;Valuta
 *     30.09.26;Zahlungseingang Beispiel AG;1838.00;30.09.26
 *
 * Migros Bank in its older layout, Schaffhauser KB, Valiant, acrevis, Schwyzer
 * KB, Nidwaldner KB, Zuger KB and Clientis Bank Aareland all ship it, and
 * nothing in the file says which of them produced it. The generic reader takes
 * it in free detection.
 *
 * A bank profile may still read it, so that a caller who already knows the
 * bank can, but must never claim it: {@see cap()} holds such a match below the
 * generic reader, and the file is read as that bank only when the caller
 * restricts the parser to it.
 *
 * What lives here is what every such profile needs: the headings, the preamble
 * labels, the date notations and the ceiling. A profile that reads other
 * layouts as well, as Migros Bank's does, adds its own labels to these.
 */
final class FourColumnStatement
{
    /**
     * The ceiling of a match on this layout. Below the generic reader's 0.30
     * on purpose, so it can never win a free detection, and far below
     * {@see DetectionReport::isConfident()}.
     */
    public const float SCORE = 0.25;

    /**
     * Rows are dated `dd.mm.yy` by most of these banks, `dd.mm.yyyy` by
     * Nidwaldner KB, and the preamble writes `dd.mm.yyyy`. The four-digit
     * notation comes first, or 30.09.2026 would be read as the year 20.
     *
     * @var list<string>
     */
    public const array DATE_FORMATS = ['d.m.Y', 'd.m.y'];

    /**
     * The layout's headings, by term. `Saldo` is optional: Clientis Bank
     * Aareland's 2024 sample prints a running balance between the amount and
     * the value date, the other samples do not.
     *
     * @return array<string, list<string>> Term value => accepted headings
     */
    public static function termLabels(): array
    {
        return [
            Term::BookingDate->value => ['Datum'],
            Term::Description->value => ['Buchungstext'],
            Term::Amount->value => ['Betrag'],
            // The value date, as everywhere in German.
            Term::ValueDate->value => ['Valuta'],
            Term::Balance->value => ['Saldo'],
        ];
    }

    /**
     * No holder on purpose. `Bezeichnung` names the kind of account
     * ("Privatkonto", "Kontokorrent BUSINESS CHF"), never the person who holds
     * it, and stays in the metadata. The holder's name is on the unlabelled
     * address lines, which are not read.
     */
    public static function headerBlock(): HeaderBlock
    {
        return new HeaderBlock(
            account: ['Kontonummer'],
            // "Saldo: CHF 12345.60": the ISO code is picked out of the value.
            currency: ['Saldo'],
            to: ['Kontoauszug bis'],
        );
    }

    /**
     * Whether the heading row holds this layout's headings and nothing else.
     *
     * For a profile that reads this layout alone: it leaves the richer exports
     * that begin with the same columns to the profiles that know them, such as
     * Migros Bank's current layout,
     * `Datum;Buchungstext;Mitteilung;Referenznummer;Betrag;Saldo;Valuta`.
     */
    public static function isLayout(CsvDocument $document, ColumnMap $columns): bool
    {
        $headings = array_merge(...array_values(self::termLabels()));

        foreach ($document->row($columns->headerRow) as $cell) {
            if (Text::normalise($cell) === '') {
                continue;
            }

            $known = array_filter($headings, static fn (string $heading): bool => Text::equals($cell, $heading));

            if ($known === []) {
                return false;
            }
        }

        return true;
    }

    /**
     * A match on this layout, capped at {@see SCORE}, with the reason.
     *
     * @param  string  $sharedWith  The other banks known to print it, as a phrase:
     *                              "Valiant, acrevis and Schwyzer KB".
     */
    public static function cap(ProfileMatch $match, string $sharedWith): ProfileMatch
    {
        return new ProfileMatch(
            min($match->score, self::SCORE),
            [...$match->reasons, 'four-column layout shared with '.$sharedWith.': the bank is not named'],
            $match->columns,
        );
    }
}
