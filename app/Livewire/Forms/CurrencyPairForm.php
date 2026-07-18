<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\CurrencyPairData;
use App\Enums\FxSource;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CurrencyPairForm extends Form
{
    public ?string $id = null;

    public ?string $baseCurrencyId = null;

    public ?string $quoteCurrencyId = null;

    public ?string $source = null;

    public bool $isActive = true;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'baseCurrencyId' => [
                'required', 'exists:currencies,id', 'different:quoteCurrencyId',
                Rule::unique('currency_pairs', 'base_currency_id')
                    ->where('quote_currency_id', $this->quoteCurrencyId)
                    ->ignore($this->id),
            ],
            'quoteCurrencyId' => ['required', 'exists:currencies,id'],
            'source' => ['required', Rule::enum(FxSource::class)],
            'isActive' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setPair(CurrencyPairData $data): void
    {
        $this->id = $data->id;
        $this->baseCurrencyId = $data->baseCurrencyId;
        $this->quoteCurrencyId = $data->quoteCurrencyId;
        $this->source = $data->source->value;
        $this->isActive = $data->isActive;
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'base_currency_id' => $this->baseCurrencyId,
            'quote_currency_id' => $this->quoteCurrencyId,
            'source' => $this->source,
            'is_active' => $this->isActive,
            'note' => $this->note,
        ];
    }
}
