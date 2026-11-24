<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\PostFinance;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * PostFinance e-finance account statement as exported around 2010 to 2013,
 * before the layout {@see EFinanceProfile} reads.
 *
 * Debit comes before credit, and a `Details` column sits between the booking
 * text and the value date:
 *
 *     BookingDate;BookingText;Details;ValutaDate;DebitAmount;CreditAmount;Balance
 *     Buchung;Buchungstext;Details;Valuta;Belastung;Gutschrift;Saldo;Kategorie;Familienmitglied;Kommentar
 *
 * `Details` holds a small number (1, 2) or nothing. It is not an amount and not
 * part of the description, so it is left in {@see Row::$raw}.
 *
 * Every heading is narrowed to these exact spellings. The German ones are
 * ordinary words that half the market prints, so the profile only matches when
 * a heading no other bank uses is present: the English export's camel-cased
 * names, or the German export's `Familienmitglied` (family member) column.
 */
final class EFinance2010Profile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('postfinance', 'PostFinance');
    }

    public function id(): string
    {
        return 'postfinance.efinance-2010';
    }

    public function priority(): int
    {
        return 30;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y'];
    }

    protected function optionalTerms(): array
    {
        return [Term::ValueDate, Term::Balance, Term::Category];
    }

    protected function termLabels(): array
    {
        return [
            Term::BookingDate->value => ['BookingDate', 'Buchung'],
            Term::Description->value => ['BookingText', 'Buchungstext'],
            Term::ValueDate->value => ['ValutaDate', 'Valuta'],
            Term::Debit->value => ['DebitAmount', 'Belastung'],
            Term::Credit->value => ['CreditAmount', 'Gutschrift'],
            Term::Balance->value => ['Balance', 'Saldo'],
            Term::Category->value => ['Kategorie'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['BookingText', 'DebitAmount', 'CreditAmount', 'Familienmitglied'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
