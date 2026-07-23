# Banca dello Stato del Cantone Ticino

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Banca Stato export.

## `bancastato.statement`

```
Data;Data valuta;Rif.Esterno;Tipo;Testo di contabilizzazione;Addebiti;Accrediti;Saldo
07.10.2026;07.10.2026;RIF0001;Bonifico;Accredito cliente Muster SA;;439.20;62'555.67
06.10.2026;06.10.2026;RIF0002;Pagamento;Pagamento fornitore;293.00;;62'116.47
```

Italian throughout, separate debit and credit columns, Swiss thousands
apostrophes, newest booking first, and an external reference of its own —
`Rif.Esterno`, which is what identifies the file.

`Tipo` is the order type — `Bonifico`, `Pagamento` — and is reported in
`Row::$extras`, the same way Bank Cler's `Tipo di ordine` is.

## What this profile deliberately does not claim

The bank has shipped newer layouts whose reference column is `Numero di ordine`
rather than `Rif.Esterno` — one of them spells out each booking's original
amount and charges inside the booking text, and another switches to German
number formatting (`20.000,00`). Neither carries this profile's signature, so
both fall through to the generic reader rather than being claimed and read
wrong. There is a test for that.

## Fixtures

Synthetic. The balances chain: each older balance plus the newer amount equals
the newer balance.
