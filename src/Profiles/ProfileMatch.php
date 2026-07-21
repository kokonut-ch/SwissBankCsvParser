<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

/**
 * A profile's answer to "is this your file?".
 *
 * The score is a confidence between 0 and 1, not a verdict: several profiles
 * may recognise the same file, and the caller is entitled to see the runners-up
 * and to overrule the winner.
 */
final readonly class ProfileMatch
{
    /**
     * @param  float  $score  0 to 1.
     * @param  list<string>  $reasons  Plain English, so a UI can explain the choice.
     */
    public function __construct(
        public float $score,
        public array $reasons,
        public ColumnMap $columns,
    ) {}
}
