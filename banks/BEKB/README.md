# Berner Kantonalbank

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real BEKB export.

## `bekb.statement`

```
Gutschrift / Belastung;Datum;Valuta;Buchungstext;Zusatzinfos Buchung;Name Auftraggeber / Begünstigter;Adresse Auftraggeber / Begünstigter;Konto / Bank;Mitteilung / Referenz;Zusatzinfos Transaktion;Betrag;Saldo
Gutschrift per 27.06.2026;27.06.2026;27.06.2026;Zahlungseingang;;Muster SA;Via Delle scuole 24 Lugano;CH1234;TR1234567890 -123;;629.74;11176.55
```

The most talkative of the cantonal exports, and the only one that is
unmistakable. Most Swiss cantonal banks ship four ordinary columns and nothing
that names them; BEKB gives the counterparty a name, an address and an account,
and prefixes every row with a sentence spelling out the direction.

Worth knowing:

- **The direction column is not read.** "Gutschrift per 27.06.2026" says in words
  what the sign on `Betrag` already says. It earns its keep by identifying the
  file, not by being parsed.
- **Booking text, counterparty and message are joined** into the label. A BEKB
  line means little without all three.
- The counterparty's address and bank stay in `Row::$raw`.

## Fixtures

Synthetic.
