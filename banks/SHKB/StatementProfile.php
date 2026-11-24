<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\SHKB;

use Kokonut\SwissBankCsvParser\Banks\Generic\FourColumnStatement;
use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * Schaffhauser Kantonalbank account statement.
 *
 * The bank exports the four-column statement that Migros Bank's older layout,
 * Valiant, acrevis, Schwyzer KB, Nidwaldner KB and Zuger KB print too, preamble
 * included, so nothing in the file names it:
 *
 *     Kontoauszug bis: 30.09.2026 ;;;
 *     Kontonummer: 123.456-7 890;;;
 *     Bezeichnung: Kontokorrent BUSINESS CHF;;;
 *     Saldo: CHF 8450.35;;;
 *     <holder and address lines>
 *     Datum;Buchungstext;Betrag;Valuta
 *
 * The layout is {@see FourColumnStatement}'s, read the way it says: this
 * profile never wins a free detection, where the file stays one whose bank has
 * to be named, and reads it as Schaffhauser Kantonalbank once the caller
 * restricts the parser to this bank.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('shkb', 'Schaffhauser Kantonalbank');
    }

    public function id(): string
    {
        return 'shkb.statement';
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
        return FourColumnStatement::DATE_FORMATS;
    }

    protected function optionalTerms(): array
    {
        return [Term::ValueDate, Term::Balance];
    }

    protected function termLabels(): array
    {
        return FourColumnStatement::termLabels();
    }

    protected function headerBlock(): HeaderBlock
    {
        return FourColumnStatement::headerBlock();
    }

    /**
     * The layout, heading for heading, and nothing more: always capped below
     * the generic reader.
     */
    public function match(CsvDocument $document): ?ProfileMatch
    {
        $match = parent::match($document);

        if ($match === null || ! FourColumnStatement::isLayout($document, $match->columns)) {
            return null;
        }

        return FourColumnStatement::cap(
            $match,
            'Migros Bank, Valiant, acrevis, Schwyzer KB, Nidwaldner KB and Zuger KB',
        );
    }
}
