<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\ZKB;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Zürcher Kantonalbank account statement.
 *
 * ZKB has shipped at least five layouts over the years, all quoted, all German,
 * all built from the same vocabulary — which is why one profile reads them
 * rather than five: the column names are what identify them, not their order.
 *
 * Two habits of this export matter:
 *
 * - the description is spread over up to four columns ("Buchungstext",
 *   "Zahlungszweck" twice, and "Details"), and they are joined back together;
 * - a booking spills onto the following line, which carries only more text.
 *   Those lines are folded into the row above.
 *
 * Its oldest six-column layout, `Datum;Buchungstext;Konto;Whg;Belastung;Gutschrift`,
 * carries nothing that names ZKB. It is deliberately **not** claimed here and
 * falls through to the generic reader, which says it is guessing.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('zkb', 'Zürcher Kantonalbank');
    }

    public function id(): string
    {
        return 'zkb.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y'];
    }

    /**
     * "ZKB-Referenz" and nothing else. "Betrag Detail" looks tempting — it is
     * the per-item amount of a collective booking — but Luzerner Kantonalbank
     * prints a column by that name too, so signing on it would have this
     * profile claiming LUKB's statements. Every ZKB layout that carries
     * "Betrag Detail" carries "ZKB-Referenz" as well, so nothing is lost.
     */
    protected function signatureHeadings(): array
    {
        return ['ZKB-Referenz'];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }

    /**
     * The bank's own reference comes first, so it wins over the generic
     * "Referenznummer" column that sits next to it and is usually empty.
     */
    protected function termLabels(): array
    {
        return [
            Term::Reference->value => ['ZKB-Referenz', 'Referenznummer'],
        ];
    }
}
