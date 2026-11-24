<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\UBS;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\ColumnMap;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Value\Amount;

/**
 * UBS account statement with separate debit and credit columns.
 *
 * Covers both the wide portfolio export (twenty-one columns, two-digit years,
 * the account's own IBAN in a column) and the modern e-banking export that
 * splits the two directions.
 *
 * The catch is the description. UBS prints a plain "Description" column holding
 * the *account* name, repeated identically on every row, and puts the actual
 * booking text in "Description 1/2/3". Left to the shared lexicon, every label
 * would start with "UBS Business Current Account". The term is therefore
 * narrowed to the numbered columns.
 */
final class SplitAmountStatementProfile extends HeaderDrivenProfile
{
    use Signatures;
    use StatementRows;

    public function identity(): BankIdentity
    {
        return new BankIdentity('ubs', 'UBS');
    }

    public function id(): string
    {
        return 'ubs.statement.split';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y', 'Y-m-d'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => Lexicon::numbered(['Description', 'Beschreibung', 'Descrizione']),
            Term::BookingDate->value => self::ROW_DATE_HEADINGS,
        ];
    }

    protected function joinableTerms(): array
    {
        return [Term::Description, Term::BookingDate];
    }

    /**
     * A credit keeps the sign UBS prints on it; a debit is always money out.
     *
     * The portfolio export books a reversed credit in the credit column with a
     * minus: "-500" under "Gutschrift", and the running balance drops by 500 on
     * that row. The shared split-column rule discards a printed sign and would
     * report 500 received instead, a thousand francs off on one reversal. The
     * debit column's sign carries nothing to read: the e-banking export prints
     * every debit negative, the portfolio export every debit positive, and no
     * published sample of either reverses a debit.
     *
     * @param  list<string>  $cells
     */
    protected function rowAmount(array $cells, ColumnMap $map): ?string
    {
        $credit = Amount::parse($this->amountCell($cells, $map, Term::Credit));

        if ($credit !== null) {
            return $credit;
        }

        $debit = Amount::parse($this->amountCell($cells, $map, Term::Debit));

        return $debit === null ? null : Amount::negate(Amount::abs($debit));
    }

    /** @param list<string> $cells */
    private function amountCell(array $cells, ColumnMap $map, Term $term): ?string
    {
        $index = $map->indexOf($term);

        return $index === null ? null : ($cells[$index] ?? null);
    }
}
