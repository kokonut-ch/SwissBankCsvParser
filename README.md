<p>
    <img src="art/logo-white.svg" alt="Kokonut" width="240" align="left">
    <img src="art/open-source.svg" alt="Open source, MIT licensed" width="150" align="right">
</p>

<br clear="both">

---

# Swiss Bank CSV Parser

<p >
    <a href="https://packagist.org/packages/kokonut-ch/laravel-swiss-bank-csv-parser"><img src="https://img.shields.io/packagist/v/kokonut-ch/laravel-swiss-bank-csv-parser.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/kokonut-ch/laravel-swiss-bank-csv-parser"><img src="https://img.shields.io/packagist/php-v/kokonut-ch/laravel-swiss-bank-csv-parser.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://github.com/Kokonut-ch/SwissBankCsvParser/actions"><img alt="Tests" src="https://img.shields.io/github/actions/workflow/status/Kokonut-ch/SwissBankCsvParser/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/kokonut-ch/laravel-swiss-bank-csv-parser"><img src="https://img.shields.io/packagist/dt/kokonut-ch/laravel-swiss-bank-csv-parser.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Every Swiss bank exports account statements as CSV, and every one of them does it
differently: four languages of column headings, credit and debit either split across two
columns or merged into one signed column, Latin-1 files, apostrophes for thousands, card
statements that print a purchase as a positive number, and a handful of banks still
shipping a format with no headings at all.

This package reads all of that and gives you one predictable structure back.

**Its remit stops there.** It identifies the exporting bank, maps the columns, and reports
dates, labels and exact decimal amounts. What those rows *mean*, and what should happen to
them, is entirely yours.

---

# Installation

```bash
composer require kokonut-ch/laravel-swiss-bank-csv-parser
```

There is nothing to publish and nothing to configure.

---

# Usage

```php
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

$file = (new SwissBankCsvParser)->parse($csvContents);
// or ->parseFile('/path/to/statement.csv')

$file->bank->name;          // 'PostFinance'
$file->profile;             // 'postfinance.efinance'
$file->account->iban;       // 'CH9300762011623852957'
$file->account->currency;   // 'CHF'
$file->period->from;        // DateTimeImmutable|null, the period the file announces

foreach ($file as $row) {
    $row->date;        // DateTimeImmutable
    $row->valueDate;   // DateTimeImmutable|null
    $row->label;       // 'Paiement fournisseur'
    $row->amount;      // '-150.50', signed decimal string, negative means money out
    $row->currency;    // 'CHF'
    $row->balance;     // '8759.45' or null
    $row->reference;   // whatever the bank printed, if anything
    $row->extras;      // ['Catégorie' => 'Achats'], recognised but outside the core model
    $row->raw;         // the original cells, so nothing is ever lost
}
```

In a Laravel application the service provider and the `SwissBankCsvParser` facade are
registered automatically. Outside Laravel, instantiate the class directly, the parser has
no framework dependencies.

## Identifying the bank before parsing

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

---

# What you can rely on

## Amounts are exact decimal strings, never floats

A parser has no business rounding money. `'-150.50'` stays `'-150.50'`, at the scale the
bank printed. Feed it to whatever decimal type you already use.

## A negative amount means money left the account

Files that split credit and debit into two columns are normalised to one signed amount. The
column decides the direction, because some banks *also* print debits as negatives and
honouring both would flip the sign twice.

## `null` means the file did not say

No currency anywhere in the file gives you `currency === null` and a
`CURRENCY_NOT_DETECTED` warning, not a plausible-looking guess. Defaults are your decision,
and you are better placed to make them.

## Rows come back in file order

Sorting is a decision too.

## Nothing is dropped

A line carrying a balance but no movement is reported with `amount === null` rather than
discarded. An amount cell holding a spreadsheet formula is read as `'0'` with a
`Warning::AMOUNT_IS_FORMULA` rather than guessed at. Columns outside the core model land in
`extras`, and the untouched cells are always in `raw`.

## Warning codes are stable

Match on `Warning::CURRENCY_NOT_DETECTED`, never on the message text.

---

# Untrusted files

Statements arrive from outside, uploaded by a user or pulled from a mailbox, so the parser
treats every byte as hostile input. It executes nothing, evaluates nothing, and reads no
path that the file names. Adversarial input has
[its own test suite](tests/Feature/HostileInputTest.php): formula payloads, control
characters, unterminated quotes, and patterns designed to make a regular expression
backtrack.

## A formula in an amount column is read as zero, and reported

`=1+1`, `@SUM(…)`, `=cmd|'/c calc'!A0`: none of them is a number, so none of them becomes
one. The row keeps its place with `amount === '0'`, and the file carries a
`Warning::AMOUNT_IS_FORMULA` naming the line and quoting the original text.

Zero rather than `null` on purpose. `null` is what a balance line looks like, and a rejected
value is not that. Zero cannot be mistaken for a booking, cannot quietly change a total, and
cannot pass unnoticed while the warning is there.

A signed amount is never mistaken for a formula. `+1200.00` and `-1200.00` are amounts, and
the check requires both a formula-opening character *and* a value that will not read as a
number.

## Text columns are yours to handle

A description may legitimately contain `=cmd|'/c calc'!A0`, and this package hands it back
exactly as written, because a parser that silently rewrites its input is worse than one that
does not. If you re-export that value to CSV or XLSX, escape it, otherwise you have built a
spreadsheet-formula injection into someone else's machine.

---

# Supported banks

