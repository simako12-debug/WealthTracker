<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Repositories\AccountRepositoryInterface;
use App\Repositories\LiabilityPaymentRepositoryInterface;
use App\Repositories\LiabilityRepositoryInterface;
use App\Repositories\TransactionRepositoryInterface;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DashboardSummary extends Component
{
    public function render(
        AccountRepositoryInterface $accounts,
        TransactionRepositoryInterface $transactions,
        LiabilityRepositoryInterface $liabilities,
        LiabilityPaymentRepositoryInterface $payments,
    ): View {
        return view('livewire.dashboard-summary', [
            'accountCount' => $accounts->count(),
            'recentTransactions' => $transactions->recent(5),
            'activeLiabilities' => $liabilities->active(),
            'lastPaymentDates' => $payments->latestDateByLiability(),
        ]);
    }
}
