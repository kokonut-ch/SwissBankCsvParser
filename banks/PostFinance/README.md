# PostFinance

Four profiles, which between them read every account and card statement layout PostFinance
has exported since 2003, as far as the published samples go.

| Layout | Years | Profile | Fixtures |
| --- | --- | --- | --- |
| Account statement, headings in the customer's language | 2024 onwards | `postfinance.efinance` | `efinance-de.csv`, `-fr`, `-it`, `-en`, `-de-latin1` |
| Same columns, older wordings and notations | 2003 to 2023 | `postfinance.efinance` | `efinance-it-2003.csv`, `-it-2009`, `-it-2016`, `-it-iso` |
| Account statement, debit before credit, `Details` column | 2010 to 2013 | `postfinance.efinance-2010` | `efinance-2010-en.csv`, `-de` |
| Account statement, no heading row | around 2012 | `postfinance.legacy` | `legacy.csv` |
| Credit card statement, two dates per row | 2024 onwards | `postfinance.creditcard` | `creditcard-de.csv`, `-fr`, `-en` |
| Credit card statement, one date per row | 2022 to 2023 | `postfinance.creditcard` | `creditcard-de-2023.csv`, `creditcard-it-2022.csv` |

> **Provenance:** the older account-statement wordings, the 2010 export and the 2022 and
> 2023 card exports are derived from the anonymised sample files Banana.ch publishes with
> its import app. They have not been verified against a real export.

## Once the bank is confirmed

A caller that has asked the user which bank the file comes from parses with PostFinance's
profiles alone. Whatever such a parser refuses, the user cannot import, even when the
generic reader would have read the file. So every layout above has a PostFinance profile,
including the ones the generic reader also reads, and `tests/ConfirmedBankTest.php` reads
every fixture through PostFinance's profiles and nothing else.

## `postfinance.efinance`: account statement

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
  direction, so the printed sign is discarded; otherwise the sign would flip twice.
- **The currency is in the amount heading**, not only in the preamble: `Crédit en CHF`.
  That is where a foreign-currency account announces itself.
- The customer-defined tag column is not in the shared vocabulary, so it is ignored; the
  cell is still in `Row::$raw`. The real exports name it `Label` in German and French, and
  `Tag` in Italian and English.
- **A line of text without a date is never folded into the row above.** PostFinance quotes
  a description that runs over several lines, so such a line is a footer, such as the 2003
  export's closing balance.

### Older exports, same columns

The heading row has named the same columns since 2003; the wording and the notation are
what changed. The same profile reads them all:

| Export | What differs |
| --- | --- |
| 2003, Italian | `Data;Descrizione della transazione;Accreditamento;Debito;Valuta;Saldo`, an opening `Saldo` line, a closing `Saldo al …` line |
| 2009, Italian | tab-separated, `Testo d'avviso`, `Data della valuta`, dated `20090401` |
| 2016, Italian | `Data di contabilizzazione;Testo d'avviso;Accredito;Addebito;Valuta;Saldo ;`, credits printed `+104.70` |
| 2016, Italian | `Testo di avviso`, dated `2016-11-14` |
| 2016 and 2017, German | `Buchungsdatum;Avisierungstext;Gutschrift;Lastschrift;Valuta;Saldo` |
| 2023, Italian | `Data;Tipo di movimento;Testo di avviso;Accredito in CHF;Addebito in CHF`, no value date or balance |

Worth knowing:

- `Testo d'avviso` is PostFinance's own wording, like `Avisierungstext`, and identifies the
  file. `Descrizione della transazione` is ordinary Italian: it lets the 2003 export be
  read, but does not make the identification confident.
- The preamble labels of the older exports are anonymised in the published samples. The
  profile reads the account, currency and period labels it knows from the 2024 export
  wherever they appear, and nothing else: for an older file, expect them to be missing.

## `postfinance.efinance-2010`: account statement, 2010 to 2013

```
BookingDate;BookingText;Details;ValutaDate;DebitAmount;CreditAmount;Balance
29.10.2010;E-FINANCE 01-23456-7 - SAMPLE SUPPLIER LTD;2;29.10.2010;-45.00;;4180.20
```

The German export names the same columns and adds three of the old budget tool's:

```
Buchung;Buchungstext;Details;Valuta;Belastung;Gutschrift;Saldo;Kategorie;Familienmitglied;Kommentar
```

Worth knowing:

- **`Details` is not an amount.** It holds a small flag (`1`, `2`) or nothing, and stays in
  `Row::$raw`. Read by position, it lands exactly where the legacy layout expects the
  credit, which is why the legacy profile now refuses this file instead of reporting every
  row as 2.00 (see below).
- The German headings are ordinary words that other banks print too, so the German export
  is recognised on its `Familienmitglied` column. Without it, the profile does not match.
