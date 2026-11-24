<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\UBS;

/**
 * Headings that identify a UBS export, shared by its account-statement
 * profiles.
 *
 * UBS names every other column the way everybody else does, so without one of
 * these there is nothing to tell a UBS file from any other bank's, and the
 * profile refuses to guess.
 */
trait Signatures
{
    /** @return list<string> */
    protected function signatureHeadings(): array
    {
        return [
            // The transaction number, in the four languages UBS ships.
            'Transaktions-Nr.', 'N. di transazione', 'N° de transaction', 'No de transaction',
            'Transaction no.',
            // The footnote column of the modern e-banking export.
            'Fussnoten', 'Note a piè di pagina', 'Notes de bas de page', 'Footnotes',
            // The portfolio export's own preamble columns.
            'Bankbeziehung', 'Relazione bancaria', 'Relation bancaire', 'Banking relationship',
        ];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
