# Banque Cantonale Vaudoise

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real BCV export.

## `bcv.statement`

```
Transactions list;;;;;45950.98
Execution date;Transactions;Debit;Credit;Value date;Balance
30.09.2026;Paiement fournisseur Muster SA;1350;;30.09.2026;44600.98
19.09.2026;Virement client;;4250;19.09.2026;48850.98
```

Worth knowing:

- **A title row precedes the headings**, carrying the closing balance in its last
  cell. It is not a heading row and is not read as one — the row that maps every
  required column is.
- **The description column is called `Transactions`**, plural. No other Swiss
  bank does that, which is what identifies the file.
- Headings are English even in a French-speaking canton.

## Fixtures

Synthetic, title row included.
