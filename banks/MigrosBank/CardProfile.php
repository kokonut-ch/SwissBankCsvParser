<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\MigrosBank;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Migros Bank card transaction export.
 *
 * Produced by the same platform as Viseca's — same columns, same conventions —
 * and, like it, written from the issuer's point of view: **a purchase is
 * printed positive and a refund negative**. The amounts are flipped so that a
 * negative one means money left the cardholder, as everywhere else here.
 *
 * The one structural difference in the published samples is the trailing
 * `Exchange Rate` column, which Viseca's export does not carry — everything
 * else, `CardId` and `StateType` included, appears in both files. That column
 * is therefore the signature, and Viseca's profile excludes it.
 *
 * The three merchant columns plus `Details` make up the description, joined in
 * that order, which is how the file reads on screen. The bank's *account*
 * statement is deliberately not claimed — see this bank's README for why.
 */
final class CardProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('migrosbank', 'Migros Bank');
    }

    public function id(): string
    {
        return 'migrosbank.card';
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

    /**
     * `Exchange Rate` alone, deliberately. Viseca's near-identical export
     * carries `CardId` and `StateType` too, so signing on either would have
     * this profile claiming Viseca's files. The exchange-rate column is the
     * one heading the published Migros Bank sample carries and Viseca's does
     * not.
     */
    protected function signatureHeadings(): array
    {
        return ['Exchange Rate'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
