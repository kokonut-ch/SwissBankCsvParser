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
 * Unlike the bank's account statement — see this bank's README for why that one
 * is deliberately not claimed — the card export names itself clearly, with
 * `CardId` and a merchant broken into name, place and country.
 *
 * Those three merchant columns plus `Details` make up the description, joined in
 * that order, which is how the file reads on screen.
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
        return AmountModel::SignedColumn;
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
     * `CardId` alone, deliberately. Viseca ships a near-identical export whose
     * only structural difference is `StateType` where this one has `CardId` —
     * so signing on `MerchantName`, which both print, would have this profile
     * claiming Viseca's files.
     */
    protected function signatureHeadings(): array
    {
        return ['CardId'];
    }

    protected function excludedHeadings(): array
    {
        return ['StateType'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
