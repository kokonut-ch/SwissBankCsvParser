<?php

declare(strict_types=1);

namespace Kokonut\SwissBankCsvParser;

use Illuminate\Support\ServiceProvider;

class SwissBankCsvParserServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SwissBankCsvParser::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }
    }
}
