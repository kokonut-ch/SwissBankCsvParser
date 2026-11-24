<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Banks\MigrosBank\CardProfile;
use Kokonut\SwissBankCsvParser\Banks\MigrosBank\StatementProfile;
use Kokonut\SwissBankCsvParser\Csv\Reader;
use Kokonut\SwissBankCsvParser\Detection\Candidate;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\Row;
use Kokonut\SwissBankCsvParser\Dto\Warning;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;

/** The parser a caller builds once the user has confirmed the bank: Migros Bank's profiles only. */
function migrosBankParser(): SwissBankCsvParser
{
    return new SwissBankCsvParser(new ProfileRegistry([new CardProfile, new StatementProfile]));
}

function migrosStatementFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../fixtures/'.$name);
}

/** The Migros Bank statement candidate of a free detection, if any. */
function migrosStatementCandidate(string $csv): ?Candidate
{
    foreach ((new SwissBankCsvParser)->detect($csv) as $candidate) {
        if ($candidate->profile === 'migrosbank.statement') {
            return $candidate;
        }
    }

    return null;
}

it('reads the current German layout', function () {
    $file = migrosBankParser()->parse(migrosStatementFixture('statement-de.csv'));

    expect($file->bank->key)->toBe('migrosbank')
        ->and($file->profile)->toBe('migrosbank.statement')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->amount)->toBe('1200.00')
        ->and($file->rows[0]->balance)->toBe('5200.00')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-01-05')
        ->and($file->rows[1]->amount)->toBe('-350.40')
        ->and($file->rows[2]->amount)->toBe('-1000.00')
        ->and($file->rows[2]->balance)->toBe('3849.60');
});

it('joins the booking text and the message, and keeps the reference apart', function () {
    $rows = migrosBankParser()->parse(migrosStatementFixture('statement-de.csv'))->rows;

    expect($rows[0]->label)->toBe('Beispiel AG, Bahnhofstrasse 1, 8001 Zürich, CH5604835012345678009 Rechnung 2026-001')
        ->and($rows[0]->reference)->toBeNull()
        // An empty message adds nothing to the label.
        ->and($rows[1]->label)->toBe('Muster Versicherung AG, Postfach, 3000 Bern')
        ->and($rows[1]->reference)->toBe('12 34567 89012 34567 89012 34560')
        ->and($rows[2]->label)->toBe('Kontoübertrag');
});

