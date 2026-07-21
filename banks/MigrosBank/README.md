# Migros Bank

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Migros Bank export.

## `migrosbank.card`

The card transaction export, which names itself plainly.

```
Date;ValutaDate;TransactionId;CardId;Currency;Amount;MerchantName;MerchantPlace;MerchantCountry;Details
2026-09-15;2026-09-16;TX0000123;XXXX0001;CHF;-105.45;Muster Shop;Lausanne;CH;Card payment
```

One signed amount column. The merchant is split across name, place and country;
those three plus `Details` make up the description, joined in that order.

## The account statement is deliberately not claimed

Migros Bank's account statement looks like this:

```
Kontoauszug bis: 04.09.2026 ;;;
Kontonummer: 543.278.22;;;
Bezeichnung: Privat;;;
Saldo: CHF 38547.70;;;

Datum;Buchungstext;Betrag;Valuta
04.09.26;Zahlungseingang;1838.00;04.09.26
```

**Valiant's is identical** — the same preamble labels, the same four columns, the
same wording, the same date format. Nothing in either file says which bank
produced it.

A profile claiming this shape for Migros Bank would attribute Valiant's
statements to Migros Bank, and be right roughly half the time while sounding
certain. So neither is claimed. The generic reader handles the format instead,
picks up the account number, holder and currency from the preamble, and reports
the bank as unknown along with a `GENERIC_PROFILE_USED` warning.

That is not a gap waiting to be filled. It is the correct answer until one of
the two banks changes its export.

## Fixtures

Synthetic.
