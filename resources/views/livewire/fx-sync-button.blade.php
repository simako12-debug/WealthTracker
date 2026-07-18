<div class="flex items-center gap-3">
    <x-primary-button wire:click="sync" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="sync">Sync FX rates</span>
        <span wire:loading wire:target="sync">Syncing…</span>
    </x-primary-button>

    @if ($result !== null)
        <span class="text-sm text-gray-600 dark:text-gray-300">{{ $result }}</span>
    @endif
</div>
