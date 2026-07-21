<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Yuh;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Yuh account statement.
 *
 * Yuh is a banking and investing app, so its export carries far more than a
 * statement: card details, localities, counterparties, fees, and the quantity
 * and asset of any security bought or sold. Only the banking columns are
 * modelled; the rest stays in {@see Row::$raw}.
 *
 * Its own oddity is a currency column on each side — `DEBIT CURRENCY` and
 * `CREDIT CURRENCY` — with only the one that applies to the row filled in. Both
 * are mapped, and the first that holds anything wins.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('yuh', 'Yuh');
    }

    public function id(): string
    {
        return 'yuh.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'Y-m-d'];
    }

    protected function termLabels(): array
    {
        return [
            // Yuh describes a booking with a kind and a name, in that order.
            Term::Description->value => ['ACTIVITY TYPE', 'ACTIVITY NAME'],
            Term::Currency->value => ['DEBIT CURRENCY', 'CREDIT CURRENCY'],
        ];
    }

    protected function joinableTerms(): array
    {
        return [Term::Description, Term::Currency];
    }

    protected function signatureHeadings(): array
    {
        return ['ACTIVITY NAME', 'FEES/COMMISSION', 'BUY/SELL'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
