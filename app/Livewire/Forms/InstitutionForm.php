<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\InstitutionData;
use App\Enums\InstitutionType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class InstitutionForm extends Form
{
    public ?string $id = null;

    public string $name = '';

    public ?string $type = null;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InstitutionType::class)],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setInstitution(InstitutionData $data): void
    {
        $this->id = $data->id;
        $this->name = $data->name;
        $this->type = $data->type->value;
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'note' => $this->note,
        ];
    }
}
