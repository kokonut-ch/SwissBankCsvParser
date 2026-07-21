# Banks

One directory per bank. Each holds everything about that bank and nothing about any other:

```
banks/PostFinance/
├── EFinanceProfile.php        one class per recognisable export format
├── CreditCardProfile.php
├── LegacyStatementProfile.php
├── fixtures/                  synthetic sample files
├── tests/                     tests for those profiles, against those fixtures
└── README.md                  what the formats look like, and what is odd about them
```

Profiles are found by scanning this directory, not by being listed anywhere. **Adding a
bank means adding one folder and changing no existing file** — so two people adding two
banks never conflict, and a pull request adding a bank reads in one screen.

## Adding a bank

### 1. Get a real export first

Do not write a profile from documentation. Bank exports are full of things no spec
mentions: trailing empty columns, subtotal lines, multi-line descriptions, foreign-currency
rows, footers that look like data. A profile written without a real file is a guess that
looks like a feature.

### 2. Never commit that real export

Fixtures in this repository are **synthetic**: invented names, invented amounts, test
IBANs. Reproduce the *structure* of the real file, never its content.

A bank statement identifies a person by construction. Redacting one well enough to publish
is harder than it looks, and this repository is public. Keep the real file outside the repo
— `samples/` is git-ignored for exactly this — and commit only what you rebuilt from it.

### 3. Write the profile

Most formats print column headings, so extend `HeaderDrivenProfile` and declare what makes
this bank recognisable. The shared `Lexicon` already knows what Swiss banks call a date, a
description, a credit or a balance in four languages, so a profile is usually short:

```php
final class AcmeStatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('acme', 'Acme Bank');
    }

    public function id(): string
    {
        return 'acme.statement';   // stable, public, never renamed
    }

    protected function dateFormats(): array
    {
        return ['d.m.Y'];          // narrower is better: it sharpens detection
    }

    protected function signatureHeadings(): array
    {
        return ['Bewegungsart'];   // a heading only this bank prints
    }

    protected function headerBlock(): HeaderBlock
    {
        return new HeaderBlock(
            account: ['Konto', 'Compte'],
            currency: ['Währung', 'Monnaie'],
        );
    }
}
```

Reach for `termLabels()` only when the shared vocabulary is too broad to tell two of the
bank's *own* formats apart — an account statement and a card statement, typically.

For a bank that uses one signed amount column instead of two, add:

```php
protected function amountModel(): AmountModel
{
    return AmountModel::SignedColumn;
}
```

Extend `PositionalProfile` only for a format that genuinely has no headings. It cannot be
confident about anything, and it says so.

### 4. Test it

Put the tests in `banks/YourBank/tests/`, reading from `banks/YourBank/fixtures/`. Cover:

- the columns map correctly, and amounts carry the right sign;
- preamble, blank lines and footers do not become rows;
- each of the bank's formats is **not** matched by its siblings — `->supports()` on the
  other fixture should be `false`;
- anything odd the bank does, with a comment saying why it is odd.

```bash
composer test:unit -- --filter=YourBank
composer test
```

### 5. Update the two READMEs

The bank's own `README.md`, and the supported-banks table in the root `README.md`.
