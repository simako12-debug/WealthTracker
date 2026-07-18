<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\InstitutionType;
use App\Livewire\Forms\InstitutionForm;
use App\Repositories\InstitutionRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageInstitutions extends Component
{
    use WithPagination;

    public InstitutionForm $form;

    public bool $showModal = false;

    public string $sortField = 'name';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, InstitutionRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setInstitution($data);
        $this->showModal = true;
    }

    public function save(InstitutionRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Institution saved.');
    }

    public function delete(string $id, InstitutionRepositoryInterface $repository): void
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

    public function render(InstitutionRepositoryInterface $repository): View
    {
        return view('livewire.manage-institutions', [
            'institutions' => $repository->paginate($this->sortField, $this->sortDirection, 15),
            'types' => InstitutionType::cases(),
        ]);
    }
}
