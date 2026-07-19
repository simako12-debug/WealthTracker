<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\LiabilityPaymentData;
use Livewire\Form;

class LiabilityPaymentForm extends Form
{
    public ?string $id = null;

    public ?string $liabilityId = null;

    public ?string $paymentDate = null;

    public ?string $totalAmount = null;

    public ?string $principalPortion = null;

    public ?string $interestPortion = null;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'liabilityId' => ['required', 'exists:liabilities,id'],
            'paymentDate' => ['required', 'date'],
            'totalAmount' => ['required', 'numeric', 'min:0'],
            'principalPortion' => ['nullable', 'numeric', 'min:0'],
            'interestPortion' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setPayment(LiabilityPaymentData $data): void
    {
        $this->id = $data->id;
        $this->liabilityId = $data->liabilityId;
        $this->paymentDate = $data->paymentDate->toDateString();
        $this->totalAmount = $data->totalAmount;
        $this->principalPortion = $data->principalPortion;
        $this->interestPortion = $data->interestPortion;
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'liability_id' => $this->liabilityId,
            'payment_date' => $this->paymentDate,
            'total_amount' => $this->totalAmount,
            'principal_portion' => $this->principalPortion,
            'interest_portion' => $this->interestPortion,
            'note' => $this->note,
        ];
    }
}
