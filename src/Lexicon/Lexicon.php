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
    /** @var array<string, array<string, true>>|null */
    private static ?array $index = null;

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
                'Data registrazione', 'Data operazione', "Data dell'operazione",
                'Transaktionsdatum', 'Data transazione',
                'Erfassungsdatum',
                // en
                'Booking date', 'Booked at', 'Transaction date', 'Entry date', 'Execution date',
                'Registration date',
            ],
            Term::ValueDate->value => [
                'Valuta', 'Valutadatum', 'Valuta-Datum', 'Wert', 'Wertstellung',
                'Valeur', 'Date de valeur',
                'Data valuta', 'Data di valuta', 'Valuta contabile',
                'Value date', 'Valuta Date', 'Value',
            ],
            Term::Description->value => [
                'Buchungstext', 'Avisierungstext', 'Text', 'Beschreibung', 'Bezeichnung', 'Buchungsdetails',
                'Mitteilung', 'Zahlungszweck', 'Verwendungszweck',
                'Libellé', 'Description', 'Texte de notification', 'Détails',
                'Détails de comptabilisation', 'Communication', 'Motif', 'Texte',
                'Texte comptable',
                'Descrizione', 'Testo di avviso', 'Dettagli', 'Dettaglio',
                'Dettagli di contabilizzazione', 'Comunicazione', 'Causale', 'Testo',
                'Details', 'Detail', 'Notification text', 'Booking details', 'Payment purpose',
                'Purpose',
                // Banks that spread one description over several columns. All of
                // them map to this term and are joined back together, in column
                // order — see ColumnMap.
                ...self::numbered(['Description', 'Beschreibung', 'Descrizione']),
            ],
            Term::Credit->value => [
                'Gutschrift', 'Gutschriften', 'Gutschriftsbetrag', 'Haben', 'Einzahlung',
                'Crédit', 'Credit', 'Montant crédité',
                'Accredito', 'Accrediti', 'Importo di accredito', 'Avere', 'Versamento',
                'Credit amount', 'Paid in', 'Money in',
            ],
            Term::Debit->value => [
                'Lastschrift', 'Belastung', 'Belastungsbetrag', 'Soll', 'Auszahlung',
                'Débit', 'Debit', 'Montant débité',
                'Addebito', 'Addebiti', 'Importo di addebito', 'Dare', 'Prelievo',
                'Debit amount', 'Paid out', 'Money out',
            ],
            Term::Amount->value => [
                'Betrag', 'Umsatz', 'Buchungsbetrag', 'Transaktionsbetrag',
                'Montant', 'Montant de la transaction',
                'Importo', 'Importo della transazione',
                'Amount', 'Credit/Debit Amount', 'Transaction amount',
            ],
            Term::Balance->value => [
                // "Saldo in (CHF)" — Thurgauer Kantonalbank's wording, whose
                // bracketed currency leaves "Saldo in" once stripped.
                'Saldo', 'Saldo in', 'Kontostand', 'Schlusssaldo', 'Neuer Saldo',
                'Solde', 'Solde du compte',
                'Saldo contabile',
                'Balance', 'Running balance', 'Closing balance',
            ],
            Term::Reference->value => [
                'Referenz', 'Referenznummer', 'Auftragsnummer', 'Auftraggeberreferenz',
                'Belegnummer', 'Beleg', 'Buchungsnummer', 'Transaktions-Nr.',
                'Référence', 'Numéro de référence', "Numéro d'ordre", 'Numéro de document',
                'N° de transaction', 'No de transaction',
                'Riferimento', 'Numero di riferimento', "Numero d'ordine", 'N. di transazione',
                'Reference', 'Reference number', 'External Reference', 'Transaction reference',
                'Order number', 'Document number', 'Transaction no.',
            ],
            Term::AccountIban->value => [
                // Only the unambiguous spelling. "Konto" and "Compte" are not
                // here on purpose: several banks use them for the *counterparty*
                // account, and reporting that as the statement's own account
                // would be worse than reporting none.
                'IBAN',
            ],
            Term::Currency->value => [
                'Währung', 'Whg', 'Whg.', 'Whrg.',
                'Waehrung',
                'Monnaie', 'Devise', 'Monn.',
                'Moneta', 'Divisa', 'Mon.',
                'Currency', 'Ccy.',
            ],
            Term::Category->value => [
                'Kategorie', 'Registrierte Kategorie',
                'Catégorie', 'Categoria', 'Categoria Registrata',
                'Category', 'Registered Category',
            ],
            Term::TransactionType->value => [
                'Bewegungstyp', 'Buchungsart', 'Auftragsart', 'Transaktionstyp',
                'Type de transaction', 'Genre de comptabilisation', "Type d'opération",
                'Tipo di movimento', 'Genere di registrazione', 'Tipo di transazione',
                'Tipo di ordine', "Type d'ordre", 'Order type',
                'Type of transaction', 'Transaction type', 'Booking type', 'Activity type',
            ],
        ];
    }

    /**
     * "Description" => "Description 1", "Description1", … up to 4, which is as
     * far as any Swiss export goes.
     *
     * Public because a profile sometimes has to narrow a term to the numbered
     * columns alone — see UBS, whose plain "Description" column names the
     * account rather than the booking.
     *
     * @param  list<string>  $stems
     * @return list<string>
     */
    public static function numbered(array $stems): array
    {
        $labels = [];

        foreach ($stems as $stem) {
            foreach (range(1, 4) as $index) {
                $labels[] = $stem.' '.$index;
                $labels[] = $stem.$index;
            }
        }

        return $labels;
    }

    /** @return list<string> */
    public static function labels(Term $term): array
    {
        return self::table()[$term->value] ?? [];
    }

    /**
     * The table folded into a lookup set, built once per process.
     *
     * Matching runs once per cell of every candidate heading row, for every
     * profile — several hundred thousand times on a large file. Rebuilding the
     * table and walking it linearly each time turned a twenty-thousand-row
     * statement into three minutes of work.
     *
     * @return array<string, array<string, true>>
     */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        $index = [];

        foreach (self::table() as $term => $labels) {
            foreach ($labels as $label) {
                $index[$term][self::fold($label)] = true;
            }
        }

        return self::$index = $index;
    }

    private static function fold(string $value): string
    {
        return mb_strtolower(Text::normalise($value), 'UTF-8');
    }

    /**
     * True when the heading names this term. Any currency suffix is ignored,
     * so "Gutschrift in CHF" matches {@see Term::Credit}.
     */
    public static function matches(string $heading, Term $term): bool
    {
        [$bare] = self::splitCurrency($heading);

        return isset(self::index()[$term->value][self::fold($bare)]);
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
