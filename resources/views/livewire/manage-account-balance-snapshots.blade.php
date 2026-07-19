<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Account balance snapshots</h1>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="mb-8 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="accountId" value="Account" />
                        <select id="accountId" wire:model.live="form.accountId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                            <option value="">—</option>
                            @foreach ($accounts as $a)
                                <option value="{{ $a->id }}">{{ $a->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('form.accountId')" class="mt-2" />
                        @if ($selectedAccount !== null)
                            <span class="mt-1 inline-block rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $selectedAccount->currencyCode }}</span>
                        @endif
                    </div>

                    <div>
                        <x-input-label for="balance" value="Balance" />
                        <x-text-input id="balance" type="text" inputmode="decimal" wire:model="form.balance" class="mt-1 block w-full" />
                        <x-input-error :messages="$errors->get('form.balance')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="snapshotDate" value="Date" />
                        <input id="snapshotDate" type="date" wire:model="form.snapshotDate" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900" />
                        <x-input-error :messages="$errors->get('form.snapshotDate')" class="mt-2" />
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

        <h2 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">Recent snapshots</h2>
        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Account</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Balance</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Note</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($recent as $s)
                        <tr wire:key="snapshot-{{ $s->id }}">
                            <td class="px-4 py-3 text-sm">{{ $s->snapshotDate->toDateString() }}</td>
                            <td class="px-4 py-3 text-sm">
                                {{ $s->accountName }}
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $s->institutionName }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm">{{ $s->balance }} {{ $s->currencyCode }}</td>
                            <td class="px-4 py-3 text-sm">{{ $s->note }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit(@js($s->id))" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete(@js($s->id))" wire:confirm="Delete this snapshot?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
