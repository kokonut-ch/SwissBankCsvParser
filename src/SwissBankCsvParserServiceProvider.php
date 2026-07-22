<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser;

use Illuminate\Support\ServiceProvider;

class SwissBankCsvParserServiceProvider extends ServiceProvider
{
    /**
     * The parser is a singleton because discovering the bank profiles scans
     * banks/ on the filesystem. An application resolving it per row of an
     * upload should pay for that once.
     *
     * The "SwissBankCsvParser" alias that makes the facade reachable without an
     * import is declared in composer.json under "extra.laravel", where Laravel's
     * package discovery picks it up.
     */
    public function register(): void
    {
        $this->app->singleton(SwissBankCsvParser::class);
    }
}
