<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Viseca;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Viseca card transaction export.
 *
 * Written from the issuer's point of view: **a purchase is printed positive** —
 * it is what the cardholder owes — **and a refund negative**. The amounts are
 * flipped so that a negative one means money left the cardholder, as everywhere
 * else in this package.
 *
 * Structurally almost the same file as Migros Bank's card export — the same
 * platform produces both, with the same columns and the same issuer-side sign.
 * The one difference in the published samples is Migros Bank's trailing
 * `Exchange Rate` column, absent here. Signing on what is present cannot
 * separate the two — both print `CardId` and `StateType` — so this profile
 * declares that column disqualifying, and Migros Bank's signs on it.
 */
final class CardProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('viseca', 'Viseca');
    }

    public function id(): string
    {
        return 'viseca.card';
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
        return ['Y-m-d', 'd.m.Y', 'd.m.y'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => ['MerchantName', 'MerchantPlace', 'MerchantCountry', 'Details'],
            Term::ValueDate->value => ['ValutaDate'],
            Term::Reference->value => ['TransactionId'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['StateType'];
    }

    protected function requiredHeadings(): array
    {
        return ['StateType'];
    }

    /**
     * Migros Bank's export prints everything this one does, `StateType`
     * included, plus a trailing exchange-rate column. That column is the only
     * thing telling the two apart, so its presence hands the file to
     * {@see \Kokonut\SwissBankCsvParser\Banks\MigrosBank\CardProfile}.
     */
    protected function excludedHeadings(): array
    {
        return ['Exchange Rate'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
