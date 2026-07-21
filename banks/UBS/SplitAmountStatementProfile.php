<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\UBS;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * UBS account statement with separate debit and credit columns.
 *
 * Covers both the wide portfolio export — twenty-one columns, two-digit years,
 * the account's own IBAN in a column — and the modern e-banking export that
 * splits the two directions.
 *
 * The catch is the description. UBS prints a plain "Description" column holding
 * the *account* name, repeated identically on every row, and puts the actual
 * booking text in "Description 1/2/3". Left to the shared lexicon, every label
 * would start with "UBS Business Current Account". The term is therefore
 * narrowed to the numbered columns.
 */
final class SplitAmountStatementProfile extends HeaderDrivenProfile
{
    use Signatures;

    public function identity(): BankIdentity
    {
        return new BankIdentity('ubs', 'UBS');
    }

    public function id(): string
    {
        return 'ubs.statement.split';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y', 'Y-m-d'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => Lexicon::numbered(['Description', 'Beschreibung', 'Descrizione']),
            Term::BookingDate->value => self::BOOKING_DATE_LABELS,
        ];
    }

    protected function joinableTerms(): array
    {
        return [Term::Description, Term::BookingDate];
    }
}
