<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\EFG;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * EFG Bank account statement.
 *
 * Italian headings, day-first slashed dates, one signed amount column read at
 * face value — a negative amount is money out, unlike the card statements
 * elsewhere in this package.
 *
 * Identified by a column called `DIV`, which is as unusual a heading as Swiss
 * banking produces.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('efg', 'EFG Bank');
    }

    public function id(): string
    {
        return 'efg.statement';
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
        return ['d/m/Y', 'd.m.Y'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => ['Transazione', 'Descrizione'],
            Term::Currency->value => ['DIV'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['DIV'];
    }

    protected function requiredHeadings(): array
    {
        return ['DIV'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
