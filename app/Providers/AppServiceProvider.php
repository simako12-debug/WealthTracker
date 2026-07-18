<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\FxSource;
use App\Repositories\CurrencyPairRepositoryInterface;
use App\Repositories\FxRateRepositoryInterface;
use App\Services\Fx\CnbRateProvider;
use App\Services\Fx\FrankfurterRateProvider;
use App\Services\Fx\FxSyncService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FxSyncService::class, function ($app): FxSyncService {
            return new FxSyncService(
                $app->make(CurrencyPairRepositoryInterface::class),
                $app->make(FxRateRepositoryInterface::class),
                [
                    FxSource::CNB->value => $app->make(CnbRateProvider::class),
                    FxSource::FRANKFURTER->value => $app->make(FrankfurterRateProvider::class),
                ],
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
