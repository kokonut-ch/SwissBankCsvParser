<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Neon;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * neon account statement.
 *
 * A short, quoted, ISO-dated export with one signed amount column. Its own
 * product features name it outright: no other bank prints a "Spaces" or a
 * "Wise" column.
 *
 * Note what it does *not* say: the file names no account currency anywhere.
 * "Original currency" is the currency of a foreign purchase, not of the
 * account, and reporting it as such would be wrong on every domestic row. The
 * currency is therefore left null, with the usual warning.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('neon', 'neon');
    }

    public function id(): string
    {
        return 'neon.statement';
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
        return ['Y-m-d'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => ['Description', 'Subject'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return ['Spaces', 'Wise'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
