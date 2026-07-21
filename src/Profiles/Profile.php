<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

use DateTimeImmutable;
use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Dto\Account;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Dto\Period;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Value\Amount;
use Kokonut\SwissBankCsvParser\Value\DateParser;
use Kokonut\SwissBankCsvParser\Value\Iban;

/**
 * Shared extraction. Once a profile has said which column is which, turning
 * rows into {@see Row} objects is the same work for every bank.
 *
 * Subclasses differ only in how they recognise the file:
 * {@see HeaderDrivenProfile} reads column headings, {@see PositionalProfile}
 * counts columns.
 */
abstract class Profile implements BankProfile
{
    public function priority(): int
    {
        return 100;
    }

    /**
     * Which date notations this bank uses. Narrowing the list makes detection
     * sharper, because a date that does not fit stops a row from counting.
     *
     * @return list<string>
     */
    protected function dateFormats(): array
    {
        return DateParser::COMMON;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::SplitColumns;
    }

    /** Where this bank puts account details in its "Label: value" preamble. */
    protected function headerBlock(): HeaderBlock
    {
        return new HeaderBlock;
    }

    /**
     * Whether a line with no date and no amount, carrying only text, continues
     * the line above it.
     *
     * On by default: ZKB, Raiffeisen and others routinely spill one booking
     * over several physical lines, and dropping those lines loses the half of
     * the description that actually names the counterparty.
     */
    protected function allowsContinuationRows(): bool
    {
        return true;
    }

    /** What joins the pieces of a description spread over several columns or lines. */
    protected function descriptionSeparator(): string
    {
        return ' ';
    }

    public function extract(CsvDocument $document, ProfileMatch $match): ParsedFile
    {
        $map = $match->columns;
        $warnings = $document->warnings;

        $fileCurrency = $map->currency ?? $this->currencyFromHeaderBlock($document);

        $rows = $this->buildRows($document, $map, $fileCurrency, $warnings);

        if ($fileCurrency === null && ! $map->has(Term::Currency)) {
            $warnings[] = new Warning(
                Warning::CURRENCY_NOT_DETECTED,
                'The file names no currency, neither in a column heading nor in its header block.',
            );
        }

        return new ParsedFile(
            bank: $this->identity(),
            profile: $this->id(),
            account: $this->buildAccount($document, $map, $fileCurrency),
            period: $this->buildPeriod($document),
            rows: $rows,
            metadata: $document->metadata,
            warnings: $warnings,
        );
    }

    /**
     * A row is a row when its date column holds a real date. That single test
     * is what discards preambles, headings, blank separators, subtotals and the
     * legal footer, without any of them having to be described.
     *
     * Lines that hold text but neither a date nor an amount are folded into the
     * row above, when the profile allows it.
     *
     * @param  list<Warning>  $warnings
     * @return list<Row>
     */
    private function buildRows(CsvDocument $document, ColumnMap $map, ?string $fileCurrency, array &$warnings): array
    {
        if (! $map->has(Term::BookingDate)) {
            return [];
        }

        $formats = $this->dateFormats();
        $rows = [];

        foreach ($document->rows as $index => $cells) {
            if ($index === $map->headerRow) {
                continue;
            }

            $date = $this->rowDate($cells, $map, $formats);
            $label = $this->description($cells, $map);

            if ($date === null) {
                if ($rows !== []) {
                    // Popped and pushed rather than assigned by index: writing to
                    // $rows[$last] would leave an array that is a list in fact but
                    // not in type, which static analysis is right to object to.
                    $previous = array_pop($rows);
                    $rows[] = $this->continuation($previous, $cells, $map, $label) ?? $previous;
                }

                continue;
            }

            $rowCurrency = $this->firstNonEmpty($cells, $map, Term::Currency);
            $amount = $this->rowAmount($cells, $map);
            $formula = $amount === null ? $this->formulaAmount($cells, $map) : null;

            if ($formula !== null) {
                // Zero rather than null, so the row can never be mistaken for a
                // balance line, and never carries a number nobody wrote. The
                // original text travels with the warning.
                $amount = '0';
                $warnings[] = new Warning(
                    Warning::AMOUNT_IS_FORMULA,
                    'An amount cell held a spreadsheet formula and was read as zero.',
                    ['line' => $index + 1, 'value' => $formula],
                );
            }

            $rows[] = new Row(
                date: $date,
                valueDate: $this->cellDate($cells, $map, Term::ValueDate, $formats),
                label: $label,
                amount: $amount,
                currency: $rowCurrency === null ? $fileCurrency : strtoupper($rowCurrency),
                balance: Amount::parse($this->cell($cells, $map, Term::Balance)),
                reference: $this->nullIfBlank($this->cell($cells, $map, Term::Reference)),
                extras: $this->extras($cells, $map),
                raw: $cells,
                line: $index + 1,
            );
        }

        return $rows;
    }

