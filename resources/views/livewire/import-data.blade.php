<div class="py-8">
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
        <div class="mb-4 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Import</h1>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded bg-green-100 px-4 py-2 text-green-800">{{ session('status') }}</div>
        @endif

        <div class="mb-8 overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
            <div class="space-y-4">
                <div>
                    <x-input-label for="target" value="Target" />
                    <select id="target" wire:model.live="target" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                        <option value="">—</option>
                        @foreach ($targets as $t)
                            <option value="{{ $t->value }}">{{ $t->label() }}</option>
                        @endforeach
                    </select>
                </div>

                @if ($target !== null)
                    <div>
                        <a href="{{ route('import.sample', $target) }}" class="text-sm text-indigo-600 hover:underline">Download sample CSV</a>
                    </div>

                    <div>
                        <x-input-label for="csv" value="CSV file" />
                        <input id="csv" type="file" wire:model="csv" accept=".csv" class="mt-1 block w-full text-sm text-gray-600 dark:text-gray-300" />
                        <div wire:loading wire:target="csv" class="mt-1 text-sm text-gray-500 dark:text-gray-400">Uploading…</div>
                        <x-input-error :messages="$errors->get('csv')" class="mt-2" />
                    </div>

                    <div class="flex items-center">
                        <input id="skipDuplicates" type="checkbox" wire:model.live="skipDuplicates" class="rounded border-gray-300 dark:border-gray-700" />
                        <label for="skipDuplicates" class="ml-2 text-sm text-gray-600 dark:text-gray-300">Skip duplicate rows</label>
                    </div>
                @endif

                @if ($preview !== null)
                    <div>
                        <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
                            Valid: {{ $preview->validCount }} · Duplicates: {{ $preview->duplicateCount }} · Errors: {{ $preview->errorCount }}
                        </p>

                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-3 text-left text-sm font-medium">Line</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium">Row</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
                                        <th class="px-4 py-3 text-left text-sm font-medium">Message</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach ($preview->rows as $row)
                                        <tr wire:key="row-{{ $row->line }}">
                                            <td class="px-4 py-3 text-sm">{{ $row->line }}</td>
                                            <td class="px-4 py-3 text-sm">{{ implode(' · ', $row->raw) }}</td>
                                            @php
                                                $statusClass = match ($row->status) {
                                                    \App\Data\Import\ImportRowResult::VALID => 'text-green-600',
                                                    \App\Data\Import\ImportRowResult::DUPLICATE => 'text-gray-500',
                                                    default => 'text-red-600',
                                                };
                                            @endphp
                                            <td class="px-4 py-3 text-sm {{ $statusClass }}">
                                                {{ ucfirst($row->status) }}
                                            </td>
                                            <td class="px-4 py-3 text-sm">{{ $row->error }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="flex justify-end">
                    <x-primary-button wire:click="import" :disabled="$preview === null || $preview->validCount === 0">Import {{ $preview?->validCount ?? 0 }} rows</x-primary-button>
                </div>
            </div>
        </div>
    </div>
</div>
