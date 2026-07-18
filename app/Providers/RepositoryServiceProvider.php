<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\CurrencyPairRepository;
use App\Repositories\CurrencyPairRepositoryInterface;
use App\Repositories\CurrencyRepository;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        CurrencyRepositoryInterface::class => CurrencyRepository::class,
        CurrencyPairRepositoryInterface::class => CurrencyPairRepository::class,
    ];
}
