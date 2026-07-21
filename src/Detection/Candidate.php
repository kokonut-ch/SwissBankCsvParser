<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Detection;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;

/** One profile that recognised the file, and how sure it is. */
final readonly class Candidate
{
    /**
     * @param  string  $profile  Profile id, e.g. "postfinance.efinance".
     * @param  float  $score  0 to 1.
     * @param  list<string>  $reasons  Why it matched, in plain English, for showing to a human.
     */
    public function __construct(
        public BankIdentity $bank,
        public string $profile,
        public float $score,
        public array $reasons,
    ) {}
}
