# Viseca

> **Provenance:** verified against a real Viseca export.

## `viseca.card`

```
Date;ValutaDate;TransactionId;Amount;Currency;MerchantName;MerchantPlace;MerchantCountry;StateType;Details
2026-09-15;2026-09-16;TX0000123;105.45;CHF;Muster Shop;Lausanne;CH;Booked;Card payment
```

**The sign is inverted.** Viseca writes the file from the issuer's point of
view: a purchase is printed *positive* — the `105.45` above is money the
cardholder owes — and a refund negative. The amounts are flipped, so a negative
`amount` means money left the cardholder, as everywhere else in this package.

Structurally almost the same file as Migros Bank's card export: same merchant
columns split into name, place and country, same transaction id, one signed
amount column. The sign convention is where they part ways — `migrosbank.card`
takes its amounts at face value, this profile flips them.

**The one difference is `StateType`, where Migros Bank prints `CardId`.** Each
profile signs on its own heading and neither claims the other's file — which is
the whole reason `migrosbank.card` does not sign on `MerchantName`, a column both
of them print. There is a test for exactly that.

Viseca also ships an Excel export (`TRANSAKTIONSDATUM;VORNAME;NACHNAME;…`). This
package reads CSV only.

## Fixtures

Synthetic.
