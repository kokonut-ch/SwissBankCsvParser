<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Lexicon\Term;

/**
 * For exports that name their columns — which, since roughly 2020, is most of
 * them.
 *
 * A subclass normally only has to declare its identity and its distinguishing
 * headings; the shared {@see Lexicon} already knows what Swiss banks call a
 * date, a description or a credit column in four languages.
 *
 * Where the shared vocabulary is too broad to tell two of a bank's own formats
 * apart, override {@see termLabels()} to narrow a term to the exact headings
 * that format uses. That is the usual reason a profile needs more than a dozen
 * lines.
 */
abstract class HeaderDrivenProfile extends Profile
{
    /** All required terms present. Enough to read the file, not enough to be sure of the bank. */
    private const float SCORE_BASE = 0.45;

    /** Each optional term the file also has, up to SCORE_OPTIONAL_CAP. */
    private const float SCORE_OPTIONAL_STEP = 0.05;

    private const float SCORE_OPTIONAL_CAP = 0.20;

    /** A heading only this bank prints. The strongest single signal there is. */
    private const float SCORE_SIGNATURE_HEADING = 0.25;

    /** A label only this bank puts in its header block. */
    private const float SCORE_SIGNATURE_METADATA = 0.10;

    /**
     * Columns without which the file cannot be read. Defaults to the dates,
     * the description and whichever amount columns the amount model needs.
     *
     * @return list<Term>
     */
    protected function requiredTerms(): array
    {
        return match ($this->amountModel()) {
            AmountModel::SplitColumns => [Term::BookingDate, Term::Description, Term::Credit, Term::Debit],
            AmountModel::SignedColumn => [Term::BookingDate, Term::Description, Term::Amount],
        };
    }

    /**
     * Columns worth reading when present. Each one found also raises confidence.
     *
     * @return list<Term>
     */
    protected function optionalTerms(): array
    {
        return [
            Term::ValueDate,
            Term::Balance,
            Term::Reference,
            Term::Currency,
            Term::Category,
            Term::TransactionType,
        ];
    }

    /**
     * Narrows a term to an explicit set of headings for this profile only.
     *
     * Use it when the shared lexicon would let a sibling format match too — for
     * instance when a bank's account statement and its card statement both have
     * a description column but call it something different.
     *
     * @return array<string, list<string>> Term value => accepted headings
     */
    protected function termLabels(): array
    {
        return [];
    }

    /**
     * Headings that, on their own, say which bank produced the file.
     *
     * @return list<string>
     */
    protected function signatureHeadings(): array
    {
        return [];
    }

    /**
     * Header-block labels that, on their own, say which bank produced the file.
     *
     * @return list<string>
     */
    protected function signatureMetadata(): array
    {
        return [];
    }

    /** Ceiling for this profile's confidence. The generic fallback lowers it. */
    protected function maxScore(): float
    {
        return 1.0;
    }

    public function match(CsvDocument $document): ?ProfileMatch
    {
        foreach ($document->rows as $index => $row) {
            $map = $this->mapHeadings($row, $index);

            if ($map === null) {
                continue;
            }

            [$score, $reasons] = $this->score($document, $map);

            return new ProfileMatch($score, $reasons, $map);
        }

        return null;
    }

    /**
     * Builds a column map from one candidate heading row, or null when that row
     * does not carry every required column.
     *
     * @param  list<string>  $row
     */
    private function mapHeadings(array $row, int $rowIndex): ?ColumnMap
    {
        $terms = [];
        $extras = [];
        $currency = null;

        foreach ($row as $column => $cell) {
            $heading = Text::normalise($cell);

            if ($heading === '') {
                continue;
            }

            $term = $this->resolveTerm($heading);

            // First column wins, so a repeated heading later in the row — some
            // exports carry per-currency duplicates — cannot displace it.
            if ($term === null || isset($terms[$term->value])) {
                continue;
            }

            $terms[$term->value] = $column;

            if (in_array($term, [Term::Credit, Term::Debit, Term::Amount], true)) {
                $currency ??= Lexicon::currencyIn($heading);
            }

            if (! self::isCore($term)) {
                $extras[$heading] = $column;
            }
        }

        foreach ($this->requiredTerms() as $required) {
            if (! isset($terms[$required->value])) {
                return null;
            }
        }

        return new ColumnMap($terms, $extras, $currency, $rowIndex);
    }

    private function resolveTerm(string $heading): ?Term
    {
        $overrides = $this->termLabels();

        foreach ([...$this->requiredTerms(), ...$this->optionalTerms()] as $term) {
            $accepted = $overrides[$term->value] ?? null;

            if ($accepted === null) {
                if (Lexicon::matches($heading, $term)) {
                    return $term;
                }

                continue;
            }

            [$bare] = Lexicon::splitCurrency($heading);

            foreach ($accepted as $label) {
                if (Text::equals($bare, $label)) {
                    return $term;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: float, 1: list<string>}
     */
    private function score(CsvDocument $document, ColumnMap $map): array
    {
        $score = self::SCORE_BASE;
        $reasons = ['all required columns present'];

        $optional = 0;

        foreach ($this->optionalTerms() as $term) {
            if ($map->has($term)) {
                $optional++;
            }
        }

        if ($optional > 0) {
            $score += min($optional * self::SCORE_OPTIONAL_STEP, self::SCORE_OPTIONAL_CAP);
            $reasons[] = $optional.' optional column(s) recognised';
        }

        foreach ($this->signatureHeadings() as $signature) {
            if ($this->headingPresent($document, $map->headerRow, $signature)) {
                $score += self::SCORE_SIGNATURE_HEADING;
                $reasons[] = 'distinctive heading "'.$signature.'"';

                break;
            }
        }

        foreach ($this->signatureMetadata() as $signature) {
            if ($document->hasMetadata([$signature])) {
                $score += self::SCORE_SIGNATURE_METADATA;
                $reasons[] = 'header block contains "'.$signature.'"';

                break;
            }
        }

        return [min($score, $this->maxScore()), $reasons];
    }

    private function headingPresent(CsvDocument $document, int $rowIndex, string $signature): bool
    {
        foreach ($document->row($rowIndex) as $cell) {
            [$bare] = Lexicon::splitCurrency(Text::normalise($cell));

            if (Text::equals($bare, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** Terms the neutral row model has a field for; everything else becomes an extra. */
    private static function isCore(Term $term): bool
    {
        return in_array($term, [
            Term::BookingDate,
            Term::ValueDate,
            Term::Description,
            Term::Credit,
            Term::Debit,
            Term::Amount,
            Term::Balance,
            Term::Reference,
            Term::Currency,
        ], true);
    }
}
