<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\PostFinance;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Dto\Account;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

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
 *
 * Before the 2024 layout, the export had a single date, dated its rows
 * `2023-08-24`, and, in Italian, headed the description `Denominazione`, with
 * a trailing `Importo in CHF` column this profile does not read.
 */
final class CreditCardProfile extends HeaderDrivenProfile
{
    /**
     * Ceiling for a file whose description heading is the ordinary Italian
     * `Denominazione` and which carries no signature. Below the generic
     * reader's 0.30, so such a file is read as PostFinance only once the
     * caller has restricted the parser to this bank.
     */
    private const float UNSIGNED_DENOMINAZIONE_SCORE = 0.25;

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
        return ['d.m.Y', 'd.m.y', 'Y-m-d'];
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
                'Denominazione',
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

    /**
     * `Denominazione` is what any Italian export may call a description, so on
     * its own it does not name PostFinance. Without the card-account preamble
     * or the purchase-date heading, such a file stays below the generic reader.
     */
    public function match(CsvDocument $document): ?ProfileMatch
    {
        $match = parent::match($document);

        if ($match === null || $this->isSigned($document, $match->columns->headerRow)) {
            return $match;
        }

        $description = $match->columns->indexOf(Term::Description);
        $heading = $description === null ? '' : ($document->row($match->columns->headerRow)[$description] ?? '');

        if (! Text::equals(Lexicon::splitCurrency($heading)[0], 'Denominazione')) {
            return $match;
        }

        return new ProfileMatch(
            min($match->score, self::UNSIGNED_DENOMINAZIONE_SCORE),
            [...$match->reasons, 'description heading "Denominazione" without a card-account preamble: the bank is not named'],
            $match->columns,
        );
    }

    private function isSigned(CsvDocument $document, int $headerRow): bool
    {
        if ($document->hasMetadata($this->signatureMetadata())) {
            return true;
        }

        foreach ($document->row($headerRow) as $cell) {
            foreach ($this->signatureHeadings() as $signature) {
                if (Text::equals(Lexicon::splitCurrency($cell)[0], $signature)) {
                    return true;
                }
            }
        }

        return false;
    }
}
