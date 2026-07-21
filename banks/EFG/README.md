# EFG Bank

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real EFG export.

## `efg.statement`

```
Data registrazione;Data valuta;Transazione;Descrizione;Importo;DIV;Saldo
31/10/2026;31/10/2026;Bonifico;Muster SA fattura 4471;-1145.00;;5467.92
```

Italian headings, day-first slashed dates, one signed amount column.

**The sign is the ordinary way round** — a negative amount is money out — unlike
the card statements in this package, which invert it. Worth stating, because
nothing about the file tells you which convention it follows; the behaviour was
read off the format's documented conversion, not assumed.

Identified by a column called `DIV`, which is as unusual a heading as Swiss
banking produces. `Transazione` and `Descrizione` are joined into the label.

## Fixtures

Synthetic.
