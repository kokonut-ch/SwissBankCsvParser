<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\Cler;

use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Detection\DetectionReport;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Lexicon\Lexicon;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;

/**
 * Bank Cler account statement, in Italian, French, English and German.
 *
 * Ordinary in every respect but two: the currency is written in brackets after
 * each amount heading ("Importo di addebito (CHF)", "Belastungsbetrag (USD)"),
 * which is where the account currency comes from, and every row is classified
 * by an order type.
 *
 * In Italian, French and English the order type heading ("Tipo di ordine",
 * "Type d'ordre", "Order type") is printed by no other Swiss bank, and it
 * identifies the file.
 *
 * The German export does not identify itself:
 *
 *     Buchungsdatum;[Valutadatum;]Auftragsart;Text;Belastungsbetrag (CHF);Gutschriftsbetrag (CHF);Saldo (CHF)
 *
 * is, heading for heading, what Basler Kantonalbank exports, Bank Cler's
 * parent. That layout is read, so that a caller who already knows the bank can
 * read it, but always below the generic reader: in free detection it stays a
 * file whose bank has to be named, and it is read as Bank Cler only when the
 * caller restricts the parser to this bank.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    /**
     * The German layout's ceiling. Below the generic reader's 0.30 on purpose,
     * so it can never win a free detection, and far below
     * {@see DetectionReport::isConfident()}.
     */
    private const float SHARED_LAYOUT_SCORE = 0.25;

    /**
     * Order type headings no other bank prints.
     *
     * @var list<string>
     */
    private const array OWN_ORDER_TYPE_HEADINGS = ['Tipo di ordine', "Type d'ordre", 'Order type'];

    /** The German order type heading, which Basler Kantonalbank and Thurgauer Kantonalbank print too. */
    private const string GERMAN_ORDER_TYPE_HEADING = 'Auftragsart';

    /**
     * The German amount headings, both required beside the order type. They are
     * what keeps Thurgauer Kantonalbank's `Belastung (CHF)` / `Gutschrift (CHF)`
     * export, which also prints `Auftragsart`, out of this profile.
     *
     * @var list<string>
     */
    private const array GERMAN_AMOUNT_HEADINGS = ['Belastungsbetrag', 'Gutschriftsbetrag'];

    public function identity(): BankIdentity
    {
        return new BankIdentity('cler', 'Bank Cler');
    }

    public function id(): string
    {
        return 'cler.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y'];
    }

    protected function signatureHeadings(): array
    {
        return [...self::OWN_ORDER_TYPE_HEADINGS, self::GERMAN_ORDER_TYPE_HEADING];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }

    /**
     * The Italian, French and English layouts are identified on their order
     * type heading. Signed only by the German one, the file is read when it
     * carries the bank's own amount headings, and capped below the generic
     * reader.
     */
    public function match(CsvDocument $document): ?ProfileMatch
    {
        $match = parent::match($document);

        if ($match === null) {
            return null;
        }

        $headerRow = $match->columns->headerRow;

        if ($this->hasAnyHeading($document, $headerRow, self::OWN_ORDER_TYPE_HEADINGS)) {
            return $match;
        }

        foreach (self::GERMAN_AMOUNT_HEADINGS as $heading) {
            if (! $this->hasAnyHeading($document, $headerRow, [$heading])) {
                return null;
            }
        }

        return new ProfileMatch(
            min($match->score, self::SHARED_LAYOUT_SCORE),
            [...$match->reasons, 'German layout shared with Basler Kantonalbank: the bank is not named'],
            $match->columns,
        );
    }

    /**
     * Whether one of the headings is printed in the heading row, any bracketed
     * currency aside.
     *
     * @param  list<string>  $headings
     */
    private function hasAnyHeading(CsvDocument $document, int $headerRow, array $headings): bool
    {
        foreach ($document->row($headerRow) as $cell) {
            [$bare] = Lexicon::splitCurrency($cell);

            foreach ($headings as $heading) {
                if (Text::equals($bare, $heading)) {
                    return true;
                }
            }
        }

        return false;
    }
}
