<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Dto;

use DateTimeImmutable;

/**
 * One line of the statement.
 *
 * Amounts are exact decimal strings, never floats: a parser has no business
 * losing precision on money. Use them as-is, or feed them to whatever decimal
 * type your application already has.
 */
final readonly class Row
{
    public function __construct(
        /** The date the bank booked the line. Always present: a row without a date is not a row. */
        public DateTimeImmutable $date,
        /** Value date when the file has a separate column for it. */
        public ?DateTimeImmutable $valueDate,
        /** The description, whitespace-collapsed but otherwise untouched. */
        public string $label,
        /**
         * Signed decimal string, e.g. "-150.50" or "1200.00".
         *
         * Negative means money left the account. Null means the line carries no
         * amount at all — balance-only lines do exist, and dropping them is a
         * decision for the caller, not for the parser.
         */
        public ?string $amount,
        /** ISO 4217 code when the row or its column heading names one. */
        public ?string $currency,
        /** Running balance after the line, when the file has such a column. */
        public ?string $balance,
        /** Whatever the bank calls a reference, when it prints one. */
        public ?string $reference,
        /**
         * Columns that were recognised but do not belong to the neutral model,
         * keyed by the heading as printed. Categories, tags, card numbers, and
         * so on.
         *
         * @var array<string, string>
         */
        public array $extras,
        /**
         * The original cells, untouched. Always here so nothing this package
         * failed to model is lost to the caller.
         *
         * @var list<string>
         */
        public array $raw,
        /** 1-based line number in the source file, for error reporting. */
        public int $line,
    ) {}

    /**
     * A copy with a different label.
     *
     * Used when a bank continues one booking over several physical lines: the
     * lines that follow carry no date and no amount, only more text, and belong
     * to the row above.
     */
    public function withLabel(string $label): self
    {
        return new self(
            date: $this->date,
            valueDate: $this->valueDate,
            label: $label,
            amount: $this->amount,
            currency: $this->currency,
            balance: $this->balance,
            reference: $this->reference,
            extras: $this->extras,
            raw: $this->raw,
            line: $this->line,
        );
    }

    public function isCredit(): bool
    {
        return $this->amount !== null && ! str_starts_with($this->amount, '-');
    }

    public function isDebit(): bool
    {
        return $this->amount !== null && str_starts_with($this->amount, '-');
    }
}
