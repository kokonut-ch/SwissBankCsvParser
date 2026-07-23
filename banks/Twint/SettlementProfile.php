<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Twint;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * TWINT merchant transaction report.
 *
 * Not a bank statement: this is what a merchant downloads to reconcile the
 * payments taken at a terminal against the payout that lands on the account. It
 * is read here because the rows are still dated amounts with a description, and
 * because a fiduciary reconciling TWINT payouts needs them.
 *
 * Only the transaction amount is reported. The discount and the transaction fee
 * sit in their own columns, are not netted off, and remain in
 * {@see Row::$raw} — what a merchant owes TWINT
 * is a matter for the caller, not for a parser.
 */
final class SettlementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('twint', 'TWINT');
    }

    public function id(): string
    {
        return 'twint.settlement';
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
        return ['Y.m.d', 'd.m.Y', 'Y-m-d'];
    }

    protected function termLabels(): array
    {
        return [
            // The currency travels in brackets on the heading, and is picked up
            // from there: "Betrag Transaktion (CHF)".
            Term::Amount->value => [
                'Betrag Transaktion', 'Montant de la transaction', 'Transaction amount',
            ],
            Term::Description->value => ['Typ', 'Type', 'Tipo'],
            Term::Reference->value => ['TWINT Order ID', 'TWINT order ID', 'ID de commande TWINT'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return [
            'TWINT Terminal ID', 'TWINT terminal ID', 'ID de terminal TWINT',
            'TWINT Order ID', 'TWINT order ID', 'ID de commande TWINT',
        ];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
