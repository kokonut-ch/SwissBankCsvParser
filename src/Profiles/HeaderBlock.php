<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

/**
 * Where a given bank puts account details in its "Label: value" preamble.
 *
 * Every list is a set of candidate labels in whatever languages the bank ships;
 * the first one present wins.
 */
final readonly class HeaderBlock
{
    /**
     * @param  list<string>  $account  IBAN or account/card number.
     * @param  list<string>  $currency
     * @param  list<string>  $holder
     * @param  list<string>  $from  Start of the announced period.
     * @param  list<string>  $to  End of the announced period.
     */
    public function __construct(
        public array $account = [],
        public array $currency = [],
        public array $holder = [],
        public array $from = [],
        public array $to = [],
    ) {}
}
