# Release Notes

## [Unreleased](https://github.com/Kokonut-ch/SwissBankCsvParser/compare/v0.2.0...main)

### Migros Bank account statements

Only the card export had a profile, so a caller that restricted parsing to Migros Bank, once
the user had named the bank, had every account statement refused. A new profile,
`migrosbank.statement`, reads them in German, French and Italian.

- **The current layout is identified confidently.** It is the one Swiss export in the
  published samples to print a message column and a reference-number column together:
  `Datum;Buchungstext;Mitteilung;Referenznummer;Betrag;[Saldo;]Valuta`, and its French and
  Italian wordings. The label joins the booking text and the message; the reference number
  reaches `Row::$reference`; the value date and the balance are read.
- **Its preamble is read.** The period comes from `Kontoauszug von:` / `bis:` and their
  French and Italian wordings, and a date that cannot be read leaves it `null` rather than
  failing. The account comes from `Kontonummer / IBAN:`, which prints both on one line: the
  IBAN when it is valid, otherwise the number. `Vertrag:` and the account designation stay
  in `metadata`.
- **The older four-column layout is read only once the bank is confirmed.**
  `Datum;Buchungstext;Betrag;Valuta` is exactly what Valiant, acrevis, Schwyzer KB and
  Nidwaldner KB print. The profile caps it at 0.25, below the generic reader's 0.30, so in
  free detection such a file stays unidentified, and a parser restricted to Migros Bank's
  profiles reads it.

### A short table under a long preamble picks the right delimiter

The delimiter was chosen on the most frequent row width alone. Migros Bank's French export
prints seven two-cell preamble lines above a short table full of commas, decimal commas
included, so the preamble won the count and the comma then beat the semicolon on the
table's stray splits: the file was refused outright. Every row width is now weighed, and
the largest block of agreeing rows wins. Across the 176 published sample files, that one
file is the only one whose delimiter changes.

### Shared vocabulary

`Testo di registrazione` and `Message` are now description headings. Italian Migros Bank
statements came back with every label empty.

### The generic reader no longer reports an account designation as the holder

`Bezeichnung:`, and `Description:` in French, are what the de-facto cantonal-bank preamble
prints most often, and in every published sample the value is the kind of account:
`Geschäftskonto`, `Kontokorrent`, `Betriebskredit`, `Compte courant CHF`. The holder's
name sits on the unlabelled address lines below. Those labels, with `Désignation` and
`Designazione`, no longer fill `Account::$holder`, which is now `null` for such files; the
value is still in `metadata`. Labels that name a person (`Kontoinhaber`, `Titulaire`,
`Intestatario`, `Account holder`) are still read.

### PostFinance: no false amount, and every layout read once the bank is confirmed

A caller that restricts parsing to PostFinance, once the user has named the bank, had three
published PostFinance samples refused that the generic reader read. One more was read with
every amount wrong, two parsed to zero rows, and one was refused by every profile. All of
them are now read. Across the 176 published sample files, only PostFinance's change.

- **The legacy profile refuses a file whose cells contradict its positions.** PostFinance's
  2010 export, `BookingDate;BookingText;Details;ValutaDate;DebitAmount;CreditAmount;Balance`,
  has English headings the shared vocabulary does not know, so it passed for a headerless
  file, and `postfinance.legacy` read its `Details` flag as the credit: every row came back
  as 2.00. A dated row with a date or text in an amount column, anything but a date in the
  value-date column, or both a credit and a debit, now makes the profile refuse the file.
- **A new profile, `postfinance.efinance-2010`, reads that export**, in English and in its
  German wording (`Buchung;Buchungstext;Details;Valuta;Belastung;Gutschrift;Saldo;…`). It is
  recognised on the camel-cased headings and on the German export's `Familienmitglied`
  column, and requires one of them. `Details` stays in `Row::$raw`.
