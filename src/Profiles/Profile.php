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

    public function extract(CsvDocument $document, ProfileMatch $match): ParsedFile
    {
        $map = $match->columns;
        $warnings = $document->warnings;

        $fileCurrency = $map->currency
            ?? $this->currencyFromHeaderBlock($document);

        $rows = $this->buildRows($document, $map, $fileCurrency);

        if ($fileCurrency === null && ! $map->has(Term::Currency)) {
            $warnings[] = new Warning(
                Warning::CURRENCY_NOT_DETECTED,
                'The file names no currency, neither in a column heading nor in its header block.',
            );
        }

        return new ParsedFile(
            bank: $this->identity(),
            profile: $this->id(),
            account: $this->buildAccount($document, $fileCurrency),
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
     * @return list<Row>
     */
    private function buildRows(CsvDocument $document, ColumnMap $map, ?string $fileCurrency): array
    {
        $dateIndex = $map->indexOf(Term::BookingDate);

        if ($dateIndex === null) {
            return [];
        }

        $formats = $this->dateFormats();
        $rows = [];

        foreach ($document->rows as $index => $cells) {
            if ($index === $map->headerRow) {
                continue;
            }

            $date = DateParser::parse($cells[$dateIndex] ?? '', $formats);

            if ($date === null) {
                continue;
            }

            $rowCurrency = $this->nullIfBlank($this->cell($cells, $map, Term::Currency));

            $rows[] = new Row(
                date: $date,
                valueDate: $this->cellDate($cells, $map, Term::ValueDate, $formats),
                label: Text::normalise($this->cell($cells, $map, Term::Description) ?? ''),
                amount: $this->rowAmount($cells, $map),
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

        $credit = Amount::parse($this->cell($cells, $map, Term::Credit));

        if ($credit !== null) {
            return Amount::abs($credit);
        }

        $debit = Amount::parse($this->cell($cells, $map, Term::Debit));

        return $debit !== null ? Amount::negate(Amount::abs($debit)) : null;
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

    private function currencyFromHeaderBlock(CsvDocument $document): ?string
    {
        $labels = $this->headerBlock()->currency;

        if ($labels === []) {
            return null;
        }

        $value = $document->metadataValue($labels);

        return $value === null ? null : strtoupper(trim($value));
    }

    private function buildAccount(CsvDocument $document, ?string $currency): Account
    {
        $block = $this->headerBlock();
        $raw = $block->account === [] ? null : $document->metadataValue($block->account);
        $iban = $raw === null ? null : Iban::parse($raw);

        return new Account(
            iban: $iban,
            // Only keep a plain number when it is not the IBAN we already have,
            // so a card account still reports something and an IBAN account
            // does not report the same value twice.
            number: $iban === null ? $this->nullIfBlank($raw) : null,
            currency: $currency,
            holder: $block->holder === [] ? null : $this->nullIfBlank($document->metadataValue($block->holder)),
        );
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
