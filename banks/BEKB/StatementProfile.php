<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\BEKB;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Berner Kantonalbank account statement.
 *
 * The most talkative of the cantonal exports, and the only one that is
 * unmistakable: it gives the counterparty a name, an address and an account of
 * their own, and prefixes every row with a "Gutschrift / Belastung" column
 * spelling out the direction in a sentence.
 *
 * The direction column is not read — the amount is already signed — but it does
 * identify the file, which is what makes this bank claimable where most of its
 * peers are not.
 *
 * Description, counterparty name and message are joined, because a statement
 * line here is only meaningful with all three.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('bekb', 'Berner Kantonalbank');
    }

    public function id(): string
    {
        return 'bekb.statement';
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
        return ['d.m.Y', 'd.m.y'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => [
                'Buchungstext',
                'Zusatzinfos Buchung',
                'Name Auftraggeber / Begünstigter',
                'Mitteilung / Referenz',
                'Zusatzinfos Transaktion',
            ],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Gutschrift / Belastung', 'Zusatzinfos Buchung', 'Zusatzinfos Transaktion'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
