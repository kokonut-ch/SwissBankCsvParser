# TWINT

> **Provenance:** verified against a real TWINT export.

## `twint.settlement`

```
"Datum";"Zeit";"Typ";"Status";"Betrag Transaktion (CHF)";"Rabatt";"Transaktionskosten (CHF)";"Niederlassung";"TWINT Terminal ID";"TWINT Order ID"
"2026.11.01";"11:45";"Zahlung";"Erfolgreich";"49.35";"";"0.65";"Filiale Bern";"l068";"2r0vrh8j-6o52"
```

**Not a bank statement.** This is what a merchant downloads to reconcile the
payments taken at a terminal against the payout that reaches the account. It is
read here because the rows are still dated amounts with a description, and
because reconciling TWINT payouts is a real need.

Worth knowing:

- **The fee is not netted off.** `Transaktionskosten` and `Rabatt` sit in their
  own columns and stay there; `amount` is the transaction amount. What a merchant
  owes TWINT is an accounting decision, and this is a parser.
- Dates are dot-separated ISO — `2026.11.01` — with the time in its own column.
- The currency travels in brackets on the amount heading.
- `TWINT Terminal ID` and `TWINT Order ID` identify the file beyond doubt.
- **The English report prints `State` where the German one prints `Status`**, and
  a `Failed` line still carries an amount. The state reaches `extras` under the
  heading the file uses — filter on it before counting a line as money cashed.
- The English report is wider — merchant references, the name in the statement,
  first and last name. Those columns are not modelled and stay in `Row::$raw`.

## Fixtures

Synthetic. The German report with preamble and a refund row; the English report
with the totals block, a `Failed` payment and a reversal.
