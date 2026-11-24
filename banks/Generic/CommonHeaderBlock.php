<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Generic;

use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;

/**
 * The "Label: value" preamble Swiss banks print above the table, in the
 * spellings that recur across institutions.
 *
 * It exists because several banks (Valiant, acrevis, Schwyzer KB, and Migros
 * Bank in its older layout among them) ship an export that is byte-for-byte
 * indistinguishable from one another's: the same preamble, the same four
 * columns, the same wording. None can honestly be claimed by name, so instead
 * of guessing, the generic reader picks up their account number and currency
 * and leaves the bank unidentified.
 *
 * Reading a preamble costs nothing when it is absent, so both generic profiles
 * use it.
 */
final class CommonHeaderBlock
{
    public static function get(): HeaderBlock
    {
        return new HeaderBlock(
            account: [
                'Kontonummer', 'Konto', 'IBAN',
                'Numéro de compte', 'Compte',
                'Numero di conto', 'Conto',
                'Account number', 'Account',
            ],
            currency: [
                'Währung', 'Monnaie', 'Devise', 'Moneta', 'Currency',
                // A balance line names the currency too — "Saldo: CHF 38547.70" —
                // and the ISO code is picked out of the value.
                'Saldo', 'Solde', 'Balance',
            ],
            // Only labels that name a person. "Bezeichnung", and "Description"
            // in French, are what these preambles print most often, but what
            // follows them is the kind of account: "Geschäftskonto",
            // "Kontokorrent", "Compte courant CHF", in every published sample.
            // The holder's name sits on the unlabelled address lines below.
            // The designation stays in the metadata.
            holder: [
                'Kontoinhaber', 'Titulaire', 'Intestatario', 'Account holder',
            ],
        );
    }
}
