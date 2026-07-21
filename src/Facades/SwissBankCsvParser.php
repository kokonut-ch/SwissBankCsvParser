<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Kokonut\SwissBankCsvParser\Dto\ParsedFile parse(string $contents)
 * @method static \Kokonut\SwissBankCsvParser\Dto\ParsedFile parseFile(string $path)
 * @method static \Kokonut\SwissBankCsvParser\Detection\DetectionReport detect(string $contents)
 * @method static bool supports(string $contents)
 * @method static \Kokonut\SwissBankCsvParser\Detection\ProfileRegistry profiles()
 *
 * @see \Kokonut\SwissBankCsvParser\SwissBankCsvParser
 */
class SwissBankCsvParser extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Kokonut\SwissBankCsvParser\SwissBankCsvParser::class;
    }
}