- **`postfinance.efinance` reads the older account statements**, whose columns were always
  the same: `Testo d'avviso` (2009, 2016), the 2003 wording `Descrizione della transazione`
  / `Accreditamento` / `Debito`, `Data della valuta`, and rows dated `2016-11-14` or
  `20090401`. `Testo d'avviso` is PostFinance's own wording and identifies the file; the 2003
  wording is ordinary Italian and does not. A line of text without a date is no longer
  folded into the row above: PostFinance quotes multi-line descriptions, so such a line is
  a footer, such as the 2003 export's closing balance.
- **`postfinance.creditcard` reads the 2022 and 2023 card exports**, dated `2023-08-24`, and
  in Italian headed `Denominazione`. That heading alone does not name the bank: without the
  card-account preamble or the purchase-date column, such a file scores 0.25, below the
  generic reader, and is read as PostFinance only by a parser restricted to this bank.
- Two samples the generic reader used to read, the 2009 and 2016 Italian statements, are
  now identified as PostFinance. Their dates and amounts are unchanged, and their labels,
  which the generic reader left empty, are read.

### Bank Cler: the German export, once the bank is confirmed

Bank Cler published a German export in July 2026,
`Buchungsdatum;Auftragsart;Text;Belastungsbetrag (CHF);Gutschriftsbetrag (CHF);Saldo (CHF)`.
`cler.statement` signed only on the Italian, French and English order type headings, so a
caller that restricted parsing to Bank Cler had that file refused.

- **`cler.statement` reads the German layout**, with or without `Valutadatum`. The currency
  comes from the bracketed heading, CHF, EUR or USD, as in the other languages; texts
  quoted over several lines make one label; `Auftragsart` is kept as an extra.
- **It never claims that layout in free detection.** Basler Kantonalbank, Bank Cler's
  parent, exports exactly the same headings, so nothing in the file names the bank. The
  profile caps it at 0.25, below the generic reader's 0.30: in free detection such a file
  stays unidentified, and a parser restricted to Bank Cler reads it.
- The German layout needs `Belastungsbetrag` and `Gutschriftsbetrag` beside `Auftragsart`,
  which keeps Thurgauer Kantonalbank's export (`Belastung (CHF)`, `Gutschrift (CHF)`) out.
- Across the 176 published sample files, only that German Cler sample changes: refused
  once the bank is confirmed, it is now read with all its rows.

### Swisscard in French

Swisscard added a French export in November 2026, the twelve-column layout headed
`Date transaction,Description,Commerçant,Numéro de carte,Monnaie,Montant,…,Débit/Crédit,Statut,Catégorie de commerçant,Catégorie enregistrée`.
No profile knew `Date transaction` or `Catégorie enregistrée`, so the file was refused,
in free detection and with the bank confirmed alike.

- **`swisscard.statement` reads it**, identified confidently on `Catégorie enregistrée`,
  with the issuer sign flipped as in the other languages: a purchase (`Débit`) is
  negative, a card payment (`Crédit`) positive.
- **`Numéro de carte`, `Statut` and `Catégorie enregistrée` reach `extras`**, like
  their German, Italian and English counterparts. The settled `Monnaie` and `Montant`
  are read, never the foreign pair beside them.
- **`Débit/Crédit` signs the file too**, as `Debit/Kredit` does in German, for a French
  file without the registered category.
- Both French headings are claimed by the Swisscard profile, not added to the shared
  vocabulary: no other profile changes.
- Across the 176 published sample files, only that French sample changes: refused, it is
  now read with all its rows, in free detection and once the bank is confirmed.

### Schaffhauser Kantonalbank, once the bank is confirmed

Banana.ch published a sample of Schaffhauser Kantonalbank's export in October 2026. It is
the four-column statement Migros Bank's older layout, Valiant, acrevis, Schwyzer KB,
Nidwaldner KB and Zuger KB print too: `Kontoauszug bis:` / `Kontonummer:` / `Bezeichnung:` / `Saldo:`, then
`Datum;Buchungstext;Betrag;Valuta`. No profile was named after the bank, so a caller that
restricted parsing to it had nothing to restrict to.

