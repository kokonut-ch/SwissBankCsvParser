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

## Fixtures

Synthetic, report preamble and a refund row included.
