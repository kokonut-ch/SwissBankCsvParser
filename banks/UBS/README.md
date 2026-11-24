# UBS

> **Provenance:** verified against a real UBS export. Every layout below is also checked
> against the 24 anonymised sample files Banana.ch publishes with its UBS import app.

Three profiles. UBS names its columns the way everybody else does, so none of them matches
unless one of the headings in [`Signatures.php`](Signatures.php) (or, for the card
statement, `Numéro de carte` and its translations) is present. Without that, a UBS profile
would read every other bank's statement just as happily as its own.

| Layout | Years | Profile | Fixtures |
| --- | --- | --- | --- |
| Portfolio export, debit and credit columns | 2012 onwards | `ubs.statement.split` | `statement-split.csv`, `statement-portfolio-de.csv` |
| E-banking export, debit and credit columns | November 2022 onwards | `ubs.statement.split` | `statement-current-fr.csv` |
| E-banking export, one signed amount column | September 2022 | `ubs.statement.signed` | `statement-signed.csv` |
| Credit card statement | 2017 onwards | `ubs.creditcard` | `creditcard.csv`, `creditcard-pending.csv` |

## Dates: what an application receives

Every account export prints three dates per row: the trade date, the booking date and the
value date. The rule is the same for all three account layouts.

| Field | Read from | Headings |
| --- | --- | --- |
| `Row::$date` | the booking date | `Buchungsdatum`, `Date de comptabilisation`, `Data di registrazione`, `Data di contabilizzazione`, `Booking date` |
| `Row::$date`, when the booking date is empty | the trade date | `Abschluss`, `Abschlussdatum`, `Date de conclusion`, `Date de transaction`, `Data di chiusura`, `Data dell'operazione`, `Trade date` |
| `Row::$valueDate` | the value date | `Valuta`, `Valutadatum`, `Date de valeur`, `Data di valuta`, `Value date` |
| `Row::$extras` | the trade date, as printed, under its heading as printed | same as above, e.g. `['Abschlussdatum' => '2022-11-04']` |

**The booking date first.** It is the date the bank booked the line, which is what
`Row::$date` means throughout this package, and what an ISO 20022 statement calls
`BookgDt`. On the e-banking exports it often differs from the trade date: a debit-card
payment made on a Friday evening is booked on the Monday, and its value date is backdated
to the Friday. In the 2022 sample statement, 126 of 195 rows were booked one to five days
after their trade date.

**The trade date when the booking date is empty.** The e-banking exports leave the booking
date empty on a payment UBS has not booked yet, which prints a trade time instead. The trade
date is the one date UBS fills on every row, and on every such row in the published samples
it equals the value date, so the choice between the two changes nothing there. It is also
the fallback Banana.ch's own import uses for the portfolio export. No published portfolio
export leaves the booking date empty.

**The trade date stays reachable.** Since it is read as a candidate for the row's date, it
never reached `extras` before. It now does, on every row of the three account layouts that
prints one.

> **Attention:** Banana.ch's import dates the two e-banking layouts by the trade date alone.
> Its output therefore differs from this package's on every row booked after the day it was
> made. An application that wants Banana.ch's date reads the
> trade date from `extras`; across the published samples, that reproduces it on every row.

The card statement has two dates: `Ecriture`, the booking date, is `Row::$date`, and the
purchase date (`Date d'achat`) is `Row::$valueDate`.

## `ubs.statement.split`: separate debit and credit columns

Covers the wide portfolio export and the e-banking export that splits the two directions.

```
Valuation date;Banking relationship;Portfolio;Product;IBAN;Ccy.;Date from;Date to;Description;Trade date;Booking date;Value date;Description 1;Description 2;Description 3;Transaction no.;…;Debit;Credit;Balance
07.07.26;0240 00254061;;…;CH93…;CHF;01.02.26;30.06.26;UBS Business Current Account;30.06.26;30.06.26;30.06.26;Zahlung Lieferant;Muster AG;Rechnung 4471;ZD81181TI0690091;;;1145.00;;7854.90
```

