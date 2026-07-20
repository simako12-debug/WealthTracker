<div class="space-y-6">
    <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
        <h3 class="mb-2 text-lg font-semibold text-gray-800 dark:text-gray-200">Accounts</h3>
        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $accountCount }}</p>
    </div>

    <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Recent transactions</h3>
        @if ($recentTransactions->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No transactions yet</p>
        @else
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-medium">Date</th>
                        <th class="px-4 py-2 text-left text-sm font-medium">Account</th>
                        <th class="px-4 py-2 text-left text-sm font-medium">Type</th>
                        <th class="px-4 py-2 text-left text-sm font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($recentTransactions as $t)
                        <tr wire:key="dash-tx-{{ $t->id }}">
                            <td class="px-4 py-2 text-sm">{{ $t->transactionDate->toDateString() }}</td>
                            <td class="px-4 py-2 text-sm">{{ $t->accountName }}</td>
                            <td class="px-4 py-2 text-sm">{{ $t->type->label() }}</td>
                            <td class="px-4 py-2 text-sm">{{ number_format((float) $t->amount, 2) }} {{ $t->accountCurrencyCode }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
        <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Active liabilities</h3>
        @if ($activeLiabilities->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No active liabilities</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($activeLiabilities as $l)
                    <li wire:key="dash-liab-{{ $l->id }}" class="flex items-center justify-between py-2 text-sm">
                        <span>{{ $l->name }}</span>
                        <span class="text-gray-500 dark:text-gray-400">Last payment: {{ $lastPaymentDates->get($l->id) ?? '—' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
