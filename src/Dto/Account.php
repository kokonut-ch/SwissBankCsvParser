<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Dto;

/**
 * The account the file is about, as far as the file itself says so.
 *
 * Every field is nullable on purpose: plenty of CSV exports carry no header
 * block at all. A null here means "the file does not say", never "unknown, so
 * we guessed something".
 */
final readonly class Account
{
    public function __construct(
        /** Normalised IBAN (no spaces, uppercase) when the file carries one. */
        public ?string $iban = null,
        /** Account or card number exactly as printed, when it is not an IBAN. */
        public ?string $number = null,
        /** ISO 4217 code, uppercase. */
        public ?string $currency = null,
        /** Account or card holder. */
        public ?string $holder = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->iban === null
            && $this->number === null
            && $this->currency === null
            && $this->holder === null;
    }
}