- **A new profile, `shkb.statement`, reads it** once the bank is confirmed: rows dated
  `dd.mm.yy` (or `dd.mm.yyyy`), the signed amount, the value date, and a running balance
  when one is printed. The preamble gives the period's end, the account number and, from
  `Saldo: CHF …`, the currency. `Bezeichnung:` is the account's designation and stays in
  `metadata`, never the holder.
- **It never claims that layout in free detection.** Nothing in the file names the bank,
  so the profile caps it at 0.25, below the generic reader's 0.30: in free detection such a
  file stays unidentified, and a parser restricted to Schaffhauser Kantonalbank reads it. A
  heading row with any other column is refused, even with the bank confirmed.
- **The layout's reading is shared, not copied.** Its headings, preamble labels, date
  notations and ceiling move to `banks/Generic/FourColumnStatement.php`, which Migros
  Bank's statement profile now uses too. Migros Bank reads every file exactly as before.
- Across the 176 published sample files, free detection is unchanged. The Schaffhauser KB
  sample, read by the generic reader, is read with all its rows once the bank is
  confirmed, dates and amounts as Banana.ch's.

### Changed

**UBS: what an application receives changes on four points.** The row-date rule itself
does not change; it is now stated, with its evidence, in `banks/UBS/README.md`.

- **The row date, stated.** On every UBS account layout, `Row::$date` is the booking date
  (`Buchungsdatum`, `Date de comptabilisation`, `Data di registrazione`,
  `Data di contabilizzazione`, `Booking date`), and the trade date only when the booking
  date is empty, as the e-banking exports leave it on a payment UBS has not booked yet.
  Banana.ch's import dates the two e-banking layouts by the trade date instead: in its 2022
  sample, 126 of 195 rows differ, booked one to five days after they were made. This
  package keeps the booking date.
- **The trade date reaches `extras`**, as printed, under its heading as printed:
  `['Abschlussdatum' => '2022-11-04']`. It used to be in `Row::$raw` only, so an
  application comparing `extras` exactly now sees one more key on UBS rows. On the
  e-banking layouts, it reproduces Banana.ch's date on every published sample row. `Date de conclusion`
  and `Data di chiusura`, the French and Italian portfolio wordings, are recognised as the
  trade date too: a row of such a file with an empty booking date, which no published
  sample has, is dated by it instead of being dropped.
- **A reversed credit is negative.** The portfolio export prints it in the credit column
  with a minus, `-500.00` under `Gutschrift`, and the running balance falls by that amount.
  It was read as money received; it is now money out. Debits are unchanged, always negative.
- **A row split by an unquoted semicolon is read.** A portfolio description containing
  ` ; ` shifted every later column one cell to the right, and the row came back with no
  amount and its credit as the balance. Such a row is rejoined where a delimiter has a
  space on either side, when that explains every surplus cell. `Row::$raw` then holds the
  rejoined cells.
- **A UBS card line without `Ecriture` no longer lends its text to the row above.** A
  purchase not yet booked, or the balance carried over, was folded into the label of the
  booked purchase before it. Such a line has no booking date and is not a row.
- Across the 176 published sample files, only one reads differently: Banana.ch's 2018 UBS
  portfolio sample, which had 1263 of its 1270 rows right (six reversed credits, one split
  row), now has all 1270, in free detection and once the bank is confirmed.

## [v0.2.0](https://github.com/Kokonut-ch/SwissBankCsvParser/releases/tag/v0.2.0) - 2026-07-23

Every profile was re-audited against the source it was derived from: the open-source import
apps published by Banana.ch, this time including their real test files, not only their
documentation. The audit found one pattern, repeated: a language or layout variant that
really exists was not covered, and the failure was silent — the file either fell through to
the generic reader, which reads card signs backwards, or parsed to zero rows without a
warning. Everything below was reproduced with upstream's own sample files before being
fixed, and each fix ships with the fixture that would have caught it.

### Files that were read wrong now read right

- **Cornèrcard in German and Italian.** The profile's gate was three literal English
  headings, so `Datum;Beschreibung;Karte;Währung;Betrag;Status` fell to the generic reader
  and every purchase came back as income. The gate is now the same three columns as
  lexicon terms, one rule for all three languages, with a fixture per language.
