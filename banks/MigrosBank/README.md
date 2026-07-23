# Migros Bank

> **Provenance:** derived from publicly documented format samples. Not yet
> verified against a real Migros Bank export.

## `migrosbank.card`

The card transaction export, produced by the same platform as Viseca's.

```
TransactionId,CardId,Date,ValutaDate,Amount,Currency,OriginalAmount,OriginalCurrency,MerchantName,MerchantPlace,MerchantCountry,StateType,Details,Type,Exchange Rate
TX0000123,XXXX0001,2026-09-15 13:11:50,2026-09-16 00:00:00,105.45,CHF,105.45,CHF,Muster Shop,Lausanne,CHE,BOOKED,Card payment,merchant,1.000000
```

**The sign is inverted.** Like Viseca's export, the file is written from the
issuer's point of view: a purchase is printed *positive* — the `105.45` above is
money the cardholder owes — and a refund negative. The amounts are flipped, so a
negative `amount` means money left the cardholder, as everywhere else in this
package. Every purchase in the published sample is positive.

The merchant is split across name, place and country; those three plus
`Details` make up the description, joined in that order. Dates carry a clock
time, which is dropped. `CardId`, `StateType` and the original-currency pair
are not modelled and stay in `Row::$raw`.

**Told apart from Viseca by the `Exchange Rate` column.** Both exports carry
`CardId` and `StateType`; the trailing exchange-rate column is the one heading
the published Migros Bank sample has and Viseca's does not. This profile signs
on it, and `viseca.card` declares it disqualifying. That is the whole evidence
base — thin, and stated here so nobody mistakes it for more.

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
