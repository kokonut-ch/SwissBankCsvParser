<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\CornerCard;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Cornèrcard card statement.
 *
 * Six columns — `Date;Description;Card;Currency;Amount;Status`, printed in
 * English, German or Italian — and, like Swisscard, written from the issuer's
 * point of view: **a purchase is positive and a refund negative**. The amounts
 * are flipped so that a negative one means money left the cardholder, as
 * everywhere else here.
 *
 * No single heading names Cornèrcard: card, currency and status are all
 * ordinary words. It is the three together, beside a date, a description and an
 * amount, that identify the file — hence they are required *terms*, resolved
 * through the multilingual lexicon, so the German and Italian files are
 * recognised by the same rule as the English one.
 *
 * The Italian file is why {@see termLabels()} claims `Valuta` for the currency:
 * the shared lexicon lists that word under value date only, and this format has
 * no value-date column to collide with.
 *
 * Distinct from {@see \Kokonut\SwissBankCsvParser\Banks\Corner\StatementProfile},
 * which reads the bank's account statements.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('cornercard', 'Cornèrcard');
    }

    public function id(): string
    {
        return 'cornercard.statement';
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
        return ['d/m/Y', 'd.m.Y'];
    }

    protected function requiredTerms(): array
    {
        return [...parent::requiredTerms(), Term::CardNumber, Term::Currency, Term::Status];
    }

    protected function termLabels(): array
    {
        return [
            Term::Currency->value => [...Lexicon::labels(Term::Currency), 'Valuta'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Card', 'Karte', 'Carta', 'Carte'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
