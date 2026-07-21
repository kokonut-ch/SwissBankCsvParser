<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

use Kokonut\SwissBankCsvParser\Lexicon\Term;

/** Which column holds what, once a profile has recognised a file. */
final readonly class ColumnMap
{
    /**
     * @param  array<string, int>  $terms  Term value => zero-based column index.
     * @param  array<string, int>  $extras  Heading as printed => column index, for recognised
     *                                      columns that are not part of the neutral row model.
     * @param  string|null  $currency  ISO code read off an amount heading, e.g. "Crédit en CHF".
     * @param  int  $headerRow  Row index of the heading line, or -1 when the file has none.
     */
    public function __construct(
        public array $terms,
        public array $extras = [],
        public ?string $currency = null,
        public int $headerRow = -1,
    ) {}

    public function indexOf(Term $term): ?int
    {
        return $this->terms[$term->value] ?? null;
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
