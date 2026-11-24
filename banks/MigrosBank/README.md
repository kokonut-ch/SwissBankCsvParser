# Migros Bank

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Migros Bank export.

| Profile | Export | Languages | In free detection |
| --- | --- | --- | --- |
| `migrosbank.card` | card transactions | English | identified confidently |
| `migrosbank.statement` | account statement, current layout | German, French, Italian | identified confidently |
| `migrosbank.statement` | account statement, older four-column layout | German, Italian | never claimed: read once the bank is confirmed |

## `migrosbank.card`

The card transaction export, produced by the same platform as Viseca's.

```
TransactionId,CardId,Date,ValutaDate,Amount,Currency,OriginalAmount,OriginalCurrency,MerchantName,MerchantPlace,MerchantCountry,StateType,Details,Type,Exchange Rate
TX0000123,XXXX0001,2026-09-15 13:11:50,2026-09-16 00:00:00,105.45,CHF,105.45,CHF,Muster Shop,Lausanne,CHE,BOOKED,Card payment,merchant,1.000000
```

**The sign is inverted.** Like Viseca's export, the file is written from the
issuer's point of view: a purchase is printed *positive* — the `105.45` above is
money the cardholder owes — and a refund negative. The amounts are flipped, so a
negative `amount` means money left the cardholder, as everywhere else in this
package. Every purchase in the published sample is positive.

The merchant is split across name, place and country; those three plus
`Details` make up the description, joined in that order. Dates carry a clock
time, which is dropped. `CardId`, `StateType` and the original-currency pair
are not modelled and stay in `Row::$raw`.

**Told apart from Viseca by the `Exchange Rate` column.** Both exports carry
`CardId` and `StateType`; the trailing exchange-rate column is the one heading
the published Migros Bank sample has and Viseca's does not. This profile signs
on it, and `viseca.card` declares it disqualifying. That is the whole evidence
base — thin, and stated here so nobody mistakes it for more.

## `migrosbank.statement`: the current layout

```
"Kontoauszug von:";"2026-01-01"
"Kontoauszug bis:";"2026-03-01"
;
"Vertrag:";"12345678"
"Kontonummer / IBAN:";"123.456.78 / CH93 0076 2011 6238 5295 7"
"Bezeichnung:";"Privatkonto"
"Saldo:";""
;
"Max Muster"
"Musterstrasse 1"
"8000 Zürich"
;
;
"Datum";"Buchungstext";"Mitteilung";"Referenznummer";"Betrag";"Saldo";"Valuta"
"12.02.2026";"Muster Versicherung AG, Postfach, 3000 Bern";"";"12 34567 89012 34567 89012 34560";"-350.40";"4849.60";"12.02.2026"
```

The Italian export prints `Data;Testo di registrazione;Comunicazione;Numero di riferimento;Importo;Valuta`,
the French one `Date;Libellé;Message;Numéro de référence;Montant;Valeur`. The German
sample carries a `Saldo` column, the Italian and French ones do not.

**Identified by its message and reference-number columns together.** No other Swiss
export in the published samples prints that pair, so the file is claimed confidently,
in all three languages.

- The label is the booking text, followed by the message when there is one.
- The reference number reaches `Row::$reference`; `Valuta` / `Valeur` is the value date,
  in Italian too.
- The signed amount is taken at face value. French files write it with a decimal comma,
  `-15,50`, and quote every field.

**The preamble.**

| Line | Read as |
| --- | --- |
| `Kontoauszug von:` / `bis:`, `Relevé de compte de:` / `jusqu'au:`, `Estratto conto da:` / `fino:` | `period`, from ISO dates; a date that cannot be read leaves that end `null` |
| `Kontonummer / IBAN:` | `account->iban` when the IBAN is valid, otherwise `account->number` (`123.456.78`) |
| `Vertrag:` | `metadata` only |
| `Bezeichnung:`, `Denominazione:`, `Description:` | `metadata` only: the kind of account, never the holder |
| `Saldo:` | printed empty, so the file names no currency and says so with `CURRENCY_NOT_DETECTED` |

The holder's name is on the unlabelled address lines, which are not read. The French and
Italian wordings of the account line are assumed by analogy: the published samples in
those languages do not print it.

## `migrosbank.statement`: the older four-column layout, once the bank is confirmed

```
Kontoauszug bis: 04.09.2026 ;;;
;;;
Kontonummer: 123.456.78;;;
Bezeichnung: Privatkonto;;;
Saldo: CHF 12345.60;;;
;;;
Max Muster;;;
Musterstrasse 1;;;
8000 Zürich;;;
;;;
;;;
Datum;Buchungstext;Betrag;Valuta
04.09.26;Zahlungseingang Beispiel AG;1838.00;04.09.26
```

**Valiant, acrevis, Schwyzer KB and Nidwaldner KB print exactly the same thing**: the
same preamble labels, the same four columns, the same wording, the same dates. Nothing in
the file says which bank produced it. Schaffhauser Kantonalbank does too, and its profile,
`shkb.statement`, reads the layout the same way: both take its headings, preamble labels,
date notations and ceiling from
[`banks/Generic/FourColumnStatement.php`](../Generic/FourColumnStatement.php).

So the profile reads this layout but never claims it. Its score is capped at 0.25,
below the generic reader's 0.30: in free detection the file stays with the generic
reader, which reports the bank as unknown, and `isConfident()` is false. That is the
signal to ask the person who uploaded it. Once they have said "Migros Bank", restrict the
parser to this bank's profiles and the same file is read as a Migros Bank statement:

```php
use Kokonut\SwissBankCsvParser\Contracts\BankProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

$migros = array_values(array_filter(
    (new SwissBankCsvParser)->profiles()->all(),
    static fn (BankProfile $profile): bool => $profile->identity()->key === 'migrosbank',
));

$file = (new SwissBankCsvParser(new ProfileRegistry($migros)))->parse($contents);
```

Two variants were published. The German one dates its rows `dd.mm.yy` and writes its
preamble in one cell, `Kontoauszug bis: 04.09.2026 ;;;`. The Italian one,
`Data;Testo di registrazione;Importo;Valuta`, dates its rows `dd.mm.yyyy` and writes
`label:;value`; its preamble labels are anonymised in the published sample, and the
fixture assumes the current layout's wording. Either way the label is the booking text,
the `Saldo:` line gives the currency, and `Bezeichnung:` stays in `metadata`.

## Fixtures

Synthetic: invented names and amounts, example IBANs.

| Fixture | Export |
| --- | --- |
| `card.csv` | card transactions |
| `statement-de.csv`, `statement-it.csv`, `statement-fr.csv` | current layout |
| `statement-classic-de.csv`, `statement-classic-it.csv` | older four-column layout |
