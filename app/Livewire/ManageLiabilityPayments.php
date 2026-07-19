<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\LiabilityPaymentData;
use App\Livewire\Forms\LiabilityPaymentForm;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageLiabilityPayments extends Component
{
    public LiabilityPaymentForm $form;

    public function mount(): void
    {
        $this->form->paymentDate = CarbonImmutable::now()->toDateString();
    }

    public function edit(string $id, LiabilityPaymentRepositoryInterface $payments): void
    {
        $data = $payments->find($id);

        if ($data === null) {
            return;
        }

        $this->form->setPayment($data);
    }

    public function save(LiabilityPaymentRepositoryInterface $payments): void
    {
        $this->form->validate();

        if ($this->form->id === null) {
            $payments->create($this->form->toAttributes());
        } else {
            $payments->update($this->form->id, $this->form->toAttributes());
        }

        // Keep liability/date for rapid repeated entry; clear the rest.
        $this->form->id = null;
        $this->form->totalAmount = null;
        $this->form->principalPortion = null;
        $this->form->interestPortion = null;
        $this->form->note = null;

        session()->flash('status', 'Payment saved.');
    }

    public function delete(string $id, LiabilityPaymentRepositoryInterface $payments): void
    {
        $payments->delete($id);
    }

    public function render(
        LiabilityRepositoryInterface $liabilities,
        LiabilityPaymentRepositoryInterface $payments,
    ): View {
        $selectedLiability = $this->form->liabilityId === null
            ? null
            : $liabilities->find($this->form->liabilityId);

        /** @var Collection<int, LiabilityPaymentData> $recent */
        $recent = $this->form->liabilityId === null
            ? new Collection
            : $payments->recentForLiability($this->form->liabilityId, 15);

        $paymentCount = $this->form->liabilityId === null
            ? 0
            : $payments->countForLiability($this->form->liabilityId);

        return view('livewire.manage-liability-payments', [
            'liabilities' => $liabilities->active(),
            'selectedLiability' => $selectedLiability,
            'lastPayment' => $recent->first(),
            'paymentCount' => $paymentCount,
            'recent' => $recent,
        ]);
    }
}
