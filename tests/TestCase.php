<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Tests;

use Kokonut\SwissBankCsvParser\Facades\SwissBankCsvParser;
use Kokonut\SwissBankCsvParser\SwissBankCsvParserServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SwissBankCsvParserServiceProvider::class,
        ];
    }

    /**
     * Testbench does not read "extra.laravel" from composer.json, so the alias
     * an application gets from package discovery has to be declared here for
     * the test suite to exercise the same thing.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'SwissBankCsvParser' => SwissBankCsvParser::class,
        ];
    }
}
