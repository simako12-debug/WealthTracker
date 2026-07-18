<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\CurrencyData;
use Illuminate\Validation\Rule;
use Livewire\Form;

class CurrencyForm extends Form
{
    public ?string $id = null;

    public string $code = '';

    public string $name = '';

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:10',
                Rule::unique('currencies', 'code')->ignore($this->id),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function setCurrency(CurrencyData $data): void
    {
        $this->id = $data->id;
        $this->code = $data->code;
        $this->name = $data->name;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'code' => strtoupper($this->code),
            'name' => $this->name,
        ];
    }
}
