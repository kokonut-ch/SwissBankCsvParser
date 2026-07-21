<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Value\DateParser;

/**
 * For older exports that carry no column headings, where the only thing
 * identifying a column is its position.
 *
 * Recognition is necessarily weaker than {@see HeaderDrivenProfile}: with no
 * headings there is nothing that names the bank, so these profiles top out at a
 * modest confidence and any header-driven profile that also matches will win.
 * Use one only when a bank genuinely ships a headerless format.
 */
abstract class PositionalProfile extends Profile
{
    /**
     * Shape recognised. Deliberately below the ceiling of the generic
     * header-driven fallback: a guess made from column positions is worth less
     * than one made from column names, so any file that names its columns is
     * better served by the generic reader than by a bank claiming it on layout
     * alone.
     */
    private const float SCORE_BASE = 0.20;

    private const float SCORE_PER_ROW = 0.01;

    private const float SCORE_ROWS_CAP = 0.08;

    /** How many recognisable headings in one row before a file counts as headed. */
    private const int HEADING_ROW_THRESHOLD = 3;

    /**
     * Which column holds what, by index.
     *
     * @return array<string, int> Term value => zero-based column index
     */
    abstract protected function positions(): array;

    /**
     * Row widths this format is allowed to have. Empty means any width, which
     * is the safer default: banks add trailing columns without warning, and a
     * strict width is the single most common reason a legacy profile silently
     * stops matching.
     *
     * @return list<int>
     */
    protected function columnCounts(): array
    {
        return [];
    }

    /** How many readable rows before the file is accepted as this format. */
    protected function minimumRows(): int
    {
        return 1;
    }

    public function match(CsvDocument $document): ?ProfileMatch
    {
        // A headerless profile has no business claiming a file that has
        // headings. Without this, any four-column CSV starting with a date
        // would be attributed to whichever bank happens to ship a legacy
        // layout — a confident-sounding wrong answer, which is worse than
        // falling through to the generic reader.
        if ($this->hasHeadingRow($document)) {
            return null;
        }

        $map = new ColumnMap($this->positions());
        $dateIndex = $map->indexOf(Term::BookingDate);

        if ($dateIndex === null) {
            return null;
        }

        $widths = $this->columnCounts();
        $formats = $this->dateFormats();
        $matched = 0;

        foreach ($document->rows as $cells) {
            if ($widths !== [] && ! in_array(count($cells), $widths, true)) {
                continue;
            }

            if (DateParser::parse($cells[$dateIndex] ?? '', $formats) === null) {
                continue;
            }

            // A date on its own is not enough: header blocks contain dates too.
            // Requiring a readable amount on the same row is what separates a
            // statement line from a "period from … to …" line.
            if ($this->rowAmount($cells, $map) === null) {
                continue;
            }

            $matched++;
        }

        if ($matched < $this->minimumRows()) {
            return null;
        }

        return new ProfileMatch(
            score: self::SCORE_BASE + min($matched * self::SCORE_PER_ROW, self::SCORE_ROWS_CAP),
            reasons: [$matched.' row(s) match the expected column positions'],
            columns: $map,
        );
    }

    /** True when some row names enough columns to be a heading row. */
    private function hasHeadingRow(CsvDocument $document): bool
    {
        foreach ($document->rows as $row) {
            $found = [];

            foreach ($row as $cell) {
                $heading = Text::normalise($cell);

                if ($heading === '') {
                    continue;
                }

                foreach (Term::cases() as $term) {
                    if (Lexicon::matches($heading, $term)) {
                        $found[$term->value] = true;

                        break;
                    }
                }
            }

            if (count($found) >= self::HEADING_ROW_THRESHOLD) {
                return true;
            }
        }

        return false;
    }
}
