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
 * Structurally almost the same file as Migros Bank's card export — same
 * merchant columns, same transaction id, one signed amount column. The sign
 * convention is where they part ways: `migrosbank.card` takes its amounts at
 * face value. Detection-wise, Viseca prints `StateType` where Migros Bank
 * prints `CardId`, and that is what each profile signs on.
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

    protected function requiresSignature(): bool
    {
        return true;
    }
}
