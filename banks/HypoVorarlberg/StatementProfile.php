<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Banks\HypoVorarlberg;

use Kokonut\SwissBankCsvParser\Dto\BankIdentity;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Lexicon\Term;
use Kokonut\SwissBankCsvParser\Profiles\AmountModel;
use Kokonut\SwissBankCsvParser\Profiles\HeaderDrivenProfile;

/**
 * Hypo Vorarlberg account statement.
 *
 * Austrian rather than Swiss — it is here because the bank serves the border
 * region and its statements land on Swiss desks. The country on
 * {@see BankIdentity} says so.
 *
 * By far the most detailed export this package reads: SEPA mandate and creditor
 * identifiers, the counterparty's name, BIC and account, fee information, three
 * kinds of category. Only what the neutral model has a field for is mapped; all
 * of it survives in {@see Row::$raw}.
 *
 * One Austrian habit: amounts use a comma decimal (`-40,51`). Dates are ISO
 * with dashes (`2026-12-31`) in every attested sample; the dotted form
 * (`2026.12.31`) is accepted defensively. The amount is signed the ordinary
 * way.
 */
final class StatementProfile extends HeaderDrivenProfile
{
    public function identity(): BankIdentity
    {
        return new BankIdentity('hypovorarlberg', 'Hypo Vorarlberg', 'at');
    }

    public function id(): string
    {
        return 'hypovorarlberg.statement';
    }

    public function priority(): int
    {
        return 20;
    }

    protected function amountModel(): AmountModel
    {
        return AmountModel::SignedColumn;
    }

    protected function dateFormats(): array
    {
        return ['Y-m-d', 'Y.m.d', 'd.m.Y'];
    }

    protected function termLabels(): array
    {
        return [
            Term::Description->value => ['Buchungstext', 'Umsatztext', 'Name des Partners'],
            Term::Reference->value => ['Zahlungsreferenz', 'Eigene Referenz'],
        ];
    }

    protected function signatureHeadings(): array
    {
        return [
            'Auszugsnummer', 'Bestandskategorie', 'Umsatzkategorie',
            'Entgeltinformationen', 'Mandat ID', 'Creditor ID',
        ];
    }

    protected function requiresSignature(): bool
    {
        return true;
    }
}
