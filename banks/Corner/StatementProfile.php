<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Corner;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Cornèr Banca account statement.
 *
 * Every row is indented by one empty column, headings included, and each booking
 * is followed by a run of lines carrying its charges, its bank reference and
 * sometimes the counterparty's full address — one line each.
 *
 * Those lines are folded back into the booking above them, which is the only way
 * to keep the reference and the counterparty. It makes for long labels; the
 * alternative is losing most of what the statement says.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('corner', 'Cornèr Banca');
    }

    public function id(): string
    {
        return 'corner.statement';
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
        return ['d/m/Y', 'd.m.Y', 'd/m/y', 'd.m.y'];
    }

    protected function signatureHeadings(): array
    {
        // The newer layout (2024) drops the account number from the heading
        // row, so each language needs something else to be recognised by. For
        // German that is "Erfassungsdatum" and for English "Registration
        // date"; the Italian file's date heading, "Data registrazione", is no
        // use — EFG prints it too — but its "Dettaglio" column is Cornèr's
        // own. Without a per-language signature, one language was rejected
        // while its siblings were accepted.
        return [
            'Conto No.', 'Konto-Nr.', 'Compte No.', 'Account No.',
            'Erfassungsdatum', 'Dettaglio', 'Registration date',
        ];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
