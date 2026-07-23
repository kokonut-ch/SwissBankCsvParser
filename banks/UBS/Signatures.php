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
    /**
     * Booking date first, trade date only as a fallback.
     *
     * UBS prints both, and prints the trade date in the earlier column — so
     * reading left to right binds the row's date to the wrong one whenever the
     * two differ, which on a settled transaction they do. Booking-first is this
     * package's own rule, not the bank's: UBS's import rules for the wide
     * portfolio export take booking-then-trade, but those for the modern
     * e-banking exports read the trade date alone. Here the row date is the
     * date the bank booked the line on every layout, as everywhere else in
     * this package — it is the date the balance column moves on — with the
     * trade date as the fallback, honoured because the profile declares this
     * term joinable.
     *
     * "Data di contabilizzazione" is the Italian booking date since about
     * 2024; "Data di registrazione" the wording before it.
     *
     * @var list<string>
     */
    private const array BOOKING_DATE_LABELS = [
        'Buchungsdatum', 'Date de comptabilisation', 'Data di registrazione',
        'Data di contabilizzazione', 'Booking date',
        'Abschluss', 'Abschlussdatum', 'Date de transaction', "Data dell'operazione",
        "Date de l'opération", 'Trade date',
    ];

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
