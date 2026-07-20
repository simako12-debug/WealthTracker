<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ImportTarget;
use App\Services\CsvImportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ImportData extends Component
{
    use WithFileUploads;

    public ?string $target = null;

    public ?TemporaryUploadedFile $csv = null;

    public bool $skipDuplicates = true;

    public function updatedTarget(): void
    {
        $this->reset('csv');
    }

    public function import(CsvImportService $service): void
    {
        $importTarget = $this->resolveTarget();

        if ($importTarget === null || $this->csv === null) {
            return;
        }

        $result = $service->import($importTarget, $this->csv->get(), $this->skipDuplicates);

        $this->reset('csv');

        session()->flash('status', "Imported {$result->imported}, skipped {$result->skipped}, failed {$result->failed}.");
    }

    public function render(CsvImportService $service): View
    {
        $importTarget = $this->resolveTarget();

        $preview = ($importTarget !== null && $this->csv !== null)
            ? $service->preview($importTarget, $this->csv->get(), $this->skipDuplicates)
            : null;

        return view('livewire.import-data', [
            'targets' => ImportTarget::cases(),
            'preview' => $preview,
        ]);
    }

    private function resolveTarget(): ?ImportTarget
    {
        return $this->target === null ? null : ImportTarget::tryFrom($this->target);
    }
}
