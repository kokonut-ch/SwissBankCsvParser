<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Exceptions;

/** The file is missing, unreadable, or empty. */
final class UnreadableFileException extends SwissBankCsvParserException
{
    public static function missing(string $path): self
    {
        return new self("File not found: {$path}");
    }

    public static function unreadable(string $path): self
    {
        return new self("File could not be read: {$path}");
    }

    public static function empty(): self
    {
        return new self('The file contains no data.');
    }
}