it('reads the preamble, and keeps the designation out of the holder', function () {
    $file = migrosBankParser()->parse(migrosStatementFixture('statement-de.csv'));

    expect($file->period->from?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($file->period->to?->format('Y-m-d'))->toBe('2026-03-01')
        // "123.456.78 / CH93 0076 2011 6238 5295 7": the IBAN, since it is valid.
        ->and($file->account->iban)->toBe('CH9300762011623852957')
        ->and($file->account->number)->toBeNull()
        // "Bezeichnung: Privatkonto" is the kind of account, not its holder.
        ->and($file->account->holder)->toBeNull()
        ->and($file->metadata['Bezeichnung'] ?? null)->toBe('Privatkonto')
        ->and($file->metadata['Vertrag'] ?? null)->toBe('12345678');
});

it('says so when the file names no currency', function () {
    // The current layout prints "Saldo:" with nothing after it, and no amount
    // heading carries a currency either.
    $file = migrosBankParser()->parse(migrosStatementFixture('statement-de.csv'));

    expect($file->account->currency)->toBeNull()
        ->and($file->hasWarning(Warning::CURRENCY_NOT_DETECTED))->toBeTrue();
});

it('keeps the account number when the IBAN beside it is not a valid one', function () {
    $csv = str_replace(
        'CH93 0076 2011 6238 5295 7',
        'CH11 1111 1111 1111 1111 1',
        migrosStatementFixture('statement-de.csv'),
    );

    $account = migrosBankParser()->parse($csv)->account;

    expect($account->iban)->toBeNull()
        ->and($account->number)->toBe('123.456.78');
});

it('reads the Italian layout', function () {
    $file = migrosBankParser()->parse(migrosStatementFixture('statement-it.csv'));

    expect($file->profile)->toBe('migrosbank.statement')
        ->and($file)->toHaveCount(3)
        ->and($file->rows[0]->label)->toBe('Ditta Campione SA, Via Prova 2, 6500 Bellinzona, CH5604835012345678009 Salario novembre')
        ->and($file->rows[0]->amount)->toBe('2400.00')
        // "Valuta" is the value date in this file, Italian or not.
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2026-11-02')
        ->and($file->rows[1]->label)->toBe('Assicurazione Esempio SA, Casella postale, 6900 Lugano')
        ->and($file->rows[1]->reference)->toBe('12 34567 89012 34567 89012 34560')
        ->and($file->rows[1]->amount)->toBe('-226.60')
        ->and($file->period->from?->format('Y-m-d'))->toBe('2026-11-01')
        ->and($file->period->to?->format('Y-m-d'))->toBe('2026-11-30')
        ->and($file->account->holder)->toBeNull()
        ->and($file->metadata['Denominazione'] ?? null)->toBe('Conto risparmio');
});

it('leaves the period empty when its dates cannot be read, rather than failing', function () {
    // Published samples carry anonymised dates of this shape: an ISO notation
    // with an impossible month.
    $csv = str_replace(
        ['2026-11-01', '2026-11-30'],
        ['2026-13-41', '2026-14-58'],
        migrosStatementFixture('statement-it.csv'),
    );

    $file = migrosBankParser()->parse($csv);

    expect($file->period->from)->toBeNull()
        ->and($file->period->to)->toBeNull()
        ->and($file)->toHaveCount(3);
});

it('reads the French layout, decimal commas included', function () {
    $file = migrosBankParser()->parse(migrosStatementFixture('statement-fr.csv'));

    expect($file->profile)->toBe('migrosbank.statement')
        ->and(array_map(static fn (Row $row): ?string => $row->amount, $file->rows))
        ->toBe(['-15.50', '-918.85', '-2225.00', '4500.00'])
        // Booked on the 3rd, valued on the 31st of the previous year.
        ->and($file->rows[0]->date->format('Y-m-d'))->toBe('2026-01-03')
        ->and($file->rows[0]->valueDate?->format('Y-m-d'))->toBe('2025-12-31')
        ->and($file->rows[0]->label)->toBe('Exemple SA, Rue du Test 1, 1000 Lausanne, CH5604835012345678009')
        ->and($file->rows[1]->label)->toBe('Modèle Sàrl, Avenue de la Gare 2, 1003 Lausanne, CH9300762011623852957 Facture 2025-118')
        ->and($file->rows[1]->reference)->toBe('12 34567 89012 34567 89012 34560')
        ->and($file->period->from?->format('Y-m-d'))->toBe('2026-01-01')
        ->and($file->period->to?->format('Y-m-d'))->toBe('2026-01-04')
        // "Description: Compte courant" is the designation, not the holder.
        ->and($file->account->holder)->toBeNull()
        ->and($file->metadata['Description'] ?? null)->toBe('Compte courant');
});

it('splits the French layout on semicolons in free detection too', function () {
    // Seven two-cell preamble lines, then a short table whose descriptions and
    // decimal-comma amounts are full of commas: the trap the delimiter scoring
    // used to fall into.
    $csv = migrosStatementFixture('statement-fr.csv');
    $file = (new SwissBankCsvParser)->parse($csv);

    expect(Reader::read($csv)->delimiter)->toBe(';')
        ->and($file->profile)->toBe('migrosbank.statement')
        ->and($file)->toHaveCount(4)
        ->and($file->rows[3]->amount)->toBe('4500.00');
});

it('identifies the current layout confidently, in every language', function (string $fixture) {
    $report = (new SwissBankCsvParser)->detect(migrosStatementFixture($fixture));

    expect($report->best()?->profile)->toBe('migrosbank.statement')
        ->and($report->best()?->bank->name)->toBe('Migros Bank')
        ->and($report->isConfident())->toBeTrue();
})->with(['statement-de.csv', 'statement-it.csv', 'statement-fr.csv']);

it('never claims the four-column layout in free detection', function (string $fixture) {
    // Valiant, acrevis, Schwyzer KB and Nidwaldner KB print exactly this
    // layout. Nothing in it says Migros Bank, so the generic reader keeps it,
    // and the bank has to be named by whoever uploaded the file.
    $csv = migrosStatementFixture($fixture);
    $report = (new SwissBankCsvParser)->detect($csv);

    expect($report->best()?->profile)->toBe('generic.signed-amount')
        ->and($report->isConfident())->toBeFalse()
        ->and(migrosStatementCandidate($csv)?->score)->toBeLessThan(0.30)
        ->and((new SwissBankCsvParser)->parse($csv)->bank->key)->toBe('unknown');
})->with(['statement-classic-de.csv', 'statement-classic-it.csv']);

it('reads the four-column layout once the bank is confirmed', function () {
    $file = migrosBankParser()->parse(migrosStatementFixture('statement-classic-de.csv'));

    expect($file->profile)->toBe('migrosbank.statement')
        ->and($file)->toHaveCount(3)
        // Two-digit years in this layout.
        ->and($file->rows[0]->date->format('Y-m-d'))->toBe('2026-09-04')
        ->and($file->rows[0]->amount)->toBe('1838.00')
        ->and($file->rows[0]->label)->toBe('Zahlungseingang Beispiel AG')
        ->and($file->rows[1]->amount)->toBe('-204.45')
        ->and($file->rows[1]->valueDate?->format('Y-m-d'))->toBe('2026-09-03')
        ->and($file->account->number)->toBe('123.456.78')
        // "Saldo: CHF 12345.60": the ISO code is picked out of the value.
        ->and($file->account->currency)->toBe('CHF')
        ->and($file->period->from)->toBeNull()
        ->and($file->period->to?->format('Y-m-d'))->toBe('2026-09-04')
        ->and($file->account->holder)->toBeNull()
        ->and($file->metadata['Bezeichnung'] ?? null)->toBe('Privatkonto');
});

it('labels every row of the Italian four-column layout', function () {
    // "Testo di registrazione" was missing from the shared vocabulary, and
    // every label of these files came back empty.
    $rows = migrosBankParser()->parse(migrosStatementFixture('statement-classic-it.csv'))->rows;

    expect($rows)->toHaveCount(3)
        ->and(array_filter($rows, static fn (Row $row): bool => $row->label === ''))->toBe([])
        ->and($rows[0]->label)->toBe('Pagamento Esempio SA, Via Mustera 1, 6900 Lugano')
        ->and($rows[0]->date->format('Y-m-d'))->toBe('2026-09-15')
        ->and($rows[1]->amount)->toBe('2190.00');
});

it('leaves the card export to the card profile, and the statements to this one', function () {
    $statement = new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));
    $card = new SwissBankCsvParser(new ProfileRegistry([new CardProfile]));

    expect($statement->supports(migrosStatementFixture('card.csv')))->toBeFalse()
        ->and($card->supports(migrosStatementFixture('statement-de.csv')))->toBeFalse()
        ->and($card->supports(migrosStatementFixture('statement-classic-de.csv')))->toBeFalse();

    // Confirming the bank changes nothing for the card export.
    $file = migrosBankParser()->parse(migrosStatementFixture('card.csv'));

    expect($file->profile)->toBe('migrosbank.card')
        ->and($file->rows[0]->amount)->toBe('-105.45');
});

it('does not read other banks\' signed-amount exports', function () {
    $statement = new SwissBankCsvParser(new ProfileRegistry([new StatementProfile]));

    expect($statement->supports("Booked At;Text;Credit/Debit Amount;Balance\n2026-02-04;Supplier payment;-1240.55;8759.45\n"))
        ->toBeFalse()
        ->and($statement->supports("Buchungsdatum;Avisierungstext;Betrag\n04.02.2026;Zahlung;-100.00\n"))
        ->toBeFalse();
});
