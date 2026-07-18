<?php

declare(strict_types=1);

namespace App\Services\Fx;

use App\Data\CurrencyPairData;
use App\Data\FxRateData;
use App\Data\FxSyncResult;
use App\Repositories\CurrencyPairRepositoryInterface;
use App\Repositories\FxRateRepositoryInterface;
use Illuminate\Support\Collection;

final readonly class FxSyncService
{
    /**
     * @param  array<string, RateProviderInterface>  $providers  keyed by FxSource value
     */
    public function __construct(
        private CurrencyPairRepositoryInterface $pairs,
        private FxRateRepositoryInterface $rates,
        private array $providers,
    ) {}

    public function sync(): FxSyncResult
    {
        $active = $this->pairs->activePairs();
        $synced = 0;
        $messages = [];

        $grouped = $active->groupBy(fn (CurrencyPairData $pair): string => $pair->source->value);

        foreach ($grouped as $sourceValue => $pairsForSource) {
            $provider = $this->providers[$sourceValue] ?? null;

            if ($provider === null) {
                $messages[] = "No provider registered for source '{$sourceValue}'.";

                continue;
            }

            /** @var Collection<int, FxRateData> $fetched */
            $fetched = $provider->fetchRates($pairsForSource);

            foreach ($fetched as $rateData) {
                $this->rates->upsert($rateData);
                $synced++;
            }
        }

        return new FxSyncResult(
            synced: $synced,
            skipped: $active->count() - $synced,
            messages: $messages,
        );
    }
}
