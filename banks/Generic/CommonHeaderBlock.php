<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Generic;

use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;

/**
 * The "Label: value" preamble Swiss banks print above the table, in the
 * spellings that recur across institutions.
 *
 * It exists because several banks — Migros Bank and Valiant among them — ship
 * an export that is byte-for-byte indistinguishable from one another's:
 * the same preamble, the same four columns, the same wording. Neither can
 * honestly be claimed by name, so instead of guessing, the generic reader picks
 * up their account number and currency and leaves the bank unidentified.
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
            holder: [
                'Bezeichnung', 'Kontoinhaber',
                'Désignation', 'Titulaire',
                'Designazione', 'Intestatario',
                'Description', 'Account holder',
            ],
        );
    }
}
