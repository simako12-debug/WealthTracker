<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Liability payments</h1>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="mb-8 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="liabilityId" value="Liability" />
                        <select id="liabilityId" wire:model.live="form.liabilityId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">—</option>
                            @foreach ($liabilities as $l)
                                <option value="{{ $l->id }}">{{ $l->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('form.liabilityId')" class="mt-2" />
                    </div>

                    @if ($selectedLiability !== null)
                        <div class="rounded-md bg-gray-50 p-3 text-sm dark:bg-gray-900">
                            <div>
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $selectedLiability->currencyCode }}</span>
                            </div>
                            <div class="mt-1 text-gray-600 dark:text-gray-300">Monthly payment: {{ $selectedLiability->monthlyPayment !== null ? number_format((float) $selectedLiability->monthlyPayment, 2) : '—' }}</div>
                            <div class="text-gray-600 dark:text-gray-300">End date: {{ $selectedLiability->endDate?->toDateString() ?? '—' }}</div>
                            <div class="text-gray-600 dark:text-gray-300">Payments recorded: {{ $paymentCount }}</div>
                            @if ($lastPayment !== null)
                                <div class="text-gray-600 dark:text-gray-300">Last payment: {{ $lastPayment->paymentDate->toDateString() }} — {{ number_format((float) $lastPayment->totalAmount, 2) }} {{ $lastPayment->currencyCode }}</div>
                            @else
                                <div class="text-gray-600 dark:text-gray-300">No payments yet</div>
                            @endif
                        </div>
                    @endif

                    <div>
                        <x-input-label for="paymentDate" value="Date" />
                        <input id="paymentDate" type="date" wire:model="form.paymentDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                        <x-input-error :messages="$errors->get('form.paymentDate')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="totalAmount" value="Total amount" />
                        <x-text-input id="totalAmount" type="text" inputmode="decimal" wire:model="form.totalAmount" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('form.totalAmount')" class="mt-2" />
                        @if ($selectedLiability !== null)
                            <span class="mt-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $selectedLiability->currencyCode }}</span>
                        @endif
                    </div>

                    <div>
                        <x-input-label for="principalPortion" value="Principal portion" />
                        <x-text-input id="principalPortion" type="text" inputmode="decimal" wire:model="form.principalPortion" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('form.principalPortion')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="interestPortion" value="Interest portion" />
                        <x-text-input id="interestPortion" type="text" inputmode="decimal" wire:model="form.interestPortion" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('form.interestPortion')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="note" value="Note" />
                    <textarea id="note" wire:model="form.note" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"></textarea>
                    <x-input-error :messages="$errors->get('form.note')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <x-primary-button>{{ $form->id === null ? 'Save' : 'Update' }}</x-primary-button>
                </div>
            </form>
        </div>

        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Recent payments</h2>
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Total</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Principal</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Interest</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Note</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($recent as $p)
                        <tr wire:key="payment-{{ $p->id }}">
                            <td class="px-4 py-3 text-sm">{{ $p->paymentDate->toDateString() }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $p->totalAmount, 2) }} {{ $p->currencyCode }}</td>
                            <td class="px-4 py-3 text-sm">{{ $p->principalPortion !== null ? number_format((float) $p->principalPortion, 2) : '' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $p->interestPortion !== null ? number_format((float) $p->interestPortion, 2) : '' }}</td>
                            <td class="px-4 py-3 text-sm">{{ $p->note }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit(@js($p->id))" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete(@js($p->id))" wire:confirm="Delete this payment?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
