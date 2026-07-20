<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Currency Pairs</h1>
            <x-primary-button wire:click="create">New currency pair</x-primary-button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-medium">Base</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Quote</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('source')">Source</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('is_active')">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($pairs as $pair)
                        <tr wire:key="pair-{{ $pair->id }}">
                            <td class="px-4 py-3 text-sm">{{ $pair->baseCurrencyCode }}</td>
                            <td class="px-4 py-3 text-sm">{{ $pair->quoteCurrencyCode }}</td>
                            <td class="px-4 py-3 text-sm">{{ $pair->source->label() }}</td>
                            <td class="px-4 py-3 text-sm">{{ $pair->isActive ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit(@js($pair->id))" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete(@js($pair->id))" wire:confirm="Delete this currency pair?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $pairs->links() }}</div>

        <x-modal name="currency-pair-modal" entangle="showModal" focusable>
            <form wire:submit="save" class="space-y-4 p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $form->id === null ? 'New currency pair' : 'Edit currency pair' }}
                </h2>

                <div>
                    <x-input-label for="baseCurrencyId" value="Base currency" />
                    <select id="baseCurrencyId" wire:model.live="form.baseCurrencyId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($currencies as $c)
                            <option value="{{ $c->id }}">{{ $c->code }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('form.baseCurrencyId')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="quoteCurrencyId" value="Quote currency" />
                    <select id="quoteCurrencyId" wire:model.live="form.quoteCurrencyId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($currencies as $c)
                            <option value="{{ $c->id }}">{{ $c->code }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('form.quoteCurrencyId')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="source" value="Source" />
                    <select id="source" wire:model="form.source" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->value }}">{{ $source->label() }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('form.source')" class="mt-2" />
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="isActive" wire:model="form.isActive" class="rounded border-gray-300 dark:border-gray-700" />
                    <x-input-label for="isActive" value="Active" />
                    <x-input-error :messages="$errors->get('form.isActive')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="note" value="Note" />
                    <textarea id="note" wire:model="form.note" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900"></textarea>
                    <x-input-error :messages="$errors->get('form.note')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" wire:click="cancel">Cancel</x-secondary-button>
                    <x-primary-button>Save</x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</div>
