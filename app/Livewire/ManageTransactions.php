<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\AccountData;
use App\Data\ConversionResult;
use App\Enums\TransactionType;
use App\Livewire\Forms\TransactionForm;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\InstitutionRepositoryInterface;
use App\Repositories\TransactionRepositoryInterface;
use App\Services\CurrencyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageTransactions extends Component
{
    public TransactionForm $form;

    public function mount(): void
    {
        $this->form->transactionDate = CarbonImmutable::now()->toDateString();
    }

    public function updatedFormInstitutionId(): void
    {
        $this->form->accountId = null;
    }

    public function edit(string $id, TransactionRepositoryInterface $transactions, AccountRepositoryInterface $accounts): void
    {
        $data = $transactions->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setTransaction($data);
        $account = $accounts->find($data->accountId);
        $this->form->institutionId = $account?->institutionId;
    }

    public function save(TransactionRepositoryInterface $transactions): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $transactions->create($this->form->toAttributes());
        } else {
            $transactions->update($this->form->id, $this->form->toAttributes());
        }

        // Keep institution/account/date for rapid repeated entry; clear the rest.
        $this->form->id = null;
        $this->form->type = null;
        $this->form->amount = null;
        $this->form->note = null;
        $this->form->counterparty = null;

        session()->flash('status', 'Transaction saved.');
    }

    public function delete(string $id, TransactionRepositoryInterface $transactions): void
    {
        $transactions->delete($id);
    }

    private function preview(): ?ConversionResult
    {
        if ($this->form->accountId === null || $this->form->amount === null || $this->form->transactionDate === null) {
            return null;
        }

        if (is_numeric($this->form->amount) === false) {
            return null;
        }

        $account = app(AccountRepositoryInterface::class)->find($this->form->accountId);

        if ($account === null) {
            return null;
        }

        return app(CurrencyConverter::class)->toCzkByCode(
            (string) $this->form->amount,
            $account->currencyCode,
            CarbonImmutable::parse($this->form->transactionDate),
        );
    }

    public function render(
        TransactionRepositoryInterface $transactions,
        AccountRepositoryInterface $accounts,
        InstitutionRepositoryInterface $institutions,
    ): View {
        /** @var Collection<int, AccountData> $accountOptions */
        $accountOptions = $this->form->institutionId === null
            ? new Collection
            : $accounts->forInstitution($this->form->institutionId);

        $selectedAccount = $this->form->accountId === null ? null : $accounts->find($this->form->accountId);

        return view('livewire.manage-transactions', [
            'institutions' => $institutions->all(),
            'accountOptions' => $accountOptions,
            'selectedAccount' => $selectedAccount,
            'preview' => $this->preview(),
            'types' => TransactionType::cases(),
            'recent' => $transactions->recent(15),
        ]);
    }
}
