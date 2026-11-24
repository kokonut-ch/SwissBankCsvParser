# Schaffhauser Kantonalbank

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Schaffhauser Kantonalbank export.

| Profile | Export | Languages | In free detection |
| --- | --- | --- | --- |
| `shkb.statement` | account statement | German | never claimed: read once the bank is confirmed |

## `shkb.statement`

```
Kontoauszug bis: 30.09.2026 ;;;
;;;
Kontonummer: 123.456-7 890;;;
Bezeichnung: Kontokorrent BUSINESS CHF;;;
Saldo: CHF 8450.35;;;
;;;
Muster AG;;;
Beispielstrasse 4;;;
8200 Schaffhausen;;;
;;;
;;;
Datum;Buchungstext;Betrag;Valuta
30.09.26;Kontoführungsgebühr;-12.00;30.09.26
28.09.26;Kartenzahlung Muster Laden, Schaffhausen;-54.95;25.09.26
```

- The label is the booking text; `Valuta` is the value date.
- The signed amount is taken at face value: negative means money out.
- Rows are dated `dd.mm.yy` and printed newest first. They come back in file order.

**The preamble.**

| Line | Read as |
| --- | --- |
| `Kontoauszug bis:` | `period->to`; the file announces no start, so `period->from` is `null` |
| `Kontonummer:` | `account->number` (`123.456-7 890`), never an IBAN |
| `Saldo:` | `account->currency`, the ISO code picked out of `CHF 8450.35` |
| `Bezeichnung:` | `metadata` only: the kind of account, never the holder |

The holder's name is on the unlabelled address lines, which are not read.

## Never claimed in free detection

**Migros Bank's older layout, Valiant, acrevis, Schwyzer KB, Nidwaldner KB and Zuger KB
print exactly the same file**: the same preamble labels, the same four columns, the same
wording. Nothing in it says which bank produced it.

So the profile reads this layout but never claims it. Its score is capped at 0.25, below
the generic reader's 0.30: in free detection the file stays with the generic reader, which
reports the bank as unknown, and `isConfident()` is false. That is the signal to ask the
person who uploaded it. Once they have said "Schaffhauser Kantonalbank", restrict the
parser to this bank's profiles and the same file is read as a Schaffhauser Kantonalbank
statement:

```php
use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

$shkb = array_values(array_filter(
    (new SwissBankCsvParser)->profiles()->all(),
    static fn (BankProfile $profile): bool => $profile->identity()->key === 'shkb',
));

$file = (new SwissBankCsvParser(new ProfileRegistry($shkb)))->parse($contents);
```

The layout's reading is shared, not copied: its headings, preamble labels, date notations
and ceiling live in [`banks/Generic/FourColumnStatement.php`](../Generic/FourColumnStatement.php),
which Migros Bank's statement profile uses too.

Even with the bank confirmed, the profile reads that layout and nothing richer. A heading
row with any other column, such as Migros Bank's current
`Datum;Buchungstext;Mitteilung;Referenznummer;Betrag;Saldo;Valuta`, is refused. A running
balance column, `Saldo`, which one published sample of the layout prints, is read;
`dd.mm.yyyy` row dates too.

## Fixtures

Synthetic: invented names, amounts and account number.

| Fixture | Export |
| --- | --- |
| `statement.csv` | account statement |
