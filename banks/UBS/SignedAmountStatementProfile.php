<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\UBS;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * UBS e-banking statement with a single signed amount column.
 *
 * The same export as {@see SplitAmountStatementProfile} in every other respect,
 * but the two directions are merged into "Transaktionsbetrag" and a separate
 * word column repeats the direction. That word column is ignored: the sign is
 * already on the amount, and reading both would be a way to disagree with
 * oneself.
 *
 * The two profiles cannot both match a file — one needs a debit and a credit
 * column, the other an amount column, and no UBS export has all three.
 */
final class SignedAmountStatementProfile extends HeaderDrivenProfile
{
    use Signatures;

    public function identity(): BankIdentity
    {
        return new BankIdentity('ubs', 'UBS');
    }

    public function id(): string
    {
        return 'ubs.statement.signed';
    }

    public function priority(): int
    {
        return 21;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::SignedColumn;
    }

    protected function dateFormats(): array
    {
        return ['Y-m-d', 'd.m.Y', 'd.m.y'];
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
