<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Swisscard;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Swisscard card statement.
 *
 * Everything here is ordinary except the sign. Swisscard writes the statement
 * from the issuer's point of view: **a purchase is positive** — it is what you
 * owe — **and a refund is negative**. Read at face value, every charge on the
 * card would come out as income.
 *
 * The amounts are therefore flipped, so that here, as everywhere else in this
 * package, a negative amount means money left the cardholder.
 *
 * The file also prints a debit/credit word column beside the amount. It is not
 * read: the sign already carries the direction, and consulting both would be a
 * way to disagree with oneself.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('swisscard', 'Swisscard');
    }

    public function id(): string
    {
        return 'swisscard.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::InvertedSignedColumn;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y'];
    }

    protected function signatureHeadings(): array
    {
        return ['Registered Category', 'Registrierte Kategorie', 'Categoria Registrata'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
