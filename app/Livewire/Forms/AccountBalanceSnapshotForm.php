<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Data\AccountBalanceSnapshotData;
use Livewire\Form;

class AccountBalanceSnapshotForm extends Form
{
    public ?string $id = null;

    public ?string $accountId = null;

    public ?string $balance = null;

    public ?string $snapshotDate = null;

    public ?string $note = null;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'accountId' => ['required', 'exists:accounts,id'],
            'balance' => ['required', 'numeric'],
            'snapshotDate' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ];
    }

    public function setSnapshot(AccountBalanceSnapshotData $data): void
    {
        $this->id = $data->id;
        $this->accountId = $data->accountId;
        $this->balance = $data->balance;
        $this->snapshotDate = $data->snapshotDate->toDateString();
        $this->note = $data->note;
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'account_id' => $this->accountId,
            'balance' => $this->balance,
            'snapshot_date' => $this->snapshotDate,
            'note' => $this->note,
        ];
    }
}
