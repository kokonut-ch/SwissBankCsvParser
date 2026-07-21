<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser\Tests;

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
}
