<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Swisscard;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Swisscard card statement.
 *
 * Everything here is ordinary except the sign. Swisscard writes the statement
 * from the issuer's point of view: **a purchase is positive** — it is what you
 * owe — **and a refund is negative**. Read at face value, every charge on the
 * card would come out as income.
 *
 * The amounts are therefore flipped, so that here, as everywhere else in this
 * package, a negative amount means money left the cardholder.
 *
 * The file also prints a debit/credit word column beside the amount. It is not
 * read: the sign already carries the direction, and consulting both would be a
 * way to disagree with oneself. That column's *heading* is still useful — no
 * other Swiss export prints `Debit/Credit` (or `Debit/Kredit`,
 * `Debito/Credito`) as a column name, which is what lets the older layout be
 * recognised: its category heading has no "Registered" prefix, so the word
 * column is the only signature it carries.
 *
 * Two layouts exist. The current one is twelve columns — merchant, foreign
 * currency and merchant category included — the 2023 one is eight, quoted and
 * comma-separated. Both carry a date, a description, an amount, a card number,
 * a currency, a status and a category, which is why those are the required
 * terms. The Italian files are why {@see termLabels()} claims `Valuta` for the
 * currency: the shared lexicon lists that word under value date only, and
 * neither layout has a value-date column to collide with.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('swisscard', 'Swisscard');
    }

    public function id(): string
    {
        return 'swisscard.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::InvertedSignedColumn;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y'];
    }

    protected function requiredTerms(): array
    {
        return [
            ...parent::requiredTerms(),
            Term::CardNumber, Term::Currency, Term::Status, Term::Category,
        ];
    }

    protected function termLabels(): array
    {
        return [
            Term::Currency->value => [...Lexicon::labels(Term::Currency), 'Valuta'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return [
            'Registered Category', 'Registrierte Kategorie', 'Categoria Registrata',
            // The 2023 layout's only distinctive heading is the debit/credit
            // word column — the column whose values are deliberately not read.
            'Debit/Credit', 'Debit/Kredit', 'Debito/Credito',
        ];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
