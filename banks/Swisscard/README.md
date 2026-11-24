# Swisscard

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Swisscard export.

## `swisscard.statement`

The current layout is twelve columns, comma-separated:

```
Transaction date,Description,Merchant,Card number,Currency,Amount,Foreign Currency,Amount in foreign currency,Debit/Credit,Status,Merchant Category,Registered Category
13.11.2026,MUSTER SHOP LAUSANNE,Muster Shop,XXXX 0001,CHF,189.00,,,Debit,Booked,Shopping,Shopping
16.11.2026,REMBOURSEMENT MUSTER SHOP,Muster Shop,XXXX 0001,CHF,-40.00,,,Credit,Booked,Shopping,Shopping
```

It exists in English, German, Italian and, since November 2026, French. The
2023 layout is eight quoted columns, with no merchant and no foreign-currency
pair, and its category heading is plain `Kategorie`/`Categoria`, without the
"Registered" prefix. Both layouts are read by this one profile.

### Coverage

| Layout | English | German | Italian | French |
| --- | --- | --- | --- | --- |
| Twelve columns (current) | ✅ | ✅ | ✅ | ✅ |
| Eight columns (2023) | not published | ✅ | ✅ | not published |

Every ✅ is a layout and language for which upstream publishes a sample, and that
sample is read in free detection and with the bank confirmed. Neither of the two
missing variants has been seen. A French 2023 file, should one exist with the
French headings above minus the merchant and foreign-currency columns and with a
plain `Catégorie`, would be read and signed on `Débit/Crédit`; that is tested on
a synthetic line, not on a real file.

### French

The French twelve-column layout reads:

```
Date transaction,Description,Commerçant,Numéro de carte,Monnaie,Montant,Monnaie étrangère,Montant étranger,Débit/Crédit,Statut,Catégorie de commerçant,Catégorie enregistrée
"18.11.2026","MUSTER SHOP GENÈVE","Muster Shop","XXXX 0001","CHF","74.50","","","Débit","Comptabilisée","Achats","Shopping"
```

Two of its headings are Swisscard's own wording: `Date transaction` (the shared
lexicon knows `Date de transaction`) and `Catégorie enregistrée`. The profile
claims them itself rather than adding them to the shared lexicon. No other
profile needs them, and the generic reader, which takes card signs at face
value, is better off not recognising the file at all. `Catégorie enregistrée`
and `Débit/Crédit` are signatures, like their counterparts in the other
languages. `Monnaie`, `Montant`, `Numéro de carte` and `Statut` are ordinary
vocabulary, and the foreign pair (`Monnaie étrangère`, `Montant étranger`) is
never read for the settled currency and amount, since headings match whole.

A purchase in a foreign currency that is still pending has no settled amount
yet: `Currency` and `Amount` are empty and only the foreign pair is filled. The
row is kept with a null `amount`; whether it counts is the caller's decision.

One Italian trap: `Valuta` means currency in this file, while the shared
lexicon deliberately lists that word under value date (its German meaning). The
profile claims it for `Term::Currency` explicitly — neither layout has a
value-date column to collide with.

## The sign is inverted, and it matters

Swisscard writes the statement from the **issuer's** point of view: a purchase is
printed **positive**, because it is what you owe, and a refund **negative**.

Read at face value, every charge on the card becomes income. This package flips
the sign, so that here as everywhere else a negative amount means money left the
cardholder.

That is not an interpretation — it is what the format does, and it is the reason
this profile exists rather than leaving the file to the generic reader, which
would read the signs the ordinary way and be wrong on every row.

Related: the file also prints a `Debit/Credit` word column beside the amount.
Its values are deliberately **not** read — the sign already carries the
direction, and consulting both would only create a way for them to disagree;
there is a test where the two contradict and the sign wins. Its *heading*, on
the other hand, is one of the profile's signatures: on the 2023 layout it is
the only distinctive column name there is.

## The card and the status are reported

A Swisscard statement can cover several cards issued on one account, and it lists
pending authorisations beside settled ones. Both facts change what a row means —
a pending line can still change amount or disappear — so both are reported in
`Row::$extras`:

```php
$row->extras['Card number'];  // 'XXXX 0001'
$row->extras['Status'];       // 'Booked'
```

The keys are the headings as the file prints them: `Kartennummer` and `Status`
in German, `Numero di carta` and `Stato` in Italian, `Numéro de carte` and
`Statut` in French. The registered category is reported the same way, and is
absent from rows that have none, such as a card payment.

Neither belongs in the neutral model: the package reports what the file says and
leaves to the caller whether a pending line counts. What it will not do is make
that decision reachable only by counting columns in `Row::$raw`.

The merchant, the foreign-currency pair and the merchant category are not
modelled and stay in `Row::$raw`.

## Fixtures

Synthetic: the twelve-column layout in English, Italian and French (the
Italian one with a pending foreign-currency row, the French one with a purchase
settled in CHF from a EUR amount and a card payment without a registered
category), and the 2023 eight-column layout in German and Italian. Every file
keeps a refund or payment row so the sign inversion stays covered in both
directions.
