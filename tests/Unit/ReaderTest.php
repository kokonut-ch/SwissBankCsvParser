<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Csv\Reader;
use Kokonut\SwissBankCsvParser\Dto\Warning;

it('picks the delimiter that yields the most consistent table', function (string $csv, string $expected) {
    expect(Reader::read($csv)->delimiter)->toBe($expected);
})->with([
    'semicolon' => ["a;b;c\n1;2;3\n", ';'],
    'comma' => ["a,b,c\n1,2,3\n", ','],
    'tab' => ["a\tb\tc\n1\t2\t3\n", "\t"],
    'pipe' => ["a|b|c\n1|2|3\n", '|'],
    // The description field is full of commas, but only the semicolon splits
    // every row into the same number of fields.
    'commas inside a semicolon file' => [
        "date;text;amount\n01.01.2026;Paiement, frais, divers;10\n01.02.2026;Autre, chose;20\n",
        ';',
    ],
    // One line that splits wide on the comma does not outweigh a whole table
    // that agrees on the semicolon: 10 × 1 against 3 × 4.
    'one comma-heavy line in a semicolon file' => [
        "date;text;amount\n01.01.2026;a, b, c, d, e, f, g, h, i, j;10\n02.01.2026;Paiement;20\n03.01.2026;Autre;30\n",
        ';',
    ],
]);

it('weighs a short table above a long preamble', function () {
    // Seven two-cell "Label:";"value" lines, then a table of five six-column
    // lines whose descriptions and decimal-comma amounts are full of commas.
    // Scoring only the most frequent row width crowned the preamble, 2 × 7 = 14,
    // which the comma then beat with the stray splits of the table, 5 × 4 = 20.
    // The table itself is worth 6 × 5 = 30.
    $csv = <<<'CSV'
    "Relevé de compte de:";"2026-01-01"
    "Relevé de compte jusqu'au:";"2026-01-04"
    ;
    "Description:";"Compte courant"
    ;
    ;
    ;
    "Date";"Libellé";"Message";"Numéro de référence";"Montant";"Valeur"
    "03.01.2026";"Exemple SA, Rue du Test 1, 1000 Lausanne, CH5604835012345678009";"";;"-15,50";"31.12.2025"
    "03.01.2026";"Modèle Sàrl, Avenue de la Gare 2, 1003 Lausanne, CH9300762011623852957";"";;"-918,85";"03.01.2026"
    "03.01.2026";"Assurance Exemple SA, Case postale 100, 1211 Genève, CH5604835012345678009";"";;"-2225,00";"03.01.2026"
    "04.01.2026";"Exemple SA, Rue du Test 1, 1000 Lausanne, CH5604835012345678009";"";;"4500,00";"04.01.2026"
    CSV;

    $document = Reader::read($csv);

    expect($document->delimiter)->toBe(';')
        ->and($document->rows[7])->toHaveCount(6)
        ->and($document->rows[8][4])->toBe('-15,50');
});

it('keeps quoted fields whole', function () {
    $document = Reader::read("a;b\n\"contains; a delimiter\";2\n");

    expect($document->rows[1])->toBe(['contains; a delimiter', '2']);
});

it('keeps a quoted line break inside its field', function () {
    $document = Reader::read("a;b\n\"line one\nline two\";2\n");

    expect($document->rows[1][0])->toBe("line one\nline two");
});

it('strips a byte order mark', function () {
    $document = Reader::read("\xEF\xBB\xBFDatum;Betrag\n01.01.2026;10\n");

    expect($document->rows[0][0])->toBe('Datum');
});

it('decodes Latin-1 and reports having done so', function () {
    $latin1 = (string) iconv('UTF-8', 'ISO-8859-1', "Währung;Betrag\nCHF;10\n");
    $document = Reader::read($latin1);

    expect($document->rows[0][0])->toBe('Währung')
        ->and($document->sourceEncoding)->toBe('Windows-1252')
        ->and($document->warnings[0]->code)->toBe(Warning::ENCODING_CONVERTED);
});

it('leaves valid UTF-8 alone', function () {
    $document = Reader::read("Währung;Betrag\nCHF;10\n");

    expect($document->sourceEncoding)->toBe('UTF-8')
        ->and($document->warnings)->toBe([]);
});

it('collapses the doubled carriage returns Windows exports pick up', function () {
    $document = Reader::read("a;b\r\r\n1;2\r\r\n");

    expect($document->rows)->toHaveCount(2);
});

it('reads the header block and unwraps the Excel text notation', function () {
    $document = Reader::read(<<<'CSV'
    Compte:;="CH9300762011623852957";;
    Monnaie:;CHF;;
    Date;Texte;Montant
    01.01.2026;Paiement;10
    CSV);

    expect($document->metadataValue(['Compte']))->toBe('CH9300762011623852957')
        ->and($document->metadataValue(['Monnaie']))->toBe('CHF')
        ->and($document->metadataValue(['Konto']))->toBeNull();
});

it('matches header block labels loosely', function () {
    $document = Reader::read("Date de début:;01.01.2026\nDate;Montant\n01.01.2026;10\n");

    // Curly apostrophe in the lookup, straight one in the file.
    expect($document->metadataValue(['Date de début']))->toBe('01.01.2026');
});

it('keeps blank lines so row indexes still line up with the file', function () {
    $document = Reader::read("a;b\n\n1;2\n");

    expect($document->rows)->toHaveCount(3)
        ->and($document->rows[1])->toBe([]);
});
