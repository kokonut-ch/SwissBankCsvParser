# Banque Cantonale Vaudoise

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real BCV export.

## `bcv.statement`

```
Transactions list;;;;;20.10.2026, 23:40
;;;;;
Account No. : CH44 3199 9123 0008 8901 2;;;;;
Account holder : Muster SA;;;;;
;;;;;
Balance : ;;;;;
Curr. : ;;;;;
;;;;;
Execution date;Transactions;Debit;Credit;Value date;Balance
30.09.2026;Paiement fournisseur Muster SA;1'350.00;;30.09.2026;
19.09.2026;Virement client;;4'250.00;19.09.2026;
```

Worth knowing:

- **An eight-line preamble precedes the headings** — a title row, the account
  number and holder, and empty `Balance :` / `Curr. :` labels. None of it is a
  heading row and none of it is read as one; the row that maps every required
  column is. The title row's last cell has been seen carrying a timestamp in
  one sample and a number in another — either way it is part of the preamble
  and never read.
- **The `Balance` column is empty on every row** in the only published sample.
  `Row::$balance` is therefore null; the column is mapped in case a real
  export does fill it.
- **The description column is called `Transactions`**, plural. No other Swiss
  bank does that, which is what identifies the file.
- Headings are English even in a French-speaking canton, amounts use the Swiss
  thousands apostrophe, and the export is newest first.

## Fixtures

Synthetic, full preamble included.
