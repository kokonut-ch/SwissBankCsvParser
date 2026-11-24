<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\PostFinance;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Detection\DetectionReport;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\ColumnMap;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\PositionalProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;
use Kokonut\SwissBankCsvParser\Value\Amount;
use Kokonut\SwissBankCsvParser\Value\DateParser;

/**
 * The older PostFinance statement, which has no heading row at all:
 *
 *     date ; description ; credit ; debit ; value date ; balance
 *
 * With nothing naming the bank, this profile cannot be certain, and it does not
 * pretend to be — it scores low enough that any bank that does name itself wins,
 * and low enough that {@see DetectionReport::isConfident()}
 * stays false. Treat a match here as a suggestion to confirm with the user, not
 * as an identification.
 *
 * Widths are pinned to 4–7 columns to keep it from claiming every headerless
 * CSV that happens to start with a date.
 *
 * Positions are a claim about every row, so every dated row is held to it. A
 * file whose cells contradict the layout (a date where the debit should be, a
 * figure where the value date should be, money in both directions on one
 * line) is refused outright rather than read. PostFinance's 2010 export is
 * the case in point: its English headings are not in the shared vocabulary, so
 * it passes for a headerless file, and read by position its `Details` flag
 * lands in the credit column and every row comes back as 2.00.
 */
final class LegacyStatementProfile extends PositionalProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('postfinance', 'PostFinance');
    }

    public function id(): string
    {
        return 'postfinance.legacy';
    }

    public function priority(): int
    {
        return 90;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y'];
    }

    protected function positions(): array
    {
        return [
            Term::BookingDate->value => 0,
            Term::Description->value => 1,
            Term::Credit->value => 2,
            Term::Debit->value => 3,
            Term::ValueDate->value => 4,
            Term::Balance->value => 5,
        ];
    }

    protected function columnCounts(): array
    {
        return [4, 6, 7];
    }

    protected function headerBlock(): HeaderBlock
    {
        return new HeaderBlock(
            account: ['Compte', 'Konto', 'Conto', 'Account'],
            currency: ['Monnaie', 'Währung', 'Moneta', 'Currency'],
        );
    }

    public function match(CsvDocument $document): ?ProfileMatch
    {
        $match = parent::match($document);

        if ($match === null) {
            return null;
        }

        $formats = $this->dateFormats();

        foreach ($document->rows as $cells) {
            if (DateParser::parse($this->cell($cells, $match->columns, Term::BookingDate), $formats) === null) {
                continue;
            }

            if ($this->contradictsLayout($cells, $match->columns, $formats)) {
                return null;
            }
        }

        return $match;
    }

    /**
     * Whether a dated row holds something its positions say it cannot: text or
     * a date in an amount column, anything but a date in the value-date column,
     * or both a credit and a debit.
     *
     * A cell that is absent or empty contradicts nothing: the unused half of the
     * credit/debit pair is empty on every row, and an opening balance line has
     * neither.
     *
     * @param  list<string>  $cells
     * @param  list<string>  $formats
     */
    private function contradictsLayout(array $cells, ColumnMap $map, array $formats): bool
    {
        $credit = $this->cell($cells, $map, Term::Credit);
        $debit = $this->cell($cells, $map, Term::Debit);

        if ($credit !== '' && $debit !== '') {
            return true;
        }

        foreach ([$credit, $debit, $this->cell($cells, $map, Term::Balance)] as $value) {
            if ($value !== '' && Amount::parse($value) === null) {
                return true;
            }
        }

        $valueDate = $this->cell($cells, $map, Term::ValueDate);

        return $valueDate !== '' && DateParser::parse($valueDate, $formats) === null;
    }

    /**
     * The trimmed cell at this term's position, or an empty string when the
     * row is too short to have one.
     *
     * @param  list<string>  $cells
     */
    private function cell(array $cells, ColumnMap $map, Term $term): string
    {
        $index = $map->indexOf($term);

        return $index === null ? '' : trim($cells[$index] ?? '');
    }
}
