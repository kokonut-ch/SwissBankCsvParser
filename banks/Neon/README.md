# neon

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real neon export.

## `neon.statement`

```
"Date";"Amount";"Original amount";"Original currency";"Exchange rate";"Description";"Subject";"Category";"Tags";"Wise";"Spaces"
"2026-12-30";"-5.00";"";"";"";"App Store";"";"shopping";"";"no";"no"
"2026-02-26";"-538.28";"-579.33";"USD";"1.07626";"American Stuff";"";"shopping";"";"no";"no"
```

Short, quoted, ISO dates, one signed amount column. Its own product features name
it: no other bank prints a `Spaces` or a `Wise` column.

Worth knowing:

- **The file names no account currency.** `Original currency` is the currency of
  a foreign purchase — USD on the third row above — and reporting it as the
  account's would be wrong on every domestic row. The currency is left null with
  a `CURRENCY_NOT_DETECTED` warning. If you know your neon account is in francs,
  that default belongs in your code, not here.
- `Description` and `Subject` are joined into the label.
- `Category` is kept as an extra.

## Fixtures

Synthetic.
