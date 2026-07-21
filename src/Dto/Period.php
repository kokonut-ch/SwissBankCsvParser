<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Dto;

use DateTimeImmutable;

/**
 * The period the file claims to cover, taken from its header block.
 *
 * This is what the bank announced, which is not necessarily the range actually
 * covered by the rows. Callers that need the real range should look at the
 * rows.
 */
final readonly class Period
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->from === null && $this->to === null;
    }
}