- **Swisscard's other layout, and its Italian currency.** Two real layouts exist; the 2023
  one has no `Registered Category` heading, which was the profile's only signature — same
  silent fall-through, same inverted signs. The debit/credit word column, unique to
  Swisscard, now signs the file too. Italian files also lost their currency: `Valuta` is
  currency in this file, value date in the shared vocabulary, and the profile now says so.
  The fixtures were re-cut to the real column sets — twelve columns, not eight — including
  a pending foreign-currency row with no settled amount, and a line where the debit/credit
  word contradicts the sign, proving the sign wins.
- **Cornèr Banca's 2024 layout.** It drops `Conto No.` from the heading row, and only the
  German variant carried another recognised signature — the Italian file was rejected
  outright. Each language now has one: `Erfassungsdatum`, `Registration date`, and for
  Italian the `Dettaglio` column, which is Cornèr's alone. (Its date heading would have
  collided with EFG, which prints the same one.)
- **Yuh's real dates.** The export prints `dd/mm/yyyy` with slashes; the profile knew only
  dots, from an outdated upstream comment. Detection succeeded and every row was then
  silently dropped — detection never consults the date formats, so nothing warned. Slashed
  dates are now read.
- **TWINT in English.** The English report prints `State` where the German prints
  `Status`, and a `Failed` line still carries an amount. The word was missing from the
  vocabulary, so `extras` came back empty and a failed payment was indistinguishable from
  money cashed. `State` is now a status label, and the English fixture carries a `Failed`
  line to keep it that way.
- **PostFinance's English card holder.** The real label is `Card owner:`, not
  `Card holder:`; the fixture had copied the code's wrong guess, which hid the miss. The
  holder no longer comes back null on English exports.
- **UBS in Italian, since 2024.** The booking date is now labelled
  `Data di contabilizzazione`; unrecognised, the column was invisible and the row date
  silently fell back to the trade date — the same file in German or French kept the
  booking date. Both Italian labels are read now. The date rule itself is unchanged and
  now honestly documented: the booking date is the row's date on every UBS layout because
  it is the date the balance moves on — that is this package's rule, where UBS's own
  modern import rules read the trade date alone.

### Migros Bank's card profile was wrong twice

The published sample of Migros Bank's own card export refutes both of the profile's
assumptions. It carries `StateType` — the column the profile treated as Viseca's
distinguishing mark and rejected — and it prints every purchase positive, issuer-style,
where the profile took signs at face value. A real Migros Bank file was either claimed by
`viseca.card` or rejected, and its purchases would have read as income.

Both are fixed. The amounts flip, as they do for Viseca, Swisscard and Cornèrcard, and the
two near-identical exports are told apart by the one heading that actually differs in the
published samples: the trailing `Exchange Rate` column, present in Migros Bank's file and
absent from Viseca's. Migros Bank signs on it; Viseca declares it disqualifying. That
evidence base is one sample per bank, and both READMEs say so plainly.

### `Row::$reference` for TWINT

The `TWINT Order ID` — the identifier that ties a settlement line back to the terminal
transaction, and what upstream books as each entry's external reference — now reaches
`Row::$reference` instead of serving detection alone.

### Fixtures that reconcile

Four fixtures carried running balances that did not add up — invented numbers, precisely
what a balance column exists to catch, and in every case the test suite asserted only the
balances that happened to agree. BancaStato, ZKB and BEKB now chain, and every balance is
asserted. BCV goes the other way: the only published sample leaves its balance column
empty on every row, so the fixture now does too, and gains the real eight-line preamble.
BancaStato also loses an invented detail line no real layout carries, and its `Tipo`
column moves from the label into `extras`, the way Bank Cler's order type is reported.

### Documentation set straight

