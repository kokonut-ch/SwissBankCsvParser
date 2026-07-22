# Release Notes

## [Unreleased](https://github.com/Kokonut-ch/SwissBankCsvParser/compare/v0.1.1...main)

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
