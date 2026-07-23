# Zürcher Kantonalbank

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real ZKB export.

## `zkb.statement`

ZKB has shipped at least five layouts over the years — all quoted, all German,
all built from the same vocabulary. One profile reads them all, because what
identifies a column here is its name, not its position.

```
"Datum";"Buchungstext";"Zahlungszweck";"Whg";"Betrag Detail";"ZKB-Referenz";"Referenznummer";"Belastung CHF";"Gutschrift CHF";"Valuta";"Saldo CHF";"Zahlungszweck";"Details"
"11.02.2026";"Einkauf";"";"";"";"L123456789";"";"3202.00";"";"10.02.2026";"23708.77";"";""
"";"Grundversicherung Muster AG";"";"";"";"";"";"";"";"";"";"";"Rechnung Nr. 4471"
```

Worth knowing:

- **The description is spread over up to four columns**: `Buchungstext`,
  `Zahlungszweck` — which appears twice — and `Details`. All of them are joined,
  in column order.
- **Bookings spill onto the following line**, which carries only more text. Those
  lines are folded into the row above.
- **The currency is in the amount heading** (`Belastung CHF`), never in a header
  block; there is no header block.
- `ZKB-Referenz` wins over the `Referenznummer` column beside it, which is
  usually empty.
- `Betrag Detail` is the per-item amount of a collective booking. It is not read
  as the row's amount — the collective's own debit or credit column carries the
  total. Mind the fine print: in real exports those per-item amounts sit on the
  *continuation lines*, and folding a continuation into the booking above keeps
  its text but not its cells — so the per-item breakdown is **lost**. Only a
  `Betrag Detail` printed on the dated line itself would survive in `Row::$raw`,
  and no known export prints one there. The total is always intact.

## What this profile deliberately does not claim

The oldest layout, `Datum;Buchungstext;Konto;Whg;Belastung;Gutschrift`, contains
nothing that names ZKB. Claiming it would mean claiming every other bank's file
of the same shape. It falls through to the generic reader instead, which says it
is guessing.

## Fixtures

Synthetic, modelled on the current thirteen-column layout, including a
continuation line and Swiss thousands apostrophes.
