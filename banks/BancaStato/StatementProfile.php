<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\BancaStato;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Banca dello Stato del Cantone Ticino account statement.
 *
 * Italian throughout, with separate debit and credit columns and an external
 * reference of its own. Bookings are followed by a line spelling out the
 * original amount and the charges — folded back into the row above, as
 * elsewhere.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('bancastato', 'Banca dello Stato del Cantone Ticino');
    }

    public function id(): string
    {
        return 'bancastato.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => ['Testo di contabilizzazione', 'Tipo'],
            Term::Reference->value => ['Rif.Esterno', 'Rif. Esterno'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Rif.Esterno', 'Rif. Esterno'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
