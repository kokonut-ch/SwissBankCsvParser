# PostFinance

Three formats, all of which PostFinance still hands out.

## `postfinance.efinance` — account statement, 2024 onwards

A `Label: value` preamble, a blank line, the heading row, the rows, another blank line, and
a legal disclaimer. Headings follow the customer's language (DE / FR / IT / EN).

```
Date de début:;01.01.2026;;;;;
Compte:;CH9300762011623852957;;;;;
Monnaie:;CHF;;;;;
;;;;;;
Date;Type de transaction;Texte de notification;Crédit en CHF;Débit en CHF;Label;Catégorie
;;;;;;
10.03.2026;Enregistrement comptable;Paiement fournisseur;;-150.5;;Achats
```

Worth knowing:

- **Debits are printed negative** in the debit column. The column already says the
  direction, so the printed sign is discarded — otherwise the sign would flip twice.
- **The currency is in the amount heading**, not only in the preamble: `Crédit en CHF`.
  That is where a foreign-currency account announces itself.
- The `Label` column is a customer-defined tag. It is not in the shared vocabulary, so it
  is ignored; the cell is still in `Row::$raw`.

## `postfinance.creditcard` — credit card statement

Same shape, two dates per row: the date the issuer booked the line, and the date of the
purchase. The purchase date is reported as `valueDate` — the closest thing the neutral
model has to "the other date the bank printed".

```
Compte de carte:;0000 0000 0000 0001;;;;
Titulaire de la carte:;JEAN DUPONT;;;;

Période de facturation;Date d'écriture;Date d'achat;Détails de comptabilisation;Crédit en CHF;Débit en CHF
Période comptable en cours;31.10.2026;28.10.2026;Achat Boutique;;45.9
```

Worth knowing:

- **The account is a card number, not an IBAN.** `Account::$iban` stays null and the number
  is reported as printed, masking included.
- **Debits are printed positive here**, unlike the account statement. Neither profile cares,
  which is the point of taking the direction from the column.
- This format and the account statement are laid out almost identically. What separates
  them is the description heading, which is why both profiles pin theirs with
  `termLabels()` instead of relying on the shared vocabulary.

## `postfinance.legacy` — older statement, no headings

```
15.03.26;Paiement loyer;;1200.00;15.03.26;3800.00
```

Positional: date, description, credit, debit, value date, balance. Two-digit years.

Worth knowing:

- **Nothing in this file names PostFinance.** The profile scores accordingly — low enough
  that any bank identifying itself wins, and low enough that `isConfident()` stays false.
  Treat a match as a suggestion to confirm with the user, never as an identification.
- Widths are pinned to 4–7 columns, and the profile refuses any file that has a heading
  row, so it cannot claim every headerless CSV that happens to start with a date.
- These files carry no currency at all, so `Account::$currency` is null and a
  `CURRENCY_NOT_DETECTED` warning is attached.

## Fixtures

All synthetic. `CH9300762011623852957` is the published Swiss example IBAN; the card number,
the holder and every label were invented. `efinance-de-latin1.csv` is the German fixture
re-encoded as ISO-8859-1, to cover the decoding path.
