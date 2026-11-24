<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\UBS;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\ColumnMap;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * The dates of a UBS account statement, and the rows they sit on.
 *
 * Every UBS account export prints three dates per row: the trade date (when
 * the client made the transaction), the booking date (when UBS booked it) and
 * the value date. The row's date is the booking date, as everywhere in this
 * package. A debit-card payment made on a Friday evening is booked on the
 * Monday: the booking date says Monday, the trade date Friday, and the value
 * date is backdated to the Friday.
 *
 * The current e-banking exports leave the booking date empty on a payment UBS
 * has not booked yet; such a row carries a trade time instead. The trade date
 * then stands in: it is the one date UBS fills on every row, and on every such
 * row in the published samples it equals the value date anyway. That fallback
 * is honoured because the profiles declare the booking-date term joinable.
 *
 * The trade date is also kept in {@see Row::$extras}, under its heading as
 * printed, so an application that wants it never has to dig through the raw
 * cells.
 */
trait StatementRows
{
    /**
     * The booking date, in every wording UBS has used.
     *
     * "Data di contabilizzazione" is the Italian booking date since about
     * 2024; "Data di registrazione" the wording before it.
     *
     * @var list<string>
     */
    private const array BOOKING_DATE_HEADINGS = [
        'Buchungsdatum', 'Date de comptabilisation', 'Data di registrazione',
        'Data di contabilizzazione', 'Booking date',
    ];

    /**
     * The trade date. "Abschluss", "Date de conclusion" and "Data di chiusura"
     * are the portfolio export's wordings, the others the e-banking exports'.
     *
     * @var list<string>
     */
    private const array TRADE_DATE_HEADINGS = [
        'Abschluss', 'Abschlussdatum', 'Date de conclusion', 'Date de transaction',
        "Date de l'opération", 'Data di chiusura', "Data dell'operazione", 'Trade date',
    ];

    /**
     * Booking date first, trade date only as a fallback.
     *
     * UBS prints the trade date in the earlier column, so reading left to right
     * would bind the row to the trade date whenever the two differ. The order
     * of this list is what puts the booking date first.
     *
     * @var list<string>
     */
    private const array ROW_DATE_HEADINGS = [...self::BOOKING_DATE_HEADINGS, ...self::TRADE_DATE_HEADINGS];

    public function extract(CsvDocument $document, ProfileMatch $match): ParsedFile
    {
        $file = parent::extract($this->rejoinSplitText($document, $match->columns), $match);
        $tradeDates = $this->tradeDateColumns($document, $match->columns);

        if ($tradeDates === []) {
            return $file;
        }

        return new ParsedFile(
            bank: $file->bank,
            profile: $file->profile,
            account: $file->account,
            period: $file->period,
            rows: array_map(fn (Row $row): Row => $this->withTradeDate($row, $tradeDates), $file->rows),
            metadata: $file->metadata,
            warnings: $file->warnings,
        );
    }

    /**
     * Puts back together a text cell that an unquoted delimiter split in two.
     *
     * The portfolio export does not quote its descriptions, and one that holds
     * " ; " comes out a cell too wide: every column after it shifts right, the
     * credit lands in the balance column, and the row reads as a line with no
     * amount and a wrong balance. A row wider than its heading is rejoined
     * where a delimiter has a space on either side, which is what such a split
     * looks like, and only when that accounts for every surplus cell. Any
     * other row is left exactly as it was.
     */
    private function rejoinSplitText(CsvDocument $document, ColumnMap $map): CsvDocument
    {
        $width = count($document->row($map->headerRow));
        $rows = [];
        $rejoined = false;

        foreach ($document->rows as $index => $cells) {
            $repaired = $index > $map->headerRow && count($cells) > $width
                ? $this->rejoin($cells, count($cells) - $width, $document->delimiter)
                : null;

            $rejoined = $rejoined || $repaired !== null;
            $rows[] = $repaired ?? $cells;
        }

        if (! $rejoined) {
            return $document;
        }

        return new CsvDocument(
            rows: $rows,
            delimiter: $document->delimiter,
            sourceEncoding: $document->sourceEncoding,
            metadata: $document->metadata,
            warnings: $document->warnings,
        );
    }

    /**
     * The row with its surplus cells merged back, or null when the surplus is
     * not explained by " ; " splits alone.
     *
     * @param  list<string>  $cells
     * @return list<string>|null
     */
    private function rejoin(array $cells, int $surplus, string $delimiter): ?array
    {
        $rejoined = [];

        foreach ($cells as $cell) {
            $previous = end($rejoined);

            if ($surplus > 0 && is_string($previous) && str_ends_with($previous, ' ') && str_starts_with($cell, ' ')) {
                array_pop($rejoined);
                $rejoined[] = $previous.$delimiter.$cell;
                $surplus--;

                continue;
            }

            $rejoined[] = $cell;
        }

        return $surplus === 0 ? $rejoined : null;
    }

    /**
     * The trade-date columns of the file, keyed by their heading as printed.
     *
     * @return array<string, int>
     */
    private function tradeDateColumns(CsvDocument $document, ColumnMap $map): array
    {
        $headings = $document->row($map->headerRow);
        $columns = [];

        foreach ($map->indexesOf(Term::BookingDate) as $column) {
            $heading = Text::normalise($headings[$column] ?? '');

            foreach (self::TRADE_DATE_HEADINGS as $label) {
                if (Text::equals($heading, $label)) {
                    $columns[$heading] = $column;
                }
            }
        }

        return $columns;
    }

    /** @param array<string, int> $columns */
    private function withTradeDate(Row $row, array $columns): Row
    {
        $extras = $row->extras;

        foreach ($columns as $heading => $column) {
            $value = trim($row->raw[$column] ?? '');

            if ($value !== '') {
                $extras[$heading] ??= $value;
            }
        }

        return new Row(
            date: $row->date,
            valueDate: $row->valueDate,
            label: $row->label,
            amount: $row->amount,
            currency: $row->currency,
            balance: $row->balance,
            reference: $row->reference,
            extras: $extras,
            raw: $row->raw,
            line: $row->line,
        );
    }
}
