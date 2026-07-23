<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\PostFinance;

use Kokonut\SwissBankCsvParser\Dto\Account;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * PostFinance credit card statement.
 *
 * Laid out like the account statement but with two dates per row: the date the
 * card issuer booked the line, and the date the purchase was made. The booking
 * date is the row date; the purchase date is reported as the value date, that
 * being the closest thing the neutral model has to "the other date the bank
 * printed".
 *
 * The header block names a card account rather than an IBAN, so
 * {@see Account::$iban} stays null here and the
 * number is reported as printed, masking included.
 */
final class CreditCardProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('postfinance', 'PostFinance');
    }

    public function id(): string
    {
        return 'postfinance.creditcard';
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
            // Both of these are specific to the card statement, and both are
            // needed: the description heading keeps the account-statement
            // profile from matching, and the purchase date has no equivalent in
            // the shared lexicon.
            Term::Description->value => [
                'Buchungsdetails',
                'Dettagli di contabilizzazione',
                'Détails de comptabilisation',
                'Booking details',
            ],
            Term::ValueDate->value => [
                'Einkaufsdatum',
                'Data di acquisto',
                "Date d'achat",
                'Purchase date',
            ],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Einkaufsdatum', 'Data di acquisto', "Date d'achat", 'Purchase date'];
    }

    protected function signatureMetadata(): array
    {
        return ['Kartenkonto', 'Conto della carta', 'Compte de carte', 'Card account'];
    }

    protected function headerBlock(): HeaderBlock
    {
        return new HeaderBlock(
            account: ['Kartenkonto', 'Conto della carta', 'Compte de carte', 'Card account'],
            // The English export labels the holder "Card owner"; "Card holder"
            // is kept in case both wordings circulate.
            holder: ['Karteninhaber', 'Titolare della carta', 'Titulaire de la carte', 'Card owner', 'Card holder'],
        );
    }
}
