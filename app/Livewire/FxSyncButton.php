<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\Fx\FxSyncService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class FxSyncButton extends Component
{
    public ?string $result = null;

    public function sync(FxSyncService $service): void
    {
        $outcome = $service->sync();

        $this->result = "{$outcome->synced} synced, {$outcome->skipped} skipped.";
    }

    public function render(): View
    {
        return view('livewire.fx-sync-button');
    }
}
