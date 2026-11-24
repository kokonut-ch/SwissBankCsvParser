<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\MigrosBank;

use Kokonut\SwissBankCsvParser\Banks\Generic\FourColumnStatement;
use Kokonut\SwissBankCsvParser\Csv\CsvDocument;
use Kokonut\SwissBankCsvParser\Csv\Text;
use Kokonut\SwissBankCsvParser\Dto\Account;
use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\ColumnMap;
use Kokonut\SwissBankCsvParser\Profiles\HeaderBlock;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;
use Kokonut\SwissBankCsvParser\Profiles\ProfileMatch;
use Kokonut\SwissBankCsvParser\Value\Iban;

/**
 * Migros Bank account statement, in German, French and Italian.
 *
 * The bank has shipped two layouts, and this profile reads both, with very
 * different confidence.
 *
 * The current one names a message column and a reference-number column
 * between the booking text and the amount:
 *
 *     Datum;Buchungstext;Mitteilung;Referenznummer;Betrag;[Saldo;]Valuta
 *
 * No other Swiss export in the published samples prints that pair, so it is
 * what identifies the file.
 *
 * The older one is four columns, `Datum;Buchungstext;Betrag;Valuta`, under a
 * `Kontonummer:` / `Bezeichnung:` / `Saldo:` preamble. Valiant, acrevis,
 * Schwyzer KB, Nidwaldner KB and Schaffhauser KB print exactly the same thing,
 * so nothing in the file says Migros Bank. That layout is matched, so that a
 * caller who already knows the bank can read it, but always below the generic
 * reader: in free detection it stays a file whose bank has to be named, and it
 * is read as Migros Bank only when the caller restricts the parser to this
 * bank. Its headings, its preamble and its ceiling are
 * {@see FourColumnStatement}'s, which this profile extends with the current
 * layout's and the other languages'.
 *
 * In both layouts the `Bezeichnung` line of the preamble is the account's
 * designation ("Privatkonto", "Compte courant"), not its holder. It stays in
 * the metadata and nowhere else.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    /**
     * The message column, in the three languages of the current layout.
     *
     * @var list<string>
     */
    private const array MESSAGE_HEADINGS = ['Mitteilung', 'Comunicazione', 'Message'];

    public function identity(): BankIdentity
    {
        return new BankIdentity('migrosbank', 'Migros Bank');
    }

    public function id(): string
    {
        return 'migrosbank.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::SignedColumn;
    }

    /**
     * Rows are dated `dd.mm.yy` in the oldest exports and `dd.mm.yyyy` since.
     * The ISO notation is for the preamble alone: the current layout announces
     * its period as `Kontoauszug von:;2026-01-01`.
     */
    protected function dateFormats(): array
    {
        return [...FourColumnStatement::DATE_FORMATS, 'Y-m-d'];
    }

    protected function optionalTerms(): array
    {
        return [Term::ValueDate, Term::Reference, Term::Balance];
    }

    /**
     * Narrowed to the bank's own headings, so that the profile does not read
     * every signed-amount export in the country.
     *
     * The four-column layout's German headings come first, then the other
     * languages'. The message columns are listed after the booking texts,
     * which is what puts the booking text first in the label whatever the
     * column order.
     */
    protected function termLabels(): array
    {
        $layout = FourColumnStatement::termLabels();

        return [
            Term::BookingDate->value => [...$layout[Term::BookingDate->value], 'Data', 'Date'],
            Term::Description->value => [
                ...$layout[Term::Description->value],
                'Testo di registrazione', 'Libellé', ...self::MESSAGE_HEADINGS,
            ],
            Term::Amount->value => [...$layout[Term::Amount->value], 'Importo', 'Montant'],
            // "Valuta" is the value date here, in Italian too.
            Term::ValueDate->value => [...$layout[Term::ValueDate->value], 'Valeur'],
            Term::Reference->value => ['Referenznummer', 'Numero di riferimento', 'Numéro de référence'],
            Term::Balance->value => [...$layout[Term::Balance->value], 'Solde'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return self::MESSAGE_HEADINGS;
    }

    /**
     * No holder on purpose. The `Bezeichnung` / `Denominazione` / `Description`
     * line names the kind of account, never the person who holds it.
     *
     * The French and Italian account labels are assumed by analogy with the
     * German one: the published samples in those languages omit the line.
     */
    protected function headerBlock(): HeaderBlock
    {
        $layout = FourColumnStatement::headerBlock();

        return new HeaderBlock(
            account: [
                'Kontonummer / IBAN', ...$layout->account,
                'Numéro de compte / IBAN', 'Numéro de compte',
                'Numero di conto / IBAN', 'Numero di conto',
            ],
            // "Saldo: CHF 38547.70" in the older layout. The current one prints
            // the label with an empty value, and then the file names no currency.
            currency: [...$layout->currency, 'Solde'],
            from: ['Kontoauszug von', 'Relevé de compte de', 'Estratto conto da'],
            to: [...$layout->to, "Relevé de compte jusqu'au", 'Estratto conto fino'],
        );
    }

    /**
     * The current layout is recognised on its message and reference columns
     * together. Anything else this profile can read is the shared four-column
     * layout, and is capped below the generic reader.
     */
    public function match(CsvDocument $document): ?ProfileMatch
    {
        $match = parent::match($document);

        if ($match === null || $this->isCurrentLayout($document, $match->columns)) {
            return $match;
        }

        return FourColumnStatement::cap($match, 'Valiant, acrevis, Schwyzer KB and Nidwaldner KB');
    }

    /**
     * "Kontonummer / IBAN: 123.456.78 / CH93 0076 2011 6238 5295 7" prints the
     * account number and the IBAN on one line. Taken whole, the value is
     * neither, so it is split: the IBAN when it is a valid one, otherwise the
     * number.
     */
    public function extract(CsvDocument $document, ProfileMatch $match): ParsedFile
    {
        $file = parent::extract($document, $match);
        $line = $document->metadataValue($this->headerBlock()->account);

        if ($line === null || ! str_contains($line, '/')) {
            return $file;
        }

        $parts = array_map(trim(...), explode('/', $line, 2));
        $number = $parts[0];
        $iban = Iban::parse($parts[1] ?? '') ?? Iban::parse($number);

        return new ParsedFile(
            bank: $file->bank,
            profile: $file->profile,
            account: new Account(
                iban: $iban,
                number: $iban === null && $number !== '' ? $number : null,
                currency: $file->account->currency,
                holder: $file->account->holder,
            ),
            period: $file->period,
            rows: $file->rows,
            metadata: $file->metadata,
            warnings: $file->warnings,
        );
    }

    private function isCurrentLayout(CsvDocument $document, ColumnMap $columns): bool
    {
        if (! $columns->has(Term::Reference)) {
            return false;
        }

        foreach ($document->row($columns->headerRow) as $cell) {
            foreach (self::MESSAGE_HEADINGS as $heading) {
                if (Text::equals($cell, $heading)) {
                    return true;
                }
            }
        }

        return false;
    }
}
