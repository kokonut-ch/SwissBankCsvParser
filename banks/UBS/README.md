# UBS

> **Provenance:** verified against a real UBS export.

Three formats, and UBS names its columns the way everybody else does — so all
three refuse to match unless one of the headings in [`Signatures.php`](Signatures.php)
is present. Without that, a UBS profile would read every other bank's statement
just as happily as its own.

## `ubs.statement.split` — separate debit and credit columns

Covers the wide portfolio export and the modern e-banking export that splits the
two directions.

```
Valuation date;Banking relationship;Portfolio;Product;IBAN;Ccy.;Date from;Date to;Description;Trade date;Booking date;Value date;Description 1;Description 2;Description 3;Transaction no.;…;Debit;Credit;Balance
07.07.26;0240 00254061;;…;CH93…;CHF;01.02.26;30.06.26;UBS Business Current Account;30.06.26;30.06.26;30.06.26;Zahlung Lieferant;Muster AG;Rechnung 4471;ZD81181TI0690091;;;1145.00;;7854.90
```

**The description trap.** UBS prints a plain `Description` column holding the
*account's* name, identically on every row, and puts the actual booking text in
`Description 1/2/3`. Read naively, every label would begin with "UBS Business
Current Account". The term is therefore narrowed to the numbered columns only.

Also worth knowing: two-digit years (`07.07.26`), the account IBAN in a column,
and the currency in a column abbreviated `Ccy.` / `Whrg.` / `Mon.` / `Monn.`

## `ubs.statement.signed` — one signed amount column

```
Data dell'operazione;Ora dell'operazione;Data di registrazione;Data di valuta;Moneta;Importo della transazione;Addebito/Accredito;Saldo;N. di transazione;Descrizione1;Descrizione2;Descrizione3;Note a piè di pagina
2026-09-19;;2026-09-19;2026-09-19;CHF;-150.00;Addebito;600.00;123456TI1234567;Versamento;Ordine di pagamento via e-banking;;
```

`Addebito/Accredito` repeats the direction in words. It is ignored: the sign is
already on the amount, and reading both would be a way to disagree with oneself.

Two dates, one rule: the booking date is the row's date whenever it is filled —
it is the date the balance column moves on — and the operation date in the
first column fills in when it is not. That is this package's rule, applied to
every UBS layout; UBS's own import rules for this export read the operation
date alone. Since about 2024 the Italian export names the booking date
`Data di contabilizzazione` rather than `Data di registrazione`; both are read.

This profile and the split one cannot both match a file: one needs a debit and a
credit column, the other an amount column, and no UBS export has all three.

## `ubs.creditcard`

```
Numéro de compte;Numéro de carte;Titulaire de compte/carte;Date d'achat;Texte comptable;Secteur;Montant;Monnaie originale;Cours;Monnaie;Débit;Crédit;Ecriture
ZZZZ ZZZZ ZZZZ;XXXX XXXX XXXX 0001;JEAN MUSTER;13.11.2026;www.example.ch LUGANO CHE;Magasin d ordinateurs;189.00;CHF;;CHF;189.00;;15.11.2026
```

The booking date is called `Ecriture`, which no shared vocabulary would guess, so
it is named explicitly. The purchase date is reported as the value date.

The amount appears three times — original currency, converted, then split across
debit and credit. Only the debit/credit pair is read, and the currency taken is
the settlement one (`Monnaie`), not `Monnaie originale`.

## Fixtures

Synthetic, one per format.
