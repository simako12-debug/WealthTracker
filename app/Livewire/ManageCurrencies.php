<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\Forms\CurrencyForm;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageCurrencies extends Component
{
    use WithPagination;

    public CurrencyForm $form;

    public bool $showModal = false;

    public string $sortField = 'code';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, CurrencyRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setCurrency($data);
        $this->showModal = true;
    }

    public function save(CurrencyRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Currency saved.');
    }

    public function delete(string $id, CurrencyRepositoryInterface $repository): void
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

    public function render(CurrencyRepositoryInterface $repository): View
    {
        return view('livewire.manage-currencies', [
            'currencies' => $repository->paginate($this->sortField, $this->sortDirection, 15),
        ]);
    }
}
