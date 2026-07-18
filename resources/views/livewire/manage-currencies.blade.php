<div class="py-8">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Currencies</h1>
            <x-primary-button wire:click="create">New currency</x-primary-button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('code')">Code</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('name')">Name</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($currencies as $currency)
                        <tr wire:key="currency-{{ $currency->id }}">
                            <td class="px-4 py-3 text-sm font-mono">{{ $currency->code }}</td>
                            <td class="px-4 py-3 text-sm">{{ $currency->name }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit(@js($currency->id))" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete(@js($currency->id))" wire:confirm="Delete this currency?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $currencies->links() }}</div>

        <x-modal name="currency-modal" :show="$showModal" focusable>
            <form wire:submit="save" class="space-y-4 p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $form->id === null ? 'New currency' : 'Edit currency' }}
                </h2>

                <div>
                    <x-input-label for="code" value="Code (ISO 4217)" />
                    <x-text-input id="code" wire:model="form.code" class="mt-1 block w-full uppercase" maxlength="10" />
                    <x-input-error :messages="$errors->get('form.code')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" wire:model="form.name" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('form.name')" class="mt-2" />
                </div>

                <div class="flex justify-end gap-3">
                    <x-secondary-button type="button" wire:click="cancel">Cancel</x-secondary-button>
                    <x-primary-button>Save</x-primary-button>
                </div>
            </form>
        </x-modal>
    </div>
</div>
