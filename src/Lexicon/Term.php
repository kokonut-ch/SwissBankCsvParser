<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Lexicon;

use Kokonut\SwissBankCsvParser\Dto\Row;

/**
 * The things a column can mean.
 *
 * Deliberately small. A term earns its place here only when several banks
 * print a column for it; anything rarer stays in {@see Row::$extras}.
 *
 * Adding a case takes two steps, and doing only the first is silent: give it its
 * headings in {@see Lexicon}, then list it in a profile's required or optional
 * terms. Header resolution walks that list, not the vocabulary, so a term no
 * profile asks for is never matched — the column falls through to
 * {@see Row::$raw} as if the term did not exist.
 */
enum Term: string
{
    case BookingDate = 'booking_date';
    case ValueDate = 'value_date';
    case Description = 'description';

    /** Money in, in files that split the two directions across two columns. */
    case Credit = 'credit';

    /** Money out, same. */
    case Debit = 'debit';

    /** Money in and out in a single, signed column. */
    case Amount = 'amount';

    case Balance = 'balance';
    case Reference = 'reference';

    /**
     * The IBAN of the account the file is about, printed on every row rather
     * than in a header block. Raiffeisen does this.
     */
    case AccountIban = 'account_iban';

    case Currency = 'currency';
    case Category = 'category';
    case TransactionType = 'transaction_type';

    /**
     * The card a row was booked on, as printed and usually masked. Card
     * statements routinely cover several cards issued on one account, and this
     * is the only thing that tells them apart.
     */
    case CardNumber = 'card_number';

    /**
     * Whether the issuer considers the line settled. Card statements list
     * pending authorisations beside booked ones, and the two are not the same
     * claim: a pending line can still change amount or disappear.
     */
    case Status = 'status';
}
