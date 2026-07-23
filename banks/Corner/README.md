# Cornèr Banca

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Cornèr export.

## `corner.statement`

```
;Conto No.;123456/01 CHF;;;;
;Elenco movimenti;;;;;
;Conto No.;Data registrazione;Descrizione;Dettaglio;Data valuta;Importo
;123456/01;31/12/2026;Pagamento Muster SA;;31/12/2026;-40.00
;;;Spese;40,00- CHF;;
;;;Ns.rif: 2026LI60101010101ABCDEFG;;;
```

Worth knowing:

- **Every row is indented by one empty column**, headings included. Nothing
  special is needed for that — columns are found by name, not by position — but
  it surprises anyone reading the file by eye.
- **Each booking is followed by a run of lines** carrying its charges, its bank
  reference and sometimes the counterparty's full postal address, one line each.
  All of them are folded into the booking above. Labels get long; the
  alternative is losing the reference and the counterparty entirely.
- Day-first slashed dates. Real exports mostly print two-digit years
  (`28/05/24`); four-digit years and dotted dates are accepted as well.
- One signed amount column.
- **The newer layout (2024) drops `Conto No.` from the heading row** — the
  account number survives only in the preamble — and adds a `Saldo` column, so
  each language needs its own signature: `Erfassungsdatum` for German,
  `Registration date` for English, and the `Dettaglio` column for Italian (its
  date heading, `Data registrazione`, is no use — EFG prints it too). With only
  the account-number headings listed, the Italian file was rejected while its
  German twin was accepted.

## Fixtures

Synthetic, in the Italian variant, with a charge line and a reference line. The
newer Italian layout is covered by an inline fixture in the tests.
