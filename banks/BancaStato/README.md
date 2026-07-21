# Banca dello Stato del Cantone Ticino

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Banca Stato export.

## `bancastato.statement`

```
Data;Data valuta;Rif.Esterno;Tipo;Testo di contabilizzazione;Addebiti;Accrediti;Saldo
07.10.2026;07.10.2026;RIF0001;Bonifico;Accredito cliente Muster SA;;439.20;62'409.47
;;;;"Data operazione: 07.10.2026, importo totale originale: CHF 439.20, …";;;
```

Italian throughout, separate debit and credit columns, Swiss thousands
apostrophes, and an external reference of its own — `Rif.Esterno`, which is what
identifies the file.

Bookings are followed by a line spelling out the original amount and the charges
withheld. It is folded into the row above, as elsewhere in this package.

## Fixtures

Synthetic, detail line included.
