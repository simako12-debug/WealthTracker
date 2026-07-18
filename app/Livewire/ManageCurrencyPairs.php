<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\FxSource;
use App\Livewire\Forms\CurrencyPairForm;
use App\Repositories\CurrencyPairRepositoryInterface;
use App\Repositories\CurrencyRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ManageCurrencyPairs extends Component
{
    use WithPagination;

    public CurrencyPairForm $form;

    public bool $showModal = false;

    public string $sortField = 'created_at';

    public string $sortDirection = 'asc';

    public function create(): void
    {
        $this->form->reset();
        $this->showModal = true;
    }

    public function edit(string $id, CurrencyPairRepositoryInterface $repository): void
    {
        $data = $repository->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setPair($data);
        $this->showModal = true;
    }

    public function save(CurrencyPairRepositoryInterface $repository): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $repository->create($this->form->toAttributes());
        } else {
            $repository->update($this->form->id, $this->form->toAttributes());
        }

        $this->showModal = false;
        $this->form->reset();
        session()->flash('status', 'Currency pair saved.');
    }

    public function delete(string $id, CurrencyPairRepositoryInterface $repository): void
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

    public function updatedFormBaseCurrencyId(): void
    {
        $this->applySourceRule();
    }

    public function updatedFormQuoteCurrencyId(): void
    {
        $this->applySourceRule();
    }

    private function applySourceRule(): void
    {
        $currencies = app(CurrencyRepositoryInterface::class)->all()->keyBy('id');
        $baseCode = $currencies->get($this->form->baseCurrencyId)?->code;
        $quoteCode = $currencies->get($this->form->quoteCurrencyId)?->code;

        if ($baseCode === null || $quoteCode === null) {
            return;
        }

        $this->form->source = ($baseCode === 'CZK' || $quoteCode === 'CZK')
            ? FxSource::CNB->value
            : FxSource::FRANKFURTER->value;
    }

    public function render(
        CurrencyPairRepositoryInterface $pairs,
        CurrencyRepositoryInterface $currencies,
    ): View {
        return view('livewire.manage-currency-pairs', [
            'pairs' => $pairs->paginate($this->sortField, $this->sortDirection, 15),
            'currencies' => $currencies->all(),
            'sources' => FxSource::cases(),
        ]);
    }
}
