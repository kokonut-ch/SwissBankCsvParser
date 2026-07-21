<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Exceptions;

/**
 * No profile recognised the file — not even the generic column reader, which
 * means no column could be read as a date next to something readable as an
 * amount.
 */
final class UnsupportedFileException extends SwissBankCsvParserException
{
    public static function notRecognised(): self
    {
        return new self(
            'No bank profile matched this file, and it does not look like a bank statement: '
            .'no column could be read as a date alongside a column readable as an amount.',
        );
    }
}
