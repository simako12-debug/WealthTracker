<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Transactions</h1>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="mb-8 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="institutionId" value="Institution" />
                        <select id="institutionId" wire:model.live="form.institutionId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">—</option>
                            @foreach ($institutions as $i)
                                <option value="{{ $i->id }}">{{ $i->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('form.institutionId')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="accountId" value="Account" />
                        <select id="accountId" wire:model.live="form.accountId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" @disabled($form->institutionId === null)>
                            <option value="">—</option>
                            @foreach ($accountOptions as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('form.accountId')" class="mt-2" />
                        @if ($selectedAccount !== null)
                            <span class="mt-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $selectedAccount->currencyCode }}</span>
                        @endif
                    </div>

                    <div>
                        <x-input-label for="type" value="Type" />
                        <select id="type" wire:model="form.type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">—</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}">{{ $type->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('form.type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="amount" value="Amount" />
                        <x-text-input id="amount" type="text" inputmode="decimal" wire:model.live="form.amount" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('form.amount')" class="mt-2" />
                        @if ($preview !== null)
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">≈ {{ number_format((float) $preview->amount, 2) }} CZK</p>
                        @elseif ($selectedAccount !== null && filled($form->amount))
                            <span class="mt-1 text-sm text-red-500">rate unavailable</span>
                        @endif
                    </div>

                    <div>
                        <x-input-label for="transactionDate" value="Date" />
                        <input id="transactionDate" type="date" wire:model.live="form.transactionDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                        <x-input-error :messages="$errors->get('form.transactionDate')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="counterparty" value="Counterparty" />
                        <x-text-input id="counterparty" wire:model="form.counterparty" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('form.counterparty')" class="mt-2" />
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

        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Recent transactions</h2>
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Account</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Type</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Amount</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Counterparty</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($recent as $t)
                        <tr wire:key="transaction-{{ $t->id }}">
                            <td class="px-4 py-3 text-sm">{{ $t->transactionDate->toDateString() }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ $t->accountName }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $t->institutionName }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $t->type->label() }}</td>
                            <td class="px-4 py-3 text-sm">{{ number_format((float) $t->amount, 2) }} {{ $t->accountCurrencyCode }}</td>
                            <td class="px-4 py-3 text-sm">{{ $t->counterparty }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit(@js($t->id))" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete(@js($t->id))" wire:confirm="Delete this transaction?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
