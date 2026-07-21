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
}
