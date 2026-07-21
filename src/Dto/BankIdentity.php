<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Dto;

/**
 * Who exported the file. Purely descriptive: the parser never behaves
 * differently because of what is in here.
 */
final readonly class BankIdentity
{
    public function __construct(
        /** Stable machine key, e.g. "postfinance". Never translated, never renamed. */
        public string $key,
        /** Human name as the bank writes it, e.g. "PostFinance". */
        public string $name,
        /** ISO 3166-1 alpha-2, lowercase. */
        public string $country = 'ch',
    ) {}
}
