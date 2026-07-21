<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Lexicon;

use Kokonut\SwissBankCsvParser\Dto\Row;

/**
 * The things a column can mean.
 *
 * Deliberately small. A term earns its place here only when several banks
 * print a column for it; anything rarer stays in {@see Row::$extras}.
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
    case Currency = 'currency';
    case Category = 'category';
    case TransactionType = 'transaction_type';
}
