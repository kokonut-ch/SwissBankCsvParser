<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\PostFinance;

use Kokonut\SwissBankCsvParser\Detection\DetectionReport;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\PositionalProfile;

/**
 * The older PostFinance statement, which has no heading row at all:
 *
 *     date ; description ; credit ; debit ; value date ; balance
 *
 * With nothing naming the bank, this profile cannot be certain, and it does not
 * pretend to be — it scores low enough that any bank that does name itself wins,
 * and low enough that {@see DetectionReport::isConfident()}
 * stays false. Treat a match here as a suggestion to confirm with the user, not
 * as an identification.
 *
 * Widths are pinned to 4–7 columns to keep it from claiming every headerless
 * CSV that happens to start with a date.
 */
final class LegacyStatementProfile extends PositionalProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('postfinance', 'PostFinance');
    }

    public function id(): string
    {
        return 'postfinance.legacy';
    }

    public function priority(): int
    {
        return 90;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y', 'd.m.y'];
    }

    protected function positions(): array
    {
        return [
            Term::BookingDate->value => 0,
            Term::Description->value => 1,
            Term::Credit->value => 2,
            Term::Debit->value => 3,
            Term::ValueDate->value => 4,
            Term::Balance->value => 5,
        ];
    }

    protected function columnCounts(): array
    {
        return [4, 6, 7];
    }

    protected function headerBlock(): HeaderBlock
    {
        return new HeaderBlock(
            account: ['Compte', 'Konto', 'Conto', 'Account'],
            currency: ['Monnaie', 'Währung', 'Moneta', 'Currency'],
        );
    }
}
