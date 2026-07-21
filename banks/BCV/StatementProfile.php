<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\BCV;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Banque Cantonale Vaudoise account statement.
 *
 * A short export preceded by a "Transactions list" title row that carries the
 * closing balance in its last cell. The heading row proper calls the description
 * column simply "Transactions" — plural, which no other Swiss bank does, and
 * which is what identifies the file.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('bcv', 'Banque Cantonale Vaudoise');
    }

    public function id(): string
    {
        return 'bcv.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => ['Transactions', 'Transaction'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Transactions'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
