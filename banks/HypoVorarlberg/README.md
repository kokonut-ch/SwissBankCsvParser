# Hypo Vorarlberg

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Hypo Vorarlberg export.

**Austrian, not Swiss.** It is here because the bank serves the border region and
its statements land on Swiss desks; `BankIdentity::$country` reports `at`, so a
caller that cares can tell.

## `hypovorarlberg.statement`

```
IBAN;Auszugsnummer;Buchungsdatum;Valutadatum;Umsatzzeit;Zahlungsreferenz;Waehrung;Betrag;Buchungstext;Umsatztext;Name des Partners;Kategorie;Bestandskategorie
AT61…;12;2026.12.31;2026.12.31;2026-12-31-21.35.45.616362;REF0001;EUR;-40,51;Abschluss;Kontoführung;;Sonstiges;Giro
```

By far the most detailed export this package reads: SEPA mandate and creditor
identifiers, the counterparty's name, BIC and account, fee information, three
kinds of category (`Kategorie`, `Bestandskategorie`, `Umsatzkategorie`), a
microsecond timestamp on every movement. Only what the neutral model has a field
for is mapped — everything else survives in `Row::$raw`.

One Austrian habit and one tolerance:

- **Comma decimals with dot thousands**: `-40,51`, `1.240,55`.
- **ISO dates with dashes**: `2026-12-31` — the only form the bank's published
  samples attest. The dotted form (`2026.12.31`) is accepted defensively but
  has not been observed in a real export.

The amount is signed the ordinary way: negative is money out.

The IBAN is a column rather than a header block, and the currency is spelled
`Waehrung` without the umlaut.

## Fixtures

Synthetic. `AT611904300234573201` is the published Austrian example IBAN.
