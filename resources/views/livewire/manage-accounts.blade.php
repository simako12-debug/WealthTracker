<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Accounts</h1>
            <x-primary-button wire:click="create">New account</x-primary-button>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead>
                    <tr>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('name')">Name</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Institution</th>
                        <th class="px-4 py-3 text-left text-sm font-medium">Currency</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('type')">Type</th>
                        <th class="cursor-pointer px-4 py-3 text-left text-sm font-medium" wire:click="sortBy('is_active')">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($accounts as $account)
                        <tr wire:key="account-{{ $account->id }}">
                            <td class="px-4 py-3 text-sm">{{ $account->name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $account->institutionName }}</td>
                            <td class="px-4 py-3 text-sm">{{ $account->currencyCode }}</td>
                            <td class="px-4 py-3 text-sm">{{ $account->type->label() }}</td>
                            <td class="px-4 py-3 text-sm">{{ $account->isActive ? 'Yes' : 'No' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                <button wire:click="edit(@js($account->id))" class="text-indigo-600 hover:underline">Edit</button>
                                <button wire:click="delete(@js($account->id))" wire:confirm="Delete this account?" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $accounts->links() }}</div>

        <x-modal name="account-modal" :show="$showModal" focusable>
            <form wire:submit="save" class="space-y-4 p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    {{ $form->id === null ? 'New account' : 'Edit account' }}
                </h2>

                <div>
                    <x-input-label for="institutionId" value="Institution" />
                    <select id="institutionId" wire:model="form.institutionId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($institutions as $i)
                            <option value="{{ $i->id }}">{{ $i->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('form.institutionId')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="currencyId" value="Currency" />
                    <select id="currencyId" wire:model="form.currencyId" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($currencies as $c)
                            <option value="{{ $c->id }}">{{ $c->code }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('form.currencyId')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="name" value="Name" />
                    <x-text-input id="name" wire:model="form.name" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('form.name')" class="mt-2" />
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
