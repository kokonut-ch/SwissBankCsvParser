<?php

declare(strict_types=1);

use Kokonut\SwissBankCsvParser\Detection\DetectionReport;
use Kokonut\SwissBankCsvParser\Detection\ProfileRegistry;
use Kokonut\SwissBankCsvParser\Dto\ParsedFile;
use Kokonut\SwissBankCsvParser\Facades\SwissBankCsvParser as Facade;
use Kokonut\SwissBankCsvParser\SwissBankCsvParser;
use Kokonut\SwissBankCsvParser\SwissBankCsvParserServiceProvider;

function facadeFixture(string $name): string
{
    return dirname(__DIR__, 2).'/banks/PostFinance/fixtures/'.$name;
}

it('registers the parser as a singleton', function () {
    // A singleton and not a fresh instance per resolution: profile discovery
    // scans the filesystem, and an application resolving the parser inside a
    // loop should not pay for it every time.
    expect(app(SwissBankCsvParser::class))->toBeInstanceOf(SwissBankCsvParser::class)
        ->and(app(SwissBankCsvParser::class))->toBe(app(SwissBankCsvParser::class));
});

it('resolves the facade to the registered singleton', function () {
    expect(Facade::getFacadeRoot())->toBe(app(SwissBankCsvParser::class));
});

it('parses through the facade', function () {
    $file = Facade::parse((string) file_get_contents(facadeFixture('efinance-fr.csv')));

    expect($file)->toBeInstanceOf(ParsedFile::class)
        ->and($file->bank->name)->toBe('PostFinance')
        ->and($file->profile)->toBe('postfinance.efinance')
        ->and($file->account->iban)->toBe('CH9300762011623852957');
});

it('reads a file from disk through the facade', function () {
    expect(Facade::parseFile(facadeFixture('efinance-fr.csv'))->account->currency)->toBe('CHF');
});

it('detects and reports support through the facade', function () {
    $csv = (string) file_get_contents(facadeFixture('creditcard-fr.csv'));

    expect(Facade::detect($csv))->toBeInstanceOf(DetectionReport::class)
        ->and(Facade::detect($csv)->best()?->profile)->toBe('postfinance.creditcard')
        ->and(Facade::supports($csv))->toBeTrue();
});

it('exposes the profile registry through the facade', function () {
    expect(Facade::profiles())->toBeInstanceOf(ProfileRegistry::class)
        ->and(Facade::profiles()->banks())->toContain('postfinance');
});

it('answers to the short class alias Laravel registers', function () {
    // What "SwissBankCsvParser::parse(...)" resolves to in an application that
    // never imported anything, courtesy of the alias in composer.json.
    expect(class_exists('SwissBankCsvParser'))->toBeTrue()
        ->and(\SwissBankCsvParser::parseFile(facadeFixture('efinance-fr.csv'))->bank->key)
        ->toBe('postfinance');
});

it('declares the alias and the provider for package discovery', function () {
    $composer = json_decode(
        (string) file_get_contents(dirname(__DIR__, 2).'/composer.json'),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect($composer['extra']['laravel']['providers'])
        ->toContain(SwissBankCsvParserServiceProvider::class)
        ->and($composer['extra']['laravel']['aliases'])
        ->toBe(['SwissBankCsvParser' => Facade::class]);
});

it('documents every public method of the parser on the facade', function () {
    // The @method tags are the whole point of the facade for an IDE: if a
    // public method is added to the parser and not mirrored here, typing
    // "SwissBankCsvParser::" silently stops offering it.
    $documented = [];
    preg_match_all(
        '/@method\s+static\s+\S+\s+(\w+)\(/',
        (string) (new ReflectionClass(Facade::class))->getDocComment(),
        $matches,
    );
    $documented = $matches[1];

    $public = array_values(array_diff(
        array_map(
            fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(SwissBankCsvParser::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        ),
        ['__construct'],
    ));

    sort($documented);
    sort($public);

    expect($documented)->toBe($public);
});
