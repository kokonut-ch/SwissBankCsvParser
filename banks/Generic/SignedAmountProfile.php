<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Generic;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * Last resort for a file with one signed amount column — the other half of the
 * Swiss market, used by Raiffeisen, Migros Bank, BEKB and others.
 *
 * Same caveats as {@see SplitColumnsProfile}: capped confidence, and always a
 * {@see Warning::GENERIC_PROFILE_USED}.
 */
final class SignedAmountProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('unknown', 'Unrecognised bank', '');
    }

    public function id(): string
    {
        return 'generic.signed-amount';
    }

    public function priority(): int
    {
        return 1001;
    }

    protected function maxScore(): float
    {
        return 0.30;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::SignedColumn;
    }

    protected function requiredTerms(): array
    {
        return [Term::BookingDate, Term::Amount];
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
