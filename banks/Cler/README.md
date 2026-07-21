# Bank Cler

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Bank Cler export.

## `cler.statement`

```
Data di registrazione;Data valuta;Tipo di ordine;Testo;Importo di addebito (CHF);Importo di accredito (CHF);Saldo (CHF)
31.10.2026;31.10.2026;Pagamento in Svizzera;Muster Boutique;11.32;;5467.92
02.11.2026;02.11.2026;Trasferimento di conto;Bonifico cliente;;411.04;5878.96
```

Ordinary in every respect but two:

- **The currency is bracketed after each amount heading** — `Importo di addebito
  (CHF)`, `Solde (USD)` — and there is no header block, so that is where the
  account currency comes from. A foreign-currency account announces itself the
  same way.
- **Every row is classified by an order type** (`Tipo di ordine`, `Type d'ordre`),
  which is what identifies the file. It is kept as an extra.

## Fixtures

Synthetic, in the Italian variant.
