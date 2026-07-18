<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\LiabilityData;
use Livewire\Form;

class LiabilityForm extends Form
{
    public ?string $id = null;

    public ?string $institutionId = null;

    public ?string $currencyId = null;

    public string $name = '';

    public ?string $principalAmount = null;

    public ?string $interestRate = null;

    public ?string $monthlyPayment = null;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public bool $isActive = true;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'institutionId' => ['required', 'exists:institutions,id'],
            'currencyId' => ['required', 'exists:currencies,id'],
            'name' => ['required', 'string', 'max:255'],
            'principalAmount' => ['required', 'numeric', 'min:0'],
            'interestRate' => ['required', 'numeric', 'min:0'],
            'monthlyPayment' => ['nullable', 'numeric', 'min:0'],
            'startDate' => ['required', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'isActive' => ['boolean'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setLiability(LiabilityData $data): void
    {
        $this->id = $data->id;
        $this->institutionId = $data->institutionId;
        $this->currencyId = $data->currencyId;
        $this->name = $data->name;
        $this->principalAmount = $data->principalAmount;
        $this->interestRate = $data->interestRate;
        $this->monthlyPayment = $data->monthlyPayment;
        $this->startDate = $data->startDate->toDateString();
        $this->endDate = $data->endDate?->toDateString();
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
            'principal_amount' => $this->principalAmount,
            'interest_rate' => $this->interestRate,
            'monthly_payment' => $this->monthlyPayment,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'is_active' => $this->isActive,
            'note' => $this->note,
        ];
    }
}
