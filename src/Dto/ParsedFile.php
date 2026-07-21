<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Dto;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * Everything this package has to say about one CSV file.
 *
 * @implements IteratorAggregate<int, Row>
 */
final readonly class ParsedFile implements Countable, IteratorAggregate
{
    public function __construct(
        public BankIdentity $bank,
        /** Profile that read the file, e.g. "postfinance.efinance". */
        public string $profile,
        public Account $account,
        public Period $period,
        /**
         * Rows in the order the file listed them. Deliberately not sorted:
         * reordering is a decision, and this package does not make decisions.
         *
         * @var list<Row>
         */
        public array $rows,
        /**
         * The "Label: value" header block, as printed, in file order. Whatever
         * the bank put there that this package did not model is still here.
         *
         * @var array<string, string>
         */
        public array $metadata = [],
        /** @var list<Warning> */
        public array $warnings = [],
    ) {}

    public function count(): int
    {
        return count($this->rows);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->rows);
    }

    /** @return list<Warning> */
    public function warningsOf(string $code): array
    {
        return array_values(array_filter($this->warnings, fn (Warning $w) => $w->code === $code));
    }

    public function hasWarning(string $code): bool
    {
        return $this->warningsOf($code) !== [];
    }

    /**
     * A copy carrying extra warnings, for profiles that have something to add
     * once extraction is done.
     *
     * @param  list<Warning>  $warnings
     */
    public function withWarnings(array $warnings): self
    {
        return new self(
            bank: $this->bank,
            profile: $this->profile,
            account: $this->account,
            period: $this->period,
            rows: $this->rows,
            metadata: $this->metadata,
            warnings: [...$this->warnings, ...$warnings],
        );
    }
}
