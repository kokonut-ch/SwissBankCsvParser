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
            AmountModel::SignedColumn,
            AmountModel::InvertedSignedColumn => [Term::BookingDate, Term::Description, Term::Amount],
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
            Term::AccountIban,
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
     * Headings that must **all** be present, or the profile does not match at
     * all.
     *
     * For banks identified by a combination rather than by any single unusual
     * name: no one of Cornèr Card's `Card`, `Currency` and `Status` means much,
     * but the three together alongside a date, a description and an amount mean
     * exactly one file.
     *
     * @return list<string>
     */
    protected function requiredHeadings(): array
    {
        return [];
    }

    /**
     * Headings whose presence disqualifies the file.
     *
     * The mirror of {@see requiredHeadings()}, and occasionally the only honest
     * discriminator there is. Migros Bank and Viseca ship near-identical card
     * exports; Viseca's carries both `CardId` and `StateType`, Migros Bank's
     * only `CardId`. Signing on what is present cannot separate them — Migros
     * Bank has to say what must be *absent*.
     *
     * @return list<string>
     */
    protected function excludedHeadings(): array
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

    /**
     * Terms that may legitimately appear in more than one column, whose values
     * are joined back together in column order.
     *
     * @return list<Term>
     */
    protected function joinableTerms(): array
    {
        return [Term::Description];
    }

    /**
     * Whether this profile refuses to match unless one of its signatures is
     * present.
     *
     * Turn it on for any bank whose column vocabulary is entirely ordinary —
     * date, description, debit, credit, balance. Without it, such a profile
     * matches every other bank's file just as well as its own, and two banks
     * end up deciding by a tie-break rather than by evidence. Better to fall
     * through to the generic reader, which at least admits it is guessing.
     */
    protected function requiresSignature(): bool
    {
        return false;
    }

    /** Ceiling for this profile's confidence. The generic fallback lowers it. */
    protected function maxScore(): float
    {
        return 1.0;
    }

    /**
     * How far into a file a heading row may sit.
     *
     * Every preamble observed in the wild is under a dozen rows; this leaves
     * generous room. The bound matters because every profile scans for its own
     * heading, so an unbounded search costs the whole file times the number of
     * banks — on a long statement, minutes.
     */
    private const int HEADING_SEARCH_LIMIT = 64;

    public function match(CsvDocument $document): ?ProfileMatch
    {
        foreach (array_slice($document->rows, 0, self::HEADING_SEARCH_LIMIT, true) as $index => $row) {
            $map = $this->mapHeadings($row, $index);

            if ($map === null || ! $this->hasRequiredHeadings($document, $index)) {
                continue;
            }

            [$score, $reasons, $signed] = $this->score($document, $map);

            if ($this->requiresSignature() && ! $signed) {
                return null;
            }

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
        /** @var array<string, list<int>> $terms */
        $terms = [];
        /** @var array<string, array<int, int>> $ranks */
        $ranks = [];
        $extras = [];
        $currency = null;

        foreach ($row as $column => $cell) {
            $heading = Text::normalise($cell);

            if ($heading === '') {
                continue;
            }

            $term = $this->resolveTerm($heading);

            if ($term === null) {
                continue;
            }

            if (in_array($term, $this->joinableTerms(), true)) {
                // Several columns, one term. UBS spreads a description over
                // "Description 1/2/3", and prints two candidate booking dates.
                $terms[$term->value][] = $column;
                $ranks[$term->value][$column] = $this->labelRank($term, $heading);
            } elseif (isset($terms[$term->value])) {
                // First column wins, so a repeated heading later in the row —
                // some exports carry per-currency duplicates — cannot displace it.
                continue;
            } else {
                $terms[$term->value] = [$column];
            }

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

        return new ColumnMap($this->prioritise($terms, $ranks), $extras, $currency, $rowIndex);
    }

    /**
     * Orders the columns of a multi-column term by the order the profile listed
     * its labels, falling back to column order.
     *
     * This is what lets UBS say "the booking date is preferable to the trade
     * date" even though the bank prints the trade date first. Without it, a
     * multi-column term would always be read left to right, which is a property
     * of the file rather than a decision of the profile.
     *
     * @param  array<string, list<int>>  $terms
     * @param  array<string, array<int, int>>  $ranks
     * @return array<string, list<int>>
     */
    private function prioritise(array $terms, array $ranks): array
    {
        foreach ($terms as $term => $columns) {
            if (count($columns) < 2 || ! isset($ranks[$term])) {
                continue;
            }

            $rank = $ranks[$term];

            usort(
                $columns,
                static fn (int $a, int $b): int => ($rank[$a] ?? PHP_INT_MAX) <=> ($rank[$b] ?? PHP_INT_MAX)
                    ?: $a <=> $b,
            );

            $terms[$term] = $columns;
        }

        return $terms;
    }

    /** Position of the heading in the profile's own label list, or last when it has none. */
    private function labelRank(Term $term, string $heading): int
    {
        $accepted = $this->termLabels()[$term->value] ?? null;

        if ($accepted === null) {
            return PHP_INT_MAX;
        }

        [$bare] = Lexicon::splitCurrency($heading);

        foreach ($accepted as $rank => $label) {
            if (Text::equals($bare, $label)) {
                return $rank;
            }
        }

        return PHP_INT_MAX;
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
     * @return array{0: float, 1: list<string>, 2: bool} score, reasons, and whether
     *                                                   a signature was found at all
     */
    private function score(CsvDocument $document, ColumnMap $map): array
    {
        $score = self::SCORE_BASE;
        $reasons = ['all required columns present'];
        $signed = false;

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
                $signed = true;

                break;
            }
        }

        foreach ($this->signatureMetadata() as $signature) {
            if ($document->hasMetadata([$signature])) {
                $score += self::SCORE_SIGNATURE_METADATA;
                $reasons[] = 'header block contains "'.$signature.'"';
                $signed = true;

                break;
            }
        }

        return [min($score, $this->maxScore()), $reasons, $signed];
    }

    private function hasRequiredHeadings(CsvDocument $document, int $rowIndex): bool
    {
        foreach ($this->requiredHeadings() as $heading) {
            if (! $this->headingPresent($document, $rowIndex, $heading)) {
                return false;
            }
        }

        foreach ($this->excludedHeadings() as $heading) {
            if ($this->headingPresent($document, $rowIndex, $heading)) {
                return false;
            }
        }

        return true;
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
            Term::AccountIban,
        ], true);
    }
}