| Bank | Formats | Verified against a real export |
| --- | --- | --- |
| PostFinance | e-finance account statement (2024+), credit card statement, legacy headerless statement | ✅ |
| UBS | account statement (split debit/credit), e-banking statement (signed amount), credit card statement | ✅ |
| Raiffeisen | account statement, legacy statement | ✅ |
| Zürcher Kantonalbank | account statement | ⬜ |
| Banque Cantonale Vaudoise | account statement | ⬜ |
| Berner Kantonalbank | account statement | ⬜ |
| Banca dello Stato del Cantone Ticino | account statement | ⬜ |
| Bank Cler | account statement | ⬜ |
| Cornèr Banca | account statement | ⬜ |
| Yuh | account statement | ⬜ |
| neon | account statement | ⬜ |
| Migros Bank | card transactions | ⬜ |
| Viseca | card transactions | ✅ |
| Swisscard | card statement | ⬜ |
| Cornèrcard | card statement | ✅ |
| TWINT | merchant transaction report | ✅ |
| EFG Bank | account statement | ⬜ |
| Hypo Vorarlberg (AT) | account statement | ⬜ |
| *any other bank* | generic fallback: split credit/debit, or one signed column | — |

Profiles marked ⬜ were built from published format documentation rather than from a
file we have held in our hands. They are covered by tests and reviewed, but the distinction
is real and each bank's README repeats it. If one misreads your export, please open an issue
with a **synthetic** reproduction, never the real file.

The generic fallback reads statements from banks this package does not know yet, as long as
they use ordinary column names in German, French, Italian or English, which most do. It
picks up the account number, holder and currency from the header block when there is one,
never outranks a bank that identifies itself, and always attaches a `GENERIC_PROFILE_USED`
warning, so you can tell a guess from an identification.

## Some formats are deliberately left to the generic reader

A profile is only added where the file itself says which bank produced it. Several do not,
and for those the generic reader is the correct answer rather than a stopgap.

**There is, in practice, one de-facto Swiss cantonal-bank format.** A preamble of
`Kontonummer:` / `Bezeichnung:` / `Saldo: CHF …`, then `Datum;Buchungstext;Betrag;Valuta`.
Migros Bank, Valiant, Nidwaldner KB, Schwyzer KB, acrevis and Zuger KB all ship it, and
nothing in any of those files says which one you are holding. A "Migros Bank" profile for
that shape would attribute Valiant's statements to Migros Bank while sounding certain. The
generic reader takes them instead, reads the preamble for the account number, holder and
currency, and reports the bank as unknown.

The same applies to Aargauische KB, Luzerner KB, BKB, BLKB, TKB, CIC, VZ Depotbank and
Baloise Bank SoBa, whose exports differ from one another only in ways too fine to bet on,
to Zürcher Kantonalbank's oldest six-column layout, and to Credit Suisse, whose columns were
entirely ordinary.

If you hold a real export from one of them and it turns out to carry something distinctive
after all, that is exactly the evidence needed to add a profile. See
[`banks/README.md`](banks/README.md).

## Card statements invert the sign

Swisscard, Cornèrcard and Viseca write their statements from the **issuer's** point of view:
a purchase is printed positive, because it is what you owe, and a refund negative. Their
profiles flip it, so that here as everywhere else a negative amount means money left the
cardholder.

This is worth knowing even if you never touch those banks. It is why a card statement must
not be handed to the generic reader, which takes signs at face value and would turn every
charge into income.

---

# Adding a bank

Each bank lives in its own directory under [`banks/`](banks/), holding its profiles, its
fixtures and its tests. Adding one touches nothing else. See
[`banks/README.md`](banks/README.md).

Pull requests adding a bank are very welcome. Please read the note there about fixtures
first: **real bank exports must never be committed**, not even anonymised.

---

# Testing

```bash
composer test        # static analysis, formatting, type coverage, tests
composer test:unit   # tests only
```

---

# Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

---

# Contributing

Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

---

# Security

Please review [our security policy](.github/SECURITY.md) on how to report security
vulnerabilities.

---

# Credits

## How this package was built

The engine, the bank profiles, the tests and this documentation were written with
[Claude Code](https://claude.com/claude-code), including a review pass that compared every
profile against its reference format one bank at a time. That pass found several sign and
date defects the test suite had been passing over, each of which now has a regression test.

Everything was then reviewed by the Kokonut team before release: the code, the detection
rules, and the decisions about which banks to claim and which to leave to the generic
reader. Note what that review does and does not cover. It is a review of the rules and the
implementation, not proof of a match against a real statement. The *Verified against a real
export* column above is the honest measure of that, and it is why most rows are still ⬜.

## Reference

The format knowledge in this package, which column headings each bank prints, in which
languages, which way its amounts are signed and where its quirks are, comes from the
open-source Swiss import apps published by [Banana.ch](https://www.banana.ch):

- [BananaAccounting/Switzerland](https://github.com/BananaAccounting/Switzerland/tree/master/ImportApps),
  Apache 2.0

Their work is where the knowledge came from, and it is the reference every profile here was
checked against, bank by bank. No source code was copied: the architecture is different, a
shared multilingual column vocabulary with declarative profiles rather than one converter
per bank, and every behaviour was re-derived and re-tested. See [NOTICE](NOTICE).

## Authors

- [Kokonut](https://kokonut.ch)
- [All Contributors](../../contributors)

---

# License

Swiss Bank CSV Parser is open-sourced software licensed under the [MIT license](LICENSE.md).
