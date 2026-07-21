# Cornèrcard

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Cornèrcard export.

Not to be confused with [`banks/Corner`](../Corner), which reads Cornèr Banca's
*account* statements. Same group, different file, different profile.

## `cornercard.statement`

```
Date;Description;Card;Currency;Amount;Status
02/11/2026;MUSTER SHOP LAUSANNE;**2618;CHF;49.90;Registrata
02/11/2026;RIMBORSO MUSTER SHOP;**2618;CHF;-100.00;Registrata
```

## The sign is inverted

As with Swisscard, the statement is written from the issuer's point of view: **a
purchase is positive, a refund negative**. The amounts are flipped, so a negative
one means money left the cardholder.

## Identified by a combination, not by a name

`Card`, `Currency` and `Status` are all ordinary words; no one of them names
Cornèrcard. It is the three together, beside a date, a description and an amount,
that identify the file — which is why this profile uses `requiredHeadings()`,
where every other bank so far has needed only a distinctive single heading.

A file carrying only some of them is not claimed. There is a test for that.

## Fixtures

Synthetic, with a refund row and a pending row.
