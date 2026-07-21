<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\UBS;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * UBS credit card statement.
 *
 * Two dates per row, as card statements always have: the purchase date and the
 * date the issuer booked it. UBS calls the latter simply "Ecriture", which is
 * why the booking date has to be named explicitly here — no shared vocabulary
 * would guess that.
 *
 * The row also carries the amount three times over: in the original currency,
 * converted, and split across debit and credit. Only the last pair is read; the
 * rest stays in {@see Row::$raw}.
 */
final class CreditCardProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('ubs', 'UBS');
    }

    public function id(): string
    {
        return 'ubs.creditcard';
    }

    public function priority(): int
    {
        return 10;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y'];
    }

    protected function termLabels(): array
    {
        return [
            Term::BookingDate->value => ['Ecriture', 'Écriture', 'Buchung', 'Registrazione', 'Booking'],
            Term::ValueDate->value => ["Date d'achat", 'Einkaufsdatum', 'Data di acquisto', 'Purchase date'],
            Term::Description->value => ['Texte comptable', 'Buchungstext', 'Testo contabile', 'Booking text'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Numéro de carte', 'Kartennummer', 'Numero di carta', 'Card number'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
