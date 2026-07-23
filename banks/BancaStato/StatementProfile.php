<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\BancaStato;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Banca dello Stato del Cantone Ticino account statement.
 *
 * Italian throughout, with separate debit and credit columns, an external
 * reference of its own — `Rif.Esterno`, the signature — and an order type
 * (`Tipo`) reported as an extra, the way Bank Cler's `Tipo di ordine` is.
 *
 * The bank's newer layouts number their orders (`Numero di ordine`) instead of
 * referencing them; those carry no signature here and are deliberately left to
 * the generic reader.
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
            Term::Description->value => ['Testo di contabilizzazione'],
            Term::Reference->value => ['Rif.Esterno', 'Rif. Esterno'],
            // Plain "Tipo" is not in the shared vocabulary; here it is the
            // order type, and belongs in extras rather than in the label.
            Term::TransactionType->value => ['Tipo'],
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
