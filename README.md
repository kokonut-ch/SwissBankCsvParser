<div align="center">
    <h1>Swiss Bank CSV Parser</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/kokonut-ch/swiss-bank-csv-parser"><img src="https://img.shields.io/packagist/v/kokonut-ch/swiss-bank-csv-parser.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/kokonut-ch/swiss-bank-csv-parser"><img src="https://img.shields.io/packagist/php-v/kokonut-ch/swiss-bank-csv-parser.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://github.com/Kokonut-ch/SwissBankCsvParser/actions"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/Kokonut-ch/SwissBankCsvParser/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/kokonut-ch/swiss-bank-csv-parser"><img src="https://img.shields.io/packagist/dt/kokonut-ch/swiss-bank-csv-parser.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Every Swiss bank exports account statements as CSV, and every one of them does it
differently: four languages of column headings, credit and debit either split across two
columns or merged into one signed column, Latin-1 files, apostrophes for thousands, and a
handful of banks still shipping a format with no headings at all.

This package reads all of that and gives you one predictable structure back.

**Its remit stops there.** It identifies the exporting bank, maps the columns, and reports
dates, labels and exact decimal amounts. What those rows *mean*, and what should happen to
them, is entirely yours.

## Installation

```bash
composer require kokonut-ch/swiss-bank-csv-parser
```

There is nothing to publish and nothing to configure.

## Usage

```php
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

$file = (new SwissBankCsvParser)->parse($csvContents);
// or ->parseFile('/path/to/statement.csv')

$file->bank->name;          // 'PostFinance'
$file->profile;             // 'postfinance.efinance'
$file->account->iban;       // 'CH9300762011623852957'
$file->account->currency;   // 'CHF'
$file->period->from;        // DateTimeImmutable|null — the period the file announces

foreach ($file as $row) {
    $row->date;        // DateTimeImmutable
    $row->valueDate;   // DateTimeImmutable|null
    $row->label;       // 'Paiement fournisseur'
    $row->amount;      // '-150.50' — signed decimal string, negative means money out
    $row->currency;    // 'CHF'
    $row->balance;     // '8759.45' or null
    $row->reference;   // whatever the bank printed, if anything
    $row->extras;      // ['Catégorie' => 'Achats'] — recognised, but outside the core model
    $row->raw;         // the original cells, so nothing is ever lost
}
```

In a Laravel application the service provider and the `SwissBankCsvParser` facade are
registered automatically. Outside Laravel, instantiate the class directly — the parser has
no framework dependencies.

### Identifying the bank before parsing

`detect()` returns every profile that recognises the file, best first, without extracting a
single row. It is cheap enough to run on upload.

```php
$report = (new SwissBankCsvParser)->detect($csvContents);

$report->best()->bank->name;   // 'PostFinance'
$report->best()->score;        // 0.90
$report->best()->reasons;      // ['distinctive heading "Texte de notification"', …]

if (! $report->isConfident()) {
    // Several profiles could read this, or none stands out.
    // A good moment to ask whoever uploaded it.
}
```

## What you can rely on

**Amounts are exact decimal strings, never floats.** A parser has no business rounding
money. `'-150.50'` stays `'-150.50'`, at the scale the bank printed. Feed it to whatever
decimal type you already use.

**A negative amount means money left the account.** Files that split credit and debit into
two columns are normalised to one signed amount. The column decides the direction — some
banks *also* print debits as negatives, and honouring both would flip the sign twice.

**`null` means the file did not say.** No currency anywhere in the file gives you
`currency === null` and a `CURRENCY_NOT_DETECTED` warning, not a plausible-looking guess.
Defaults are your decision, and you are better placed to make them.

**Rows come back in file order.** Sorting is a decision too.

**Nothing is dropped.** A line carrying a balance but no movement is reported with
`amount === null` rather than discarded. Columns outside the core model land in `extras`,
and the untouched cells are always in `raw`.

**Warning codes are stable.** Match on `Warning::CURRENCY_NOT_DETECTED`, never on the
message text.

## Supported banks

| Bank | Formats | Recognised by |
| --- | --- | --- |
| PostFinance | e-finance account statement (2024+), credit card statement, legacy headerless statement | column headings, header block |
| *any other bank* | generic fallback: split credit/debit, or one signed column | shared column vocabulary |

The generic fallback reads statements from banks this package does not know yet, as long as
they use ordinary column names in German, French, Italian or English — which most do. It
never outranks a bank that identifies itself, and it always attaches a
`GENERIC_PROFILE_USED` warning, so you can tell a guess from an identification.

## Adding a bank

Each bank lives in its own directory under [`banks/`](banks/), holding its profiles, its
fixtures and its tests. Adding one touches nothing else — see
[`banks/README.md`](banks/README.md).

Pull requests adding a bank are very welcome. Please read the note there about fixtures
first: **real bank exports must never be committed**, not even anonymised.

## Testing

```bash
composer test        # static analysis, formatting, type coverage, tests
composer test:unit   # tests only
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security
vulnerabilities.

## Credits

- [cbuzas](https://github.com/Kokonut-ch)
- [All Contributors](../../contributors)

See [NOTICE](NOTICE) regarding the file formats this package was built to read.

## License

Swiss Bank CSV Parser is open-sourced software licensed under the [MIT license](LICENSE.md).
