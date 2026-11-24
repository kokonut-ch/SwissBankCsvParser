# Bank Cler

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Bank Cler export.

| Profile | Export | Languages | Currencies | In free detection |
| --- | --- | --- | --- | --- |
| `cler.statement` | account statement | Italian, French, English | CHF, EUR, USD | identified confidently |
| `cler.statement` | account statement, German layout | German | CHF, EUR, USD | never claimed: read once the bank is confirmed |

## `cler.statement`

```
Data di registrazione;Data valuta;Tipo di ordine;Testo;Importo di addebito (CHF);Importo di accredito (CHF);Saldo (CHF)
31.10.2026;31.10.2026;Pagamento in Svizzera;Muster Boutique;11.32;;5467.92
02.11.2026;02.11.2026;Trasferimento di conto;Bonifico cliente;;411.04;5878.96
```

Ordinary in every respect but two:

- **The currency is bracketed after each amount heading**: `Importo di addebito
  (CHF)`, `Solde (USD)`. There is no header block, so that is where the account
  currency comes from, and every row carries it. CHF, EUR and USD accounts are
  published; any three-letter code in the brackets is read the same way.
- **Every row is classified by an order type** (`Tipo di ordine`, `Type d'ordre`,
  `Order type`), which no other Swiss bank prints under those names and which is
  what identifies the file. It is kept as an extra.
- **The French variant drops the value date column entirely**: `Date de
  comptabilisation;Type d'ordre;Libellé;Montant débité (CHF);Montant crédité
  (CHF);Solde (CHF)`, nothing else. `Term::ValueDate` is optional, so the
  profile reads it without complaint; `Row::$valueDate` is simply null.
- **Texts span several physical lines**, inside quotes: name, street, town and
  payment reference, one per line. The reader keeps the quoted cell whole, and the
  label is those lines joined by single spaces.
- **Real exports are delivered newest first.** The upstream import app
  explicitly `.reverse()`s the rows before booking them; this package never
  reorders rows, by design: callers get the file in the order it was handed
  to them.

## `cler.statement`: the German layout, once the bank is confirmed

```
Buchungsdatum;Auftragsart;Text;Belastungsbetrag (CHF);Gutschriftsbetrag (CHF);Saldo (CHF)
05.11.2026;QR-Rechnung;"Muster Energie AG
Musterstrasse 1
4051 Basel
Pers. Ref.: Rechnung 2026-118";184.30;;5233.62
```

Published upstream in July 2026. Like the French variant, the sample has no value
date column; a `Valutadatum` column is read when present, as `Data valuta` is in
Italian. Currency, signs, multi-line texts and the order type (`Auftragsart`, kept
as an extra) work exactly as above.

**Basler Kantonalbank, Bank Cler's parent, exports the same thing.** Its published
samples print `Buchungsdatum;[Valutadatum;]Auftragsart;Text|Buchungstext;Belastungsbetrag
(CHF);Gutschriftsbetrag (CHF);Saldo (CHF)`: the same headings, the same order, the same
bracketed currency. Nothing in the file says which of the two banks produced it.

So the profile reads this layout but never claims it. Its score is capped at 0.25,
below the generic reader's 0.30: in free detection the file stays with the generic
reader, which reads it just as well and reports the bank as unknown, and
`isConfident()` is false. That is the signal to ask the person who uploaded it. Once
they have said "Bank Cler", restrict the parser to this bank's profile and the same
file is read as a Bank Cler statement:

```php
use Kokonut\SwissBankCsvParser\Banks\Cler\StatementProfile;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

$file = (new SwissBankCsvParser(new ProfileRegistry([new StatementProfile])))->parse($contents);
```

The German layout is recognised on `Auftragsart` together with both
`Belastungsbetrag` and `Gutschriftsbetrag`. Thurgauer Kantonalbank also prints
`Auftragsart`, beside `Belastung (CHF)` and `Gutschrift (CHF)`; this profile does not
read that export at all.

## Fixtures

Synthetic: invented names and amounts.

| Fixture | Export |
| --- | --- |
| `statement.csv` | Italian, CHF |
| `statement-it-usd.csv` | Italian, USD, multi-line texts |
| `statement-de.csv` | German, CHF, no value date, multi-line texts |
| `statement-de-usd.csv` | German, USD, with `Valutadatum`, multi-line texts |

The French variant and the EUR headings are tested inline.
