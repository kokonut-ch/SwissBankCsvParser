<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\CornerCard;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Cornèrcard card statement.
 *
 * Six columns — `Date;Description;Card;Currency;Amount;Status` — and, like
 * Swisscard, written from the issuer's point of view: **a purchase is positive
 * and a refund negative**. The amounts are flipped so that a negative one means
 * money left the cardholder, as everywhere else here.
 *
 * No single heading names Cornèrcard: `Card`, `Currency` and `Status` are all
 * ordinary words. It is the three together, beside a date, a description and an
 * amount, that identify the file — hence {@see requiredHeadings()} rather than a
 * signature alone.
 *
 * Distinct from {@see \Kokonut\SwissBankCsvParser\Banks\Corner\StatementProfile},
 * which reads the bank's account statements.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('cornercard', 'Cornèrcard');
    }

    public function id(): string
    {
        return 'cornercard.statement';
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
        return ['d/m/Y', 'd.m.Y'];
    }

    protected function requiredHeadings(): array
    {
        return ['Card', 'Currency', 'Status'];
    }

    protected function signatureHeadings(): array
    {
        return ['Card'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
