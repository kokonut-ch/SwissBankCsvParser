<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Lexicon;

use Kokonut\SwissBankCsvParser\Csv\Text;

/**
 * What Swiss banks call their columns, in German, French, Italian and English.
 *
 * This table is the reason a bank profile can be twenty lines instead of a
 * hand-written parser: the vocabulary repeats heavily across institutions, so
 * most profiles only have to say which terms they need and which quirks they
 * have.
 *
 * Headings are matched by whole-string equality, never by substring. That is
 * what keeps Raiffeisen's signed "Credit/Debit Amount" column from being read
 * as a plain "Credit" column.
 *
 * One trap worth knowing: German "Valuta" means value date, Italian "Valuta"
 * means currency. It is listed under {@see Term::ValueDate} only — a profile
 * for an Italian-language export must name its currency heading explicitly.
 */
final class Lexicon
{
    /**
     * @return array<string, list<string>>
     */
    private static function table(): array
    {
        return [
            Term::BookingDate->value => [
                // de
                'Datum', 'Buchungsdatum', 'Buchung', 'Buchungstag', 'Datum der Buchung',
                // fr
                'Date', 'Date de comptabilisation', "Date d'écriture", 'Date de transaction',
                "Date de l'opération",
                // it
                'Data', 'Data di registrazione', 'Data contabile', 'Data di contabilizzazione',
                'Data registrazione', 'Data operazione',
                // en
                'Booking date', 'Booked at', 'Transaction date', 'Entry date', 'Execution date',
            ],
            Term::ValueDate->value => [
                'Valuta', 'Valutadatum', 'Valuta-Datum', 'Wert', 'Wertstellung',
                'Valeur', 'Date de valeur',
                'Data valuta', 'Valuta contabile',
                'Value date', 'Value',
            ],
            Term::Description->value => [
                'Buchungstext', 'Avisierungstext', 'Text', 'Beschreibung', 'Buchungsdetails',
                'Mitteilung', 'Zahlungszweck', 'Verwendungszweck',
                'Libellé', 'Description', 'Texte de notification', 'Détails',
                'Détails de comptabilisation', 'Communication', 'Motif', 'Texte',
                'Descrizione', 'Testo di avviso', 'Dettagli', 'Dettagli di contabilizzazione',
                'Comunicazione', 'Causale', 'Testo',
                'Details', 'Notification text', 'Booking details', 'Payment purpose', 'Purpose',
            ],
            Term::Credit->value => [
                'Gutschrift', 'Gutschriften', 'Gutschriftsbetrag', 'Haben', 'Einzahlung',
                'Crédit', 'Credit', 'Montant crédité',
                'Accredito', 'Importo di accredito', 'Avere', 'Versamento',
                'Credit amount', 'Paid in', 'Money in',
            ],
            Term::Debit->value => [
                'Lastschrift', 'Belastung', 'Belastungsbetrag', 'Soll', 'Auszahlung',
                'Débit', 'Debit', 'Montant débité',
                'Addebito', 'Importo di addebito', 'Dare', 'Prelievo',
                'Debit amount', 'Paid out', 'Money out',
            ],
            Term::Amount->value => [
                'Betrag', 'Umsatz', 'Buchungsbetrag',
                'Montant',
                'Importo',
                'Amount', 'Credit/Debit Amount',
            ],
            Term::Balance->value => [
                'Saldo', 'Kontostand', 'Schlusssaldo', 'Neuer Saldo',
                'Solde', 'Solde du compte',
                'Saldo contabile',
                'Balance', 'Running balance', 'Closing balance',
            ],
            Term::Reference->value => [
                'Referenz', 'Referenznummer', 'Auftragsnummer', 'Auftraggeberreferenz',
                'Belegnummer', 'Beleg', 'Buchungsnummer',
                'Référence', 'Numéro de référence', "Numéro d'ordre", 'Numéro de document',
                'Riferimento', 'Numero di riferimento', "Numero d'ordine",
                'Reference', 'Reference number', 'External Reference', 'Transaction reference',
                'Order number', 'Document number',
            ],
            Term::Currency->value => [
                'Währung',
                'Monnaie', 'Devise',
                'Moneta', 'Divisa',
                'Currency',
            ],
            Term::Category->value => [
                'Kategorie', 'Catégorie', 'Categoria', 'Category',
            ],
            Term::TransactionType->value => [
                'Bewegungstyp', 'Buchungsart', 'Auftragsart', 'Transaktionstyp',
                'Type de transaction', 'Genre de comptabilisation', "Type d'opération",
                'Tipo di movimento', 'Genere di registrazione', 'Tipo di transazione',
                'Type of transaction', 'Transaction type', 'Booking type', 'Activity type',
            ],
        ];
    }

    /** @return list<string> */
    public static function labels(Term $term): array
    {
        return self::table()[$term->value] ?? [];
    }

    /**
     * True when the heading names this term. Any currency suffix is ignored,
     * so "Gutschrift in CHF" matches {@see Term::Credit}.
     */
    public static function matches(string $heading, Term $term): bool
    {
        [$bare] = self::splitCurrency($heading);

        foreach (self::labels($term) as $label) {
            if (Text::equals($bare, $label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The ISO 4217 code a heading carries, if any: banks routinely name the
     * currency in the amount column rather than in the header block.
     */
    public static function currencyIn(string $heading): ?string
    {
        return self::splitCurrency($heading)[1];
    }

    /**
     * Separates a heading from its currency suffix.
     *
     * Recognised: "Crédit en CHF", "Gutschrift in CHF", "Importo di accredito (CHF)",
     * "Saldo CHF". The code must already be uppercase in the source, which is
     * what stops ordinary words from being read as currencies.
     *
     * @return array{0: string, 1: string|null}
     */
    public static function splitCurrency(string $heading): array
    {
        $heading = Text::normalise($heading);

        $patterns = [
            '/^(.*?)\s*\(([A-Z]{3})\)$/u',
            '/^(.*?)\s+(?:in|en|di|em)\s+([A-Z]{3})$/iu',
            '/^(.*?)\s+([A-Z]{3})$/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $heading, $matches) === 1 && trim($matches[1]) !== '') {
                return [trim($matches[1]), strtoupper($matches[2])];
            }
        }

        return [$heading, null];
    }
}
