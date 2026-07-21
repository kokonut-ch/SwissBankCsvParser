<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Cler;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Bank Cler account statement.
 *
 * Ordinary in every respect but two: the currency is written in brackets after
 * each amount heading — "Importo di addebito (CHF)", "Solde (USD)" — which is
 * where the account currency comes from, and every row is classified by an
 * order type that no other Swiss bank prints under that name.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('cler', 'Bank Cler');
    }

    public function id(): string
    {
        return 'cler.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y'];
    }

    protected function signatureHeadings(): array
    {
        return ['Tipo di ordine', "Type d'ordre", 'Order type'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
