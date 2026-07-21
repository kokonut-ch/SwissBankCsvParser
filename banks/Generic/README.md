# Generic

Not a bank. The fallback for files from banks this package does not know yet.

Both profiles rely on nothing but the shared `Lexicon`, so they read a statement from any
institution that names its columns in ordinary German, French, Italian or English — which,
in practice, is most of them.

| Profile | For files with |
| --- | --- |
| `generic.split-columns` | separate credit and debit columns |
| `generic.signed-amount` | one signed amount column |

## Guarantees

**They can never win against a real bank.** Their confidence is capped at 0.30, below every
bank profile in the package.

**They always say they guessed.** Every result carries a `GENERIC_PROFILE_USED` warning and
reports `bank->key === 'unknown'`. A caller that cares about the difference between "read
by the Raiffeisen profile" and "read by pattern-matching the headings" only has to check
that warning.

**They stay unconfident.** `DetectionReport::isConfident()` is false for a generic match, by
construction — which is the signal to ask a human rather than to proceed.

## Why they exist

There will always be more Swiss banks than profiles. Without a fallback, an unknown bank is
a hard failure; with one, it is a readable file plus an honest warning. That is also what
makes a manual column-mapping step possible on the caller's side: the generic reader
produces a mapping proposal that a person can correct.

A generic match is not a reason to skip writing a profile. It is what keeps the file usable
until someone does.
