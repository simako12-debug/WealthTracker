<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\CurrencyPairData;
use App\Enums\FxSource;
use App\Models\Currency;
use App\Models\CurrencyPair;
use App\Repositories\CurrencyPairRepository;
use App\Repositories\CurrencyPairRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CurrencyPairRepository::class)]
class CurrencyPairRepositoryCrudTest extends TestCase
{
    use RefreshDatabase;

    private function repository(): CurrencyPairRepositoryInterface
    {
        return $this->app->make(CurrencyPairRepositoryInterface::class);
    }

    public function test_paginate_returns_data_with_currency_codes(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $czk = Currency::factory()->create(['code' => 'CZK']);
        CurrencyPair::factory()->create(['base_currency_id' => $usd->id, 'quote_currency_id' => $czk->id]);

        $page = $this->repository()->paginate('created_at', 'asc', 15);

        $this->assertCount(1, $page->items());
        $item = $page->items()[0];
        $this->assertInstanceOf(CurrencyPairData::class, $item);
        $this->assertSame('USD', $item->baseCurrencyCode);
        $this->assertSame('CZK', $item->quoteCurrencyCode);
    }

    public function test_create_find_update_delete(): void
    {
        $usd = Currency::factory()->create(['code' => 'USD']);
        $eur = Currency::factory()->create(['code' => 'EUR']);

        $created = $this->repository()->create([
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $eur->id,
            'source' => FxSource::FRANKFURTER->value,
            'is_active' => true,
            'note' => 'test pair',
        ]);

        $this->assertInstanceOf(CurrencyPairData::class, $created);
        $this->assertSame('test pair', $created->note);
        $this->assertDatabaseHas('currency_pairs', ['base_currency_id' => $usd->id, 'quote_currency_id' => $eur->id]);

        $found = $this->repository()->find($created->id);
        $this->assertNotNull($found);
        $this->assertSame($created->id, $found->id);

        $updated = $this->repository()->update($created->id, [
            'base_currency_id' => $usd->id,
            'quote_currency_id' => $eur->id,
            'source' => FxSource::CNB->value,
            'is_active' => false,
            'note' => 'updated',
        ]);
        $this->assertSame(FxSource::CNB, $updated->source);
        $this->assertFalse($updated->isActive);

        $this->repository()->delete($created->id);
        $this->assertNull($this->repository()->find($created->id));
    }
}
