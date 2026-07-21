<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Csv;

use Kokonut\SwissBankCsvParser\Dto\Warning;

/**
 * A CSV file after decoding and tokenising, before any bank knowledge is
 * applied. Profiles read this; they never touch the raw string.
 */
final readonly class CsvDocument
{
    /**
     * @param  list<list<string>>  $rows  Every row, in file order, headings and footers included.
     * @param  array<string, string>  $metadata  The "Label: value" block, keys without the colon.
     * @param  list<Warning>  $warnings
     */
    public function __construct(
        public array $rows,
        public string $delimiter,
        public string $sourceEncoding,
        public array $metadata = [],
        public array $warnings = [],
    ) {}

    /** @return list<string> */
    public function row(int $index): array
    {
        return $this->rows[$index] ?? [];
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * First metadata value whose label matches one of the candidates, compared
     * loosely: case, surrounding whitespace and curly apostrophes are ignored.
     *
     * @param  list<string>  $labels
     */
    public function metadataValue(array $labels): ?string
    {
        foreach ($this->metadata as $key => $value) {
            foreach ($labels as $candidate) {
                if (Text::equals($key, $candidate)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /** @param list<string> $labels */
    public function hasMetadata(array $labels): bool
    {
        return $this->metadataValue($labels) !== null;
    }
}
