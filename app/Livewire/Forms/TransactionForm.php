<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\TransactionData;
use App\Enums\TransactionType;
use Illuminate\Validation\Rule;
use Livewire\Form;

class TransactionForm extends Form
{
    public ?string $id = null;

    public ?string $institutionId = null;

    public ?string $accountId = null;

    public ?string $type = null;

    public ?string $amount = null;

    public ?string $transactionDate = null;

    public ?string $note = null;

    public ?string $counterparty = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accountId' => ['required', 'exists:accounts,id'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric'],
            'transactionDate' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'counterparty' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function setTransaction(TransactionData $data): void
    {
        $this->id = $data->id;
        $this->accountId = $data->accountId;
        $this->type = $data->type->value;
        $this->amount = $data->amount;
        $this->transactionDate = $data->transactionDate->toDateString();
        $this->note = $data->note;
        $this->counterparty = $data->counterparty;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'account_id' => $this->accountId,
            'type' => $this->type,
            'amount' => $this->amount,
            'transaction_date' => $this->transactionDate,
            'note' => $this->note,
            'counterparty' => $this->counterparty,
        ];
    }
}
