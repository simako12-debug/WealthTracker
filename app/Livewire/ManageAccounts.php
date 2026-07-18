<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\AccountType;
use App\Livewire\Forms\AccountForm;
use App\Repositories\AccountRepositoryInterface;
use App\Repositories\CurrencyRepositoryInterface;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageAccounts extends Component
{
    use WithPagination;

    public AccountForm $form;

    public bool $showModal = false;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, AccountRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setAccount($data);
        $this->showModal = true;
    }

    public function save(AccountRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Account saved.');
    }

    public function delete(string $id, AccountRepositoryInterface $repository): void
    {
        $repository->delete($id);
    }

    public function cancel(): void
    {
        $this->form->reset();
        $this->showModal = false;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function render(
        AccountRepositoryInterface $accounts,
        InstitutionRepositoryInterface $institutions,
        CurrencyRepositoryInterface $currencies,
    ): View {
        return view('livewire.manage-accounts', [
            'accounts' => $accounts->paginate($this->sortField, $this->sortDirection, 15),
            'institutions' => $institutions->all(),
            'currencies' => $currencies->all(),
            'types' => AccountType::cases(),
        ]);
    }
}
