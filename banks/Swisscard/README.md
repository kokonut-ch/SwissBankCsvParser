# Swisscard

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Swisscard export.

## `swisscard.statement`

```
Transaction date;Description;Card number;Currency;Amount;Debit/Credit;Status;Registered Category
13.11.2026;MUSTER SHOP LAUSANNE;XXXX 0001;CHF;189.00;Debit;Booked;Shopping
16.11.2026;REMBOURSEMENT MUSTER SHOP;XXXX 0001;CHF;-40.00;Credit;Booked;Shopping
```

## The sign is inverted, and it matters

Swisscard writes the statement from the **issuer's** point of view: a purchase is
printed **positive**, because it is what you owe, and a refund **negative**.

Read at face value, every charge on the card becomes income. This package flips
the sign, so that here as everywhere else a negative amount means money left the
cardholder.

That is not an interpretation — it is what the format does, and it is the reason
this profile exists rather than leaving the file to the generic reader, which
would read the signs the ordinary way and be wrong on every row.

Related: the file also prints a `Debit/Credit` word column beside the amount. It
is deliberately **not** read. The sign already carries the direction, and
consulting both would only create a way for them to disagree.

## A note on the fixture

The column set is eight wide, and the positions of the date, description, card
number, amount and registered category are documented. The names of the two
remaining columns — given here as `Currency` and `Status` — are inferred.

Nothing depends on them: columns are mapped by name, so if a real export calls
them something else they are simply left unmapped and stay in `Row::$raw`. The
profile requires only the date, the description, the amount and the registered
category that identifies it.

## Fixtures

Synthetic, with a refund row so the sign inversion stays covered in both
directions.
