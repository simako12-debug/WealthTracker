<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\AccountRepository;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\CurrencyPairRepository;
use App\Repositories\CurrencyPairRepositoryInterface;
use App\Repositories\CurrencyRepository;
use App\Repositories\CurrencyRepositoryInterface;
use App\Repositories\FxRateRepository;
use App\Repositories\FxRateRepositoryInterface;
use App\Repositories\InstitutionRepository;
use App\Repositories\InstitutionRepositoryInterface;
use App\Repositories\LiabilityRepository;
use App\Repositories\LiabilityRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CurrencyRepositoryInterface::class => CurrencyRepository::class,
        CurrencyPairRepositoryInterface::class => CurrencyPairRepository::class,
        FxRateRepositoryInterface::class => FxRateRepository::class,
        InstitutionRepositoryInterface::class => InstitutionRepository::class,
        AccountRepositoryInterface::class => AccountRepository::class,
        LiabilityRepositoryInterface::class => LiabilityRepository::class,
    ];
}
