# Raiffeisen

> **Provenance:** verified against a real Raiffeisen export.

## `raiffeisen.statement`

English headings whatever the customer's language, one signed amount column, and
no header block at all — the IBAN is repeated on every row instead.

```
IBAN;Booked At;Text;Details;Credit/Debit Amount;Balance;Valuta Date
CH93…;2026-07-02 00:00:00.0;Zahlung Lieferant;Rechnung 4471;-1145.00;2078.76;2026-07-02 00:00:00.0
;;Muster AG, 8000 Zürich;;;;
```

Worth knowing:

- **Dates carry a clock time**: `2026-07-02 00:00:00.0` in current exports,
  `01.01.2021 00:00` and `03.01.13` in older ones. The time is dropped — a
  statement line is about a day.
- **The account IBAN is a column**, not a header block. Older exports drop that
  column entirely, and then the file names no account at all.
- **Bookings spill onto the next line.** The continuation carries nothing but
  more description, and that is usually where the counterparty is named. Those
  lines are folded into the row above rather than discarded — this is a
  deliberate choice, not a mirror of the format: the bank's own historical
  import rules (the formats current up to end of 2018) simply threw these
  continuation lines away. Folding them keeps the counterparty name that would
  otherwise be lost.
- **The amount column is signed** and taken at face value.
- `Text` and `Details` are both descriptions and are joined.

The profile only matches when `Credit/Debit Amount` is present. Every other
column it uses is ordinary enough to belong to any bank, so without that heading
there would be nothing to justify claiming the file.

The very first format Raiffeisen shipped (2008, no heading row at all) is not
recognised here; a file like that falls through to the generic reader, and
that is an accepted gap rather than an oversight.

## Fixtures

Synthetic. `statement.csv` is the current export, `statement-legacy.csv` the
older five-column one with two-digit years and no IBAN column.