- No currency anywhere: `Account::$currency` is null and a `CURRENCY_NOT_DETECTED` warning
  is attached.
- The German layout is known from the documentation of Banana.ch's import app, not from a
  sample file.

## `postfinance.legacy`: older statement, no headings

```
15.03.26;Paiement loyer;;1200.00;15.03.26;3800.00
```

Positional: date, description, credit, debit, value date, balance. Two-digit years.

Worth knowing:

- **Nothing in this file names PostFinance.** The profile scores accordingly: low enough
  that any bank identifying itself wins, and low enough that `isConfident()` stays false.
  Treat a match as a suggestion to confirm with the user, never as an identification.
- Widths are pinned to 4 to 7 columns, and the profile refuses any file that has a heading
  row, so it cannot claim every headerless CSV that happens to start with a date.
- **Every dated row must fit the layout, or the file is refused.** A date or text in an
  amount column, anything but a date in the value-date column, or a credit and a debit on
  the same line: any of these means the positions are wrong, and reading on would report
  amounts that are not in the file.
- These files carry no currency at all, so `Account::$currency` is null and a
  `CURRENCY_NOT_DETECTED` warning is attached.

## `postfinance.creditcard`: credit card statement

Same shape as the account statement, two dates per row: the date the issuer booked the line,
and the date of the purchase. The purchase date is reported as `valueDate`, the closest
thing the neutral model has to "the other date the bank printed".

```
Compte de carte:;0000 0000 0000 0001;;;;
Titulaire de la carte:;JEAN DUPONT;;;;

Période de facturation;Date d'écriture;Date d'achat;Détails de comptabilisation;Crédit en CHF;Débit en CHF
Période comptable en cours;31.10.2026;28.10.2026;Achat Boutique;;-45.9
```

The exports of 2022 and 2023 had one date per row, dated `2023-08-24`:

```
Kartenkonto:;0000 0000 0000 0001
Datum;Buchungsdetails;Gutschrift in CHF;Lastschrift in CHF
2023-08-24;"Tankstelle Muster";;-94.70
```

and in Italian headed the description `Denominazione`, with a trailing `Importo in CHF`
column that only the closing total fills.

Worth knowing:

- **The account is a card number, not an IBAN.** `Account::$iban` stays null and the number
  is reported as printed, masking included.
- **The printed sign is ignored.** The 2024 export prints debits negative, the 2022 Italian
  one positive; either way the column says the direction.
- This format and the account statement are laid out almost identically. What separates
  them is the description heading, which is why both profiles pin theirs with
  `termLabels()` instead of relying on the shared vocabulary.
- **`Denominazione` alone does not name the bank.** A file with that heading and neither
  the card-account preamble nor the purchase-date column scores below the generic reader:
  it is read as PostFinance only once the caller has restricted the parser to this bank.
- `Importo in CHF` is not read. On the closing total line it is the only figure, so that
  line comes back with a null amount.

## Not covered: SmartBusiness exports

PostFinance's SmartBusiness invoicing tool exports lists of paid invoices
(`client_name;paid_date;paid_amount`) and of offers and invoices. They are not account
statements: importing one next to the account statement would count every payment twice.
They are refused.

## Fixtures

All synthetic. `CH9300762011623852957` is the published Swiss example IBAN; the card number,
the holder and every label were invented. `efinance-de-latin1.csv` is the German fixture
re-encoded as ISO-8859-1, to cover the decoding path. The older layouts reproduce the
structure of Banana.ch's published samples with invented content. Those samples anonymise
the preamble labels too, word for word and consistently within a file, so the fixtures'
labels are reconstructions. Two are well supported and tested: `Conto :` in
`efinance-it-2016.csv` (in the sample, the same anonymised word recurs in the currency
line, as "conto" would) and `Conto della carta:` in `creditcard-it-2022.csv` (in the
sample, the word standing for "carta" recurs in the `Carta:` line). The others are placeholders no test relies on.

Both header-driven formats of 2024 are covered in all four languages PostFinance exports,
because the headings are the only thing that identifies them and a wrong translation would
be invisible to a suite that only ever read German and French:

| | de | fr | it | en |
| --- | --- | --- | --- | --- |
| e-finance | `efinance-de.csv` | `efinance-fr.csv` | `efinance-it.csv` | `efinance-en.csv` |
| credit card | `creditcard-de.csv` | `creditcard-fr.csv` | none | `creditcard-en.csv` |

The Italian e-finance fixture is worth keeping for one reason beyond coverage: it says
`Moneta:`, not `Valuta:`. Italian uses that word for the currency while German uses it for
the value date, so the shared vocabulary lists it under the value date only and this profile
has to name its currency heading itself.