The Viseca README's sample row printed a purchase negative while the real export prints it
positive — read literally, the sample inverted every amount — and its Migros Bank
comparison called the two files "same signed amount" when the sign conventions are
opposite. ZKB's claim that `Betrag Detail` survives in `Row::$raw` was untrue in the case
that actually occurs: real exports print those per-item amounts on continuation lines,
and folding keeps their text, not their cells — the breakdown is lost, the total intact,
and the README now says which. Hypo Vorarlberg's "dot-separated ISO dates" exist in no
attested sample; dashes do, and the dotted form is documented as a defensive tolerance and
finally exercised by a test. Cler's French variant and newest-first delivery, Raiffeisen's
deliberate continuation folding, and PostFinance's `Tag`/`Label` column naming are
documented from the upstream evidence.

**Upgrade notes:**

- **`migrosbank.card` flips its signs.** Amounts from Migros Bank card files now come back
  with the opposite sign relative to `v0.1.x`, matching the issuer-view convention the
  published sample shows. A file shaped like the old synthetic fixture — no `StateType`,
  no `Exchange Rate`, a shape no published evidence attests — now falls to the generic
  reader instead of being claimed.
- **BancaStato labels shrink.** The order type (`Bonifico`, `Pagamento`) leaves the label
  and appears as `extras['Tipo']`.
- **`extras` gains keys.** Any bank printing a `State` heading reports it (TWINT's English
  export does); Cornèrcard's German and Italian files report `Karte`/`Carta` and `Stato`.
  Code reading `extras` by key is unaffected; code comparing whole arrays will see the new
  entries. TWINT rows also now carry a `reference`.
- Files that previously parsed keep their dates, amounts and balances unchanged — except
  where a value was demonstrably wrong and listed above.

## [v0.1.2](https://github.com/Kokonut-ch/SwissBankCsvParser/releases/tag/v0.1.2) - 2026-07-22

Republishes `v0.1.1`, whose package never contained the code its notes described.

The `v0.1.1` tag was first created on a pull request branch, and Packagist crawled it there
two minutes before that branch was squash-merged. Moving the tag onto the squashed commit
afterwards changed nothing: Packagist keeps the reference it first saw for a tag it already
knows, precisely so that a published tag cannot be repointed at other code. The archive it
serves as `v0.1.1` therefore has a `src/` identical, byte for byte, to `v0.1.0`.

So anyone who installed `v0.1.1` is running `v0.1.0`. `Term::CardNumber` and `Term::Status`
do not exist in that archive, and a column no term matches never reaches `extras` at all —
card numbers and booking status fall through to `Row::$raw`, exactly as before. The
PostFinance Italian and English fixtures are missing too, though those only ever proved
behaviour rather than provided it.

Nothing here is new. `v0.1.2` is `v0.1.1` as it was written, plus the two documentation
corrections below, tagged on `main` after the merge rather than on the branch before it.

### Documentation

`Row::$extras` and `Row::$raw` were described as though they were one idea. They are two. A
column the shared vocabulary recognises but the neutral model does not own reaches
`extras`, keyed by the heading as printed. A column no term matches is not discarded
either, but it survives in `raw` alone, reachable only by counting columns — which a bank
that reorders its export will break. The README now draws that line, and names the card and
status columns each bank reports.

The Yuh README still listed card numbers among the columns that stay in `raw`, which
`v0.1.1` had made false. A test now asserts `CARD NUMBER` reaches `extras`, so the sentence
and the behaviour cannot drift apart again.

**Upgrade note:** `composer update kokonut-ch/laravel-swiss-bank-csv-parser` is enough.
`v0.1.2` is a different commit, so Composer fetches it rather than reusing the archive it
cached for `v0.1.1`.

## [v0.1.1](https://github.com/Kokonut-ch/SwissBankCsvParser/releases/tag/v0.1.1) - 2026-07-22

Documentation, test coverage, and one thing the model promised but did not deliver.

No profile changed how it reads a file: dates, labels, amounts, signs and balances are what
they were in `v0.1.0`. The one behavioural change is additive and confined to `Row::$extras`
— see the upgrade note below.

### Laravel facade

`SwissBankCsvParser::parse(...)` already worked in `v0.1.0` — the facade shipped, and
package discovery registered it — but nothing in the suite proved it and the README taught
something else. Both are fixed.

