<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

use Kokonut\SwissBankCsvParser\Lexicon\Term;

/** Which column holds what, once a profile has recognised a file. */
final readonly class ColumnMap
{
    /**
     * A term can map to more than one column: UBS spreads a description over
     * "Description 1/2/3", ZKB over "Buchungstext", "Zahlungszweck" and
     * "Details". Terms are stored as lists for that reason, and
     * {@see indexOf()} returns the first for the terms that only ever have one.
     *
     * @var array<string, list<int>>
     */
    public array $terms;

    /**
     * @param  array<string, int|list<int>>  $terms  Term value => column index, or indexes.
     * @param  array<string, int>  $extras  Heading as printed => column index, for recognised
     *                                      columns that are not part of the neutral row model.
     * @param  string|null  $currency  ISO code read off an amount heading, e.g. "Crédit en CHF".
     * @param  int  $headerRow  Row index of the heading line, or -1 when the file has none.
     */
    public function __construct(
        array $terms,
        public array $extras = [],
        public ?string $currency = null,
        public int $headerRow = -1,
    ) {
        $normalised = [];

        foreach ($terms as $term => $columns) {
            $normalised[$term] = is_int($columns) ? [$columns] : $columns;
        }

        $this->terms = $normalised;
    }

    public function indexOf(Term $term): ?int
    {
        return $this->terms[$term->value][0] ?? null;
    }

    /** @return list<int> */
    public function indexesOf(Term $term): array
    {
        return $this->terms[$term->value] ?? [];
    }

    public function has(Term $term): bool
    {
        return isset($this->terms[$term->value]);
    }

    /** @param list<Term> $terms */
    public function hasAll(array $terms): bool
    {
        foreach ($terms as $term) {
            if (! $this->has($term)) {
                return false;
            }
        }

        return true;
    }

    public function count(): int
    {
        return count($this->terms);
    }
}
