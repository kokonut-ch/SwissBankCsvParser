# Release Notes

## [Unreleased](https://github.com/Kokonut-ch/SwissBankCsvParser/compare/v0.1.0...main)

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
