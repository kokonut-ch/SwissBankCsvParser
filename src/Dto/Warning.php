<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Dto;

/**
 * Something worth telling the caller about, that was not bad enough to refuse
 * the file.
 *
 * Warnings describe the *file*, never what should be done about it. Deciding
 * whether a missing currency is fatal is the caller's call.
 */
final readonly class Warning
{
    /** The file was not valid UTF-8 and was transcoded. */
    public const string ENCODING_CONVERTED = 'encoding_converted';

    /** No currency could be found, neither in the header block nor in a column heading. */
    public const string CURRENCY_NOT_DETECTED = 'currency_not_detected';

    /** A cell that should have held an amount could not be read as one. */
    public const string AMOUNT_NOT_PARSABLE = 'amount_not_parsable';

    /** A cell that should have held a date could not be read as one. */
    public const string DATE_NOT_PARSABLE = 'date_not_parsable';

    /** No bank profile recognised the file; the generic column reader was used. */
    public const string GENERIC_PROFILE_USED = 'generic_profile_used';

    public function __construct(
        /** One of the constants above. Stable across versions; match on this, not on the message. */
        public string $code,
        /** English, developer-facing. Not meant to be shown to end users as-is. */
        public string $message,
        /** @var array<string, scalar|null> */
        public array $context = [],
    ) {}
}
