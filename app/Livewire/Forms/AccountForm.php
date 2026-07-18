<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\AccountData;
use App\Enums\AccountType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class AccountForm extends Form
{
    public ?string $id = null;

    public ?string $institutionId = null;

    public ?string $currencyId = null;

    public string $name = '';

    public ?string $type = null;

    public bool $isActive = true;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'institutionId' => ['required', 'exists:institutions,id'],
            'currencyId' => ['required', 'exists:currencies,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'isActive' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setAccount(AccountData $data): void
    {
        $this->id = $data->id;
        $this->institutionId = $data->institutionId;
        $this->currencyId = $data->currencyId;
        $this->name = $data->name;
        $this->type = $data->type->value;
        $this->isActive = $data->isActive;
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'institution_id' => $this->institutionId,
            'currency_id' => $this->currencyId,
            'name' => $this->name,
            'type' => $this->type,
            'is_active' => $this->isActive,
            'note' => $this->note,
        ];
    }
}
