<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Raiffeisen;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Raiffeisen account statement.
 *
 * One signed amount column rather than a credit/debit pair, English headings
 * whatever the customer's language, and no header block at all — the IBAN is
 * repeated on every row instead.
 *
 * Two things about this export need the engine's help:
 *
 * - dates come as "2024-07-02 00:00:00.0", and in older files as
 *   "01.01.2021 00:00" or "03.01.13". The clock time is dropped; a statement
 *   line is about a day;
 * - a booking often spills onto the next line, which carries nothing but more
 *   description. Those lines are folded back into the row above, which is where
 *   the counterparty's name usually lives.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('raiffeisen', 'Raiffeisen');
    }

    public function id(): string
    {
        return 'raiffeisen.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::SignedColumn;
    }

    protected function dateFormats(): array
    {
        return ['Y-m-d', 'd.m.Y', 'd/m/Y', 'd.m.y'];
    }

    /**
     * Every column here is ordinary except this one, so without it the profile
     * would match half the market.
     */
    protected function signatureHeadings(): array
    {
        return ['Credit/Debit Amount'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
