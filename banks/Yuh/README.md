# Yuh

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Yuh export.

## `yuh.statement`

```
DATE;ACTIVITY TYPE;ACTIVITY NAME;DEBIT;DEBIT CURRENCY;CREDIT;CREDIT CURRENCY;CARD NUMBER;LOCALITY;RECIPIENT;SENDER;FEES/COMMISSION;BUY/SELL;QUANTITY;ASSET;PRICE PER UNIT
31.10.2026;Card payment;Muster Boutique;11.32;CHF;;;XXXX 0001;Lausanne;;;;;;;
```

Yuh is a banking *and* investing app, so the export carries a good deal that has
no place in a bank statement: localities, counterparties, fees, and the quantity,
asset and unit price of any security traded. Those columns are not modelled — and
not discarded either; they stay in `Row::$raw`.

The card number is the exception, and it changed in `v0.1.1`. `CARD NUMBER` now
reaches `Row::$extras` under that heading, because an export covering more than
one card needs something to tell them apart. It is reported exactly as printed,
masking included — worth knowing if you redact before logging or storing.

Worth knowing:

- **A currency column on each side.** `DEBIT CURRENCY` and `CREDIT CURRENCY`, and
  only the one that applies to the row is filled. Both are mapped and the first
  that holds anything wins.
- **The description is a kind and a name**: `ACTIVITY TYPE` then `ACTIVITY NAME`,
  joined in that order.
- Headings are upper-case English whatever the customer's language.

## Fixtures

Synthetic, including a securities row so the unmodelled columns stay covered.
