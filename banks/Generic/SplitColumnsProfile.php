<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Generic;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * Last resort for a file with separate credit and debit columns whose bank is
 * not known to this package.
 *
 * It relies on nothing but the shared lexicon, so it reads a statement from any
 * bank that uses ordinary column names in German, French, Italian or English —
 * which, in practice, is most of them.
 *
 * Its confidence is capped well below every real profile, so it can never win
 * against a bank that identifies itself, and it always attaches
 * {@see Warning::GENERIC_PROFILE_USED} so callers can tell a guess from an
 * identification.
 */
final class SplitColumnsProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('unknown', 'Unrecognised bank', '');
    }

    public function id(): string
    {
        return 'generic.split-columns';
    }

    public function priority(): int
    {
        return 1000;
    }

    protected function maxScore(): float
    {
        return 0.30;
    }

    /** Deliberately permissive: a file without a description column is still readable. */
    protected function requiredTerms(): array
    {
        return [Term::BookingDate, Term::Credit, Term::Debit];
    }

    protected function optionalTerms(): array
    {
        return [Term::Description, ...parent::optionalTerms()];
    }

    protected function headerBlock(): HeaderBlock
    {
        return CommonHeaderBlock::get();
    }

    public function extract(CsvDocument $document, ProfileMatch $match): ParsedFile
    {
        return parent::extract($document, $match)->withWarnings([
            new Warning(
                Warning::GENERIC_PROFILE_USED,
                'No bank profile matched; columns were mapped from their headings alone. '
                .'The exporting bank is not identified.',
                ['profile' => $this->id()],
            ),
        ]);
    }
}
