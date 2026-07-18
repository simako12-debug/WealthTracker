<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Forms\LiabilityForm;
use App\Repositories\CurrencyRepositoryInterface;
use App\Repositories\InstitutionRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageLiabilities extends Component
{
    use WithPagination;

    public LiabilityForm $form;

    public bool $showModal = false;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, LiabilityRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setLiability($data);
        $this->showModal = true;
    }

    public function save(LiabilityRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Liability saved.');
    }

    public function delete(string $id, LiabilityRepositoryInterface $repository): void
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
        LiabilityRepositoryInterface $liabilities,
        InstitutionRepositoryInterface $institutions,
        CurrencyRepositoryInterface $currencies,
    ): View {
        return view('livewire.manage-liabilities', [
            'liabilities' => $liabilities->paginate($this->sortField, $this->sortDirection, 15),
            'institutions' => $institutions->all(),
            'currencies' => $currencies->all(),
        ]);
    }
}
