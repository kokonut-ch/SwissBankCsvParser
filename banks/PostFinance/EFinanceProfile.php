<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\PostFinance;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
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
 */
final class EFinanceProfile extends HeaderDrivenProfile
{
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
        return ['d.m.Y', 'd.m.y'];
    }

    /**
     * The description heading is what separates this format from the card
     * statement, which is otherwise laid out almost identically. Left to the
     * shared lexicon, both would match both files.
     */
    protected function termLabels(): array
    {
        return [
            Term::Description->value => [
                'Avisierungstext',
                'Testo di avviso',
                'Texte de notification',
                'Notification text',
            ],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Avisierungstext', 'Testo di avviso', 'Texte de notification', 'Notification text'];
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
