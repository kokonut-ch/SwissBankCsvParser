<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\PostFinance;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * PostFinance e-finance account statement, the export shipped since early 2024.
 *
 * A "Label: value" preamble, then a heading row whose wording follows the
 * customer's language, then the rows, then a legal disclaimer. Credit and debit
 * sit in separate columns and name the currency in their heading
 * ("Crédit en CHF"), which is where the account currency comes from.
 *
 * Debits are printed as negative numbers in the debit column. That sign is
 * ignored — the column already says which way the money went.
 *
 * The same profile reads the account statements PostFinance exported before,
 * as far back as 2003: the heading row has always named the same columns,
 * only the wording and the notation drifted. Older Italian exports say
 * `Testo d'avviso`, the 2003 one `Descrizione della transazione`,
 * `Accreditamento` and `Debito`; a 2009 export is tab-separated and dated
 * `20090401`, and some 2016 exports are dated `2016-11-14`.
 */
final class EFinanceProfile extends HeaderDrivenProfile
{
    /**
     * PostFinance's own description headings, in every language and vintage.
     * No other bank prints them, so they double as signatures.
     *
     * The 2003 export's `Descrizione della transazione` is read too, but kept
     * out of this list: it is ordinary Italian, and names no bank.
     *
     * @var list<string>
     */
    private const array NOTIFICATION_HEADINGS = [
        'Avisierungstext',
        'Testo di avviso',
        "Testo d'avviso",
        'Texte de notification',
        'Notification text',
    ];

    public function identity(): BankIdentity
    {
        return new BankIdentity('postfinance', 'PostFinance');
    }

    public function id(): string
    {
        return 'postfinance.efinance';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y', 'Y-m-d', 'Ymd'];
    }

    /**
     * The description heading is what separates this format from the card
     * statement, which is otherwise laid out almost identically. Left to the
     * shared lexicon, both would match both files.
     *
     * The amount and value-date headings keep the shared vocabulary and add the
     * older Italian wordings, which turn up in no other bank's published
     * samples.
     */
    protected function termLabels(): array
    {
        return [
            Term::Description->value => [...self::NOTIFICATION_HEADINGS, 'Descrizione della transazione'],
            Term::Credit->value => [...Lexicon::labels(Term::Credit), 'Accreditamento'],
            Term::Debit->value => [...Lexicon::labels(Term::Debit), 'Debito'],
            Term::ValueDate->value => [...Lexicon::labels(Term::ValueDate), 'Data della valuta'],
        ];
    }

    /**
     * PostFinance quotes a description that runs over several lines, so a line
     * with text and no date is never the rest of a booking here. In the
     * published samples the only such line is the 2003 export's closing
     * balance, a "Saldo al <date>" cell followed by the amount, which would
     * otherwise end up in the last row's label.
     */
    protected function allowsContinuationRows(): bool
    {
        return false;
    }

    protected function signatureHeadings(): array
    {
        return self::NOTIFICATION_HEADINGS;
    }

    protected function signatureMetadata(): array
    {
        return ['Compte', 'Konto', 'Conto', 'Account'];
    }

    protected function headerBlock(): HeaderBlock
    {
        return new HeaderBlock(
            account: ['Compte', 'Konto', 'Conto', 'Account'],
            currency: ['Monnaie', 'Währung', 'Moneta', 'Currency'],
            from: ['Date de début', 'Von-Datum', 'Data dal', 'Date from'],
            to: ['Date de fin', 'Bis-Datum', 'Data al', 'Date to'],
        );
    }
}
