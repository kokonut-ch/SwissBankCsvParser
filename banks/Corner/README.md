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
- Day-first slashed dates (`31/12/2026`), and older files use two-digit years.
- One signed amount column.

## Fixtures

Synthetic, in the Italian variant, with a charge line and a reference line.
