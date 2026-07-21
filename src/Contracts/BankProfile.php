<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Contracts;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Profiles\PositionalProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * One recognisable export format of one bank.
 *
 * Banks change their exports and keep the old ones working, so a bank usually
 * has several profiles. Each lives in that bank's directory under banks/, which
 * is what keeps adding a bank down to a single new folder.
 *
 * Rather than implementing this directly, extend
 * {@see HeaderDrivenProfile} for modern
 * exports with column headings, or
 * {@see PositionalProfile} for older ones
 * that have none.
 */
interface BankProfile
{
    public function identity(): BankIdentity;

    /** Stable machine key, e.g. "postfinance.efinance". Part of the public API: never rename one. */
    public function id(): string;

    /**
     * Lower runs first. Use it to try a bank's more specific formats before its
     * looser ones; the generic fallback sits at the very end.
     */
    public function priority(): int;

    /** Null when this profile does not recognise the file. */
    public function match(CsvDocument $document): ?ProfileMatch;

    public function extract(CsvDocument $document, ProfileMatch $match): ParsedFile;
}
