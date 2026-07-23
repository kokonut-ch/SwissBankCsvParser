# Cornèrcard

> **Provenance:** verified against a real Cornèrcard export (English). The
> German and Italian variants are derived from publicly documented format
> samples.

Not to be confused with [`banks/Corner`](../Corner), which reads Cornèr Banca's
*account* statements. Same group, different file, different profile.

## `cornercard.statement`

```
Date;Description;Card;Currency;Amount;Status
02/11/2026;MUSTER SHOP LAUSANNE;**2618;CHF;49.90;Booked
02/11/2026;REFUND MUSTER SHOP;**2618;CHF;-100.00;Booked
```

The same six columns are printed in the language of the account:
`Datum;Beschreibung;Karte;Währung;Betrag;Status` in German,
`Data;Descrizione;Carta;Valuta;Importo;Stato` in Italian.

## The sign is inverted

As with Swisscard, the statement is written from the issuer's point of view: **a
purchase is positive, a refund negative**. The amounts are flipped, so a negative
one means money left the cardholder.

## Identified by a combination, not by a name

Card, currency and status are all ordinary words; no one of them names
Cornèrcard. It is the three together, beside a date, a description and an
amount, that identify the file — so all three are *required terms*, resolved
through the shared multilingual lexicon rather than compared as literal English
headings. That is what lets one rule recognise all three languages.

A file carrying only some of them is not claimed. There is a test for that.

One Italian trap: `Valuta` means currency in this file, while the shared lexicon
deliberately lists that word under value date (its German meaning). The profile
claims it for `Term::Currency` explicitly — this format has no value-date column
to collide with.

The card and status columns reach `extras` under the heading the file itself
uses: `Card`/`Status`, `Karte`/`Status`, or `Carta`/`Stato`.

## Fixtures

Synthetic, one per language, each with a refund row and a pending row.
