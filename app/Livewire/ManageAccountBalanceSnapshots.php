<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Forms\AccountBalanceSnapshotForm;
use App\Repositories\AccountBalanceSnapshotRepositoryInterface;
use App\Repositories\AccountRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageAccountBalanceSnapshots extends Component
{
    public AccountBalanceSnapshotForm $form;

    public function mount(): void
    {
        $this->form->snapshotDate = CarbonImmutable::now()->toDateString();
    }

    public function edit(string $id, AccountBalanceSnapshotRepositoryInterface $snapshots): void
    {
        $data = $snapshots->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setSnapshot($data);
    }

    public function save(AccountBalanceSnapshotRepositoryInterface $snapshots): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $snapshots->upsert($this->form->toAttributes());
        } else {
            $snapshots->update($this->form->id, $this->form->toAttributes());
        }

        // Keep account/date for rapid repeated entry; clear the rest.
        $this->form->id = null;
        $this->form->balance = null;
        $this->form->note = null;

        session()->flash('status', 'Snapshot saved.');
    }

    public function delete(string $id, AccountBalanceSnapshotRepositoryInterface $snapshots): void
    {
        $snapshots->delete($id);
    }

    public function render(
        AccountRepositoryInterface $accounts,
        AccountBalanceSnapshotRepositoryInterface $snapshots,
    ): View {
        $selectedAccount = $this->form->accountId === null ? null : $accounts->find($this->form->accountId);

        return view('livewire.manage-account-balance-snapshots', [
            'accounts' => $accounts->active(),
            'selectedAccount' => $selectedAccount,
            'recent' => $snapshots->recent(15),
        ]);
    }
}