```
Date de transaction;Heure de transaction;Date de comptabilisation;Date de valeur;Devise;Débit;Crédit;Sous-montant;Solde;No de transaction;Description1;Description2;Description3;Notes de bas de page;
2026-07-24;19:05:33;2026-07-27;2026-07-24;CHF;-79.50;;;4720.50;9930205BN1905331;"Restaurant Muster SA,1003 Lausanne";…
```

### The description trap

The portfolio export prints a plain `Description` column holding the *account's* name,
identically on every row, and puts the actual booking text in `Description 1/2/3`. Read
naively, every label would begin with "UBS Business Current Account". The term is therefore
narrowed to the numbered columns only.

### A reversed credit is negative

The portfolio export books a reversed credit in the credit column with a minus: `-500.00`
under `Gutschrift`, and the running balance drops by 500 on that row. The shared
split-column rule ignores a printed sign and lets the column decide, which would report
500 received. Here a credit keeps its sign. A debit is always money out: the e-banking
export prints every debit negative, the portfolio export every debit positive, and no
published sample of either reverses a debit.

### A description split by an unquoted semicolon

The portfolio export does not quote its descriptions. One that contains ` ; ` comes out a
cell too wide: every column after it shifts right, the credit lands in the balance column,
and the row reads as a line with no amount and a wrong balance. A row wider than its
heading is put back together where a delimiter has a space on either side, and only when
that accounts for every surplus cell. The text keeps its semicolon, and `Row::$raw` holds
the rejoined cells.

### Also worth knowing

- Two-digit years (`07.07.26`) in the older portfolio exports.
- The account IBAN in a column, and the currency in a column called `Ccy.`, `Whrg.`, `Mon.`,
  `Monn.`, `Währung`, `Monnaie`, `Moneta`, `Currency`, or `Devise` in the French e-banking
  export since 2026.

## `ubs.statement.signed`: one signed amount column

```
Data dell'operazione;Ora dell'operazione;Data di registrazione;Data di valuta;Moneta;Importo della transazione;Addebito/Accredito;Saldo;N. di transazione;Descrizione1;Descrizione2;Descrizione3;Note a piè di pagina
2026-09-19;;2026-09-19;2026-09-19;CHF;-150.00;Addebito;600.00;123456TI1234567;Versamento;Ordine di pagamento via e-banking;;
```

`Addebito/Accredito` repeats the direction in words. It is ignored: the sign is already on
the amount, and reading both would be a way to disagree with oneself.

This profile and the split one cannot both match a file: one needs a debit and a credit
column, the other an amount column, and no UBS export has all three.

## `ubs.creditcard`

```
sep=;
Numéro de compte;Numéro de carte;Titulaire de compte/carte;Date d'achat;Texte comptable;Secteur;Montant;Monnaie originale;Cours;Monnaie;Débit;Crédit;Ecriture
ZZZZ ZZZZ ZZZZ;XXXX XXXX XXXX 0001;JEAN MUSTER;13.11.2026;www.example.ch LUGANO CHE;Magasin d ordinateurs;189.00;CHF;;CHF;189.00;;15.11.2026
```

The booking date is called `Ecriture`, which no shared vocabulary would guess, so it is
named explicitly.

The amount appears three times: original currency, converted, then split across debit and
credit. Only the debit/credit pair is read, and the currency taken is the settlement one
(`Monnaie`), not `Monnaie originale`.

A line without `Ecriture` is never a row, and never the rest of the line above. It is
either the balance carried from the previous statement or a purchase the issuer has not
booked yet, which prints its text and its original amount but no debit, no credit and no
booking date. Such lines used to lend their text to the label of the booked purchase
before them.

Banana.ch's 2017 card sample is refused. The `é` of `Numéro de carte`, `Débit` and
`Crédit` was lost before the file was published, and the sample holds the replacement
character U+FFFD in its place, so none of the headings this profile needs is there. With those three characters restored, it reads all eight rows as Banana.ch
does. `creditcard-pending.csv` reproduces its structure.

## Fixtures

Synthetic. `CH9300762011623852957` is the published Swiss example IBAN; holders, card
numbers, transaction numbers and labels are invented.
