<?php

namespace App\Providers;

use App\Contracts\MarketDataProvider;
use App\Services\PublicCryptoMarketProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MarketDataProvider::class, PublicCryptoMarketProvider::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
