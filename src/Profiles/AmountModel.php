<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Profiles;

/** How a file expresses the direction of the money. */
enum AmountModel
{
    /**
     * Two columns, one for money in and one for money out, at most one of them
     * filled per row. The sign printed in the cell is ignored — the column the
     * value sits in is what decides, which is what banks actually mean even
     * when they print debits as negatives.
     */
    case SplitColumns;

    /** A single column whose sign carries the direction. Taken at face value. */
    case SignedColumn;

    /**
     * A single signed column written from the card issuer's point of view: a
     * purchase is printed positive, because it is what you owe, and a refund
     * negative. The sign is flipped so that, as everywhere else in this package,
     * a negative amount means money left the cardholder.
     *
     * Swisscard and Cornèr Card both do this. Reading either at face value turns
     * every purchase into income, which is the kind of error that reconciles to
     * exactly twice the right number and is spotted late.
     */
    case InvertedSignedColumn;
}