The README is now written for the only way this package is actually installed: every
example goes through the facade, and the bare `new SwissBankCsvParser` instantiation is
gone. A feature test asserts the provider binds the parser as a singleton, that the facade
forwards all five methods, and that the short alias package discovery registers resolves.

One of those tests compares the facade's `@method` tags against the parser's public methods
and fails when they diverge. That is the tag list an IDE reads to complete
`SwissBankCsvParser::`, and until now nothing stopped it falling silently behind the class.

### PostFinance in Italian and English

Both header-driven PostFinance formats declare Italian and English column headings, and
neither language had a fixture — the suite only ever read German and French. A mistranslated
heading would have passed CI and failed on a customer's export. Four fixtures and their
tests close that gap, covering the account statement in all four languages and the card
statement in three.

The Italian fixture pins a trap worth naming: the file says `Moneta:`, not `Valuta:`.
Italian uses that word for the currency, German uses it for the value date, and the shared
vocabulary lists it under the value date only.

### Card number and booking status now reach `extras`

`Row::$extras` is documented as the place where recognised but non-core columns land,
"categories, tags, card numbers, and so on". Card numbers did not, in fact, land there: the
shared vocabulary had no term for them, and a column no term matches never reaches `extras`
at all. It survived only in `Row::$raw`, reachable by counting columns.

`Term::CardNumber` and `Term::Status` close that. Five banks print one or both and now
report them:

| Bank | New in `extras` |
| --- | --- |
| Swisscard | `Card number`, `Status` |
| Cornèrcard | `Card`, `Status` |
| TWINT | `Status` |
| UBS (card) | `Numéro de carte` |
| Yuh | `CARD NUMBER` |

Status is the one that changes an answer. Card statements list pending authorisations
beside settled bookings, and a pending line can still change amount or disappear. Until now
both came back identical, and on Swisscard — whose own column names are inferred from
documentation — the only way to tell them apart was the positional index this package tells
you not to rely on.

Reporting is all that happens. Whether a pending row counts is the caller's decision, as
ever.

**Upgrade note:** `extras` gains keys for those five banks. Code reading it by key is
unaffected; code comparing the whole array against a literal will see the new entries.

### Maintenance

Removed an empty `boot()` from the service provider, which tested `runningInConsole()` and
then did nothing.

## [v0.1.0](https://github.com/Kokonut-ch/SwissBankCsvParser/releases/tag/v0.1.0) - 2026-07-21

First public release.

Reads the CSV account statements Swiss banks export and returns one predictable
structure, whatever the bank, the language or the layout.

### Banks

Eighteen banks and twenty-four profiles: PostFinance, UBS, Raiffeisen, Zürcher
Kantonalbank, Banque Cantonale Vaudoise, Berner Kantonalbank, Banca dello Stato del
Cantone Ticino, Bank Cler, Cornèr Banca, Yuh, neon, Migros Bank, Viseca, Swisscard,
Cornèrcard, TWINT, EFG Bank and Hypo Vorarlberg.

A generic fallback reads statements from banks not yet known, as long as they name their
columns in German, French, Italian or English. It never outranks a bank that identifies
itself, and always says it is guessing.

Several formats are deliberately left to that fallback rather than claimed. Six banks ship
an account statement that is indistinguishable from one another's, and naming one of them
would be confidently wrong a good share of the time.

### Output

Dates, labels and exact decimal amounts as strings, never floats. A negative amount means
money left the account, including on the card statements that print a purchase positive.
Null means the file did not say, never a plausible guess. Rows come back in file order.
Whatever the model does not cover survives untouched alongside each row.

### Untrusted input

Nothing is executed or evaluated, and no path named inside a file is read. A spreadsheet
formula where a number belongs is reported as zero with a warning rather than guessed at,
while text columns are returned verbatim so the caller can decide how to escape them.

### Known limit

Seventeen of the eighteen banks are marked *not yet verified against a real export* in the
README. Their profiles were built from published format documentation, cross-checked bank
by bank and covered by tests, but not yet confronted with a statement we have held. That
distinction is why this release is `0.1.0` and not `1.0.0`.