    /**
     * The row's date, taken from the first booking-date column that actually
     * holds one.
     *
     * A bank can print more than one: UBS gives a trade date and a booking date,
     * and leaves the booking date empty on some rows. The profile declares which
     * to prefer, and the first that parses wins — reading them in column order
     * instead would silently prefer whichever the bank happened to print first.
     *
     * @param  list<string>  $cells
     * @param  list<string>  $formats
     */
    private function rowDate(array $cells, ColumnMap $map, array $formats): ?DateTimeImmutable
    {
        foreach ($map->indexesOf(Term::BookingDate) as $column) {
            $date = DateParser::parse($cells[$column] ?? '', $formats);

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    /**
     * The previous row with this line's text appended — or null when this line
     * is not a continuation of it.
     *
     * The conditions are deliberately tight: text but no date, no amount and no
     * balance. A line carrying any figure is a row of its own, however odd, and
     * must never be silently merged into its neighbour.
     *
     * @param  list<string>  $cells
     */
    private function continuation(Row $previous, array $cells, ColumnMap $map, string $label): ?Row
    {
        if (! $this->allowsContinuationRows() || $label === '') {
            return null;
        }

        if ($this->rowAmount($cells, $map) !== null || $this->formulaAmount($cells, $map) !== null) {
            return null;
        }

        if (Amount::parse($this->cell($cells, $map, Term::Balance)) !== null) {
            return null;
        }

        return $previous->withLabel(
            Text::normalise($previous->label.$this->descriptionSeparator().$label),
        );
    }

    /**
     * The description, joined across every column mapped to it: UBS spreads one
     * over "Description 1/2/3", ZKB over "Buchungstext" and "Zahlungszweck".
     * Empty pieces are skipped so the separator never doubles up.
     *
     * @param  list<string>  $cells
     */
    private function description(array $cells, ColumnMap $map): string
    {
        $pieces = [];

        foreach ($map->indexesOf(Term::Description) as $column) {
            $piece = Text::normalise($cells[$column] ?? '');

            if ($piece !== '') {
                $pieces[] = $piece;
            }
        }

        return implode($this->descriptionSeparator(), $pieces);
    }

    /**
     * With split columns the sign printed in the cell is discarded: the column
     * the value sits in decides the direction. Banks are inconsistent about
     * printing debits as negatives, and the column never lies.
     *
     * @param  list<string>  $cells
     */
    protected function rowAmount(array $cells, ColumnMap $map): ?string
    {
        if ($this->amountModel() === AmountModel::SignedColumn) {
            return Amount::parse($this->cell($cells, $map, Term::Amount));
        }

        if ($this->amountModel() === AmountModel::InvertedSignedColumn) {
            $amount = Amount::parse($this->cell($cells, $map, Term::Amount));

            return $amount === null ? null : Amount::negate($amount);
        }

        $credit = Amount::parse($this->cell($cells, $map, Term::Credit));

        if ($credit !== null) {
            return Amount::abs($credit);
        }

        $debit = Amount::parse($this->cell($cells, $map, Term::Debit));

        return $debit !== null ? Amount::negate(Amount::abs($debit)) : null;
    }

    /**
     * The formula found in one of this row's amount columns, if any.
     *
     * Returns the offending text rather than a boolean: a caller told that a
     * value was rejected will want to know what it said.
     *
     * @param  list<string>  $cells
     */
    private function formulaAmount(array $cells, ColumnMap $map): ?string
    {
        $columns = $this->amountModel() === AmountModel::SplitColumns
            ? [Term::Credit, Term::Debit]
            : [Term::Amount];

        foreach ($columns as $term) {
            $value = $this->cell($cells, $map, $term);

            if (Amount::looksLikeFormula($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $cells
     * @return array<string, string>
     */
    private function extras(array $cells, ColumnMap $map): array
    {
        $extras = [];

        foreach ($map->extras as $heading => $index) {
            $value = trim($cells[$index] ?? '');

            if ($value !== '') {
                $extras[$heading] = $value;
            }
        }

        return $extras;
    }

    /**
     * First column mapped to this term that actually holds something.
     *
     * Yuh prints a currency beside the debit column and another beside the
     * credit column, and fills only the one that applies to the row.
     *
     * @param  list<string>  $cells
     */
    private function firstNonEmpty(array $cells, ColumnMap $map, Term $term): ?string
    {
        foreach ($map->indexesOf($term) as $column) {
            $value = $this->nullIfBlank($cells[$column] ?? null);

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /** @param list<string> $cells */
    private function cell(array $cells, ColumnMap $map, Term $term): ?string
    {
        $index = $map->indexOf($term);

        return $index === null ? null : ($cells[$index] ?? null);
    }

    /**
     * @param  list<string>  $cells
     * @param  list<string>  $formats
     */
    private function cellDate(array $cells, ColumnMap $map, Term $term, array $formats): ?DateTimeImmutable
    {
        $value = $this->cell($cells, $map, $term);

        return $value === null ? null : DateParser::parse($value, $formats);
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? '' : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * The ISO code is picked out of the header-block value rather than taken
     * whole: banks write "CHF" on a "Currency:" line but "CHF 38547.70" on a
     * "Balance:" line, and both are worth reading.
     */
    private function currencyFromHeaderBlock(CsvDocument $document): ?string
    {
        $labels = $this->headerBlock()->currency;

        if ($labels === []) {
            return null;
        }

        $value = $document->metadataValue($labels);

        if ($value === null) {
            return null;
        }

        return preg_match('/\b([A-Za-z]{3})\b/', $value, $matches) === 1
            ? strtoupper($matches[1])
            : null;
    }

    private function buildAccount(CsvDocument $document, ColumnMap $map, ?string $currency): Account
    {
        $block = $this->headerBlock();
        $raw = $block->account === [] ? null : $document->metadataValue($block->account);

        // The header block is the more authoritative source when there is one;
        // banks that print no preamble at all, such as Raiffeisen, repeat the
        // IBAN on every row instead.
        $iban = ($raw === null ? null : Iban::parse($raw)) ?? $this->ibanFromColumn($document, $map);

        return new Account(
            iban: $iban,
            // Only keep a plain number when it is not the IBAN we already have,
            // so a card account still reports something and an IBAN account does
            // not report the same value twice.
            number: $iban === null ? $this->nullIfBlank($raw) : null,
            currency: $currency,
            holder: $block->holder === [] ? null : $this->nullIfBlank($document->metadataValue($block->holder)),
        );
    }

    /** First valid IBAN in the column mapped to {@see Term::AccountIban}, if any. */
    private function ibanFromColumn(CsvDocument $document, ColumnMap $map): ?string
    {
        $index = $map->indexOf(Term::AccountIban);

        if ($index === null) {
            return null;
        }

        foreach ($document->rows as $cells) {
            $iban = Iban::parse($cells[$index] ?? '');

            if ($iban !== null) {
                return $iban;
            }
        }

        return null;
    }

    private function buildPeriod(CsvDocument $document): Period
    {
        $block = $this->headerBlock();
        $formats = $this->dateFormats();

        $from = $block->from === [] ? null : $document->metadataValue($block->from);
        $to = $block->to === [] ? null : $document->metadataValue($block->to);

        return new Period(
            from: $from === null ? null : DateParser::parse($from, $formats),
            to: $to === null ? null : DateParser::parse($to, $formats),
        );
    }
}
