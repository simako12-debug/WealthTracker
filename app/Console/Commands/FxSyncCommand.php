<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Fx\FxSyncService;
use Illuminate\Console\Command;

class FxSyncCommand extends Command
{
    /** @var string */
    protected $signature = 'fx:sync';

    /** @var string */
    protected $description = 'Fetch the latest FX rates for active currency pairs and store them.';

    public function handle(FxSyncService $service): int
    {
        $result = $service->sync();

        $this->info("FX sync complete: {$result->synced} synced, {$result->skipped} skipped.");

        foreach ($result->messages as $message) {
            $this->warn($message);
        }

        return self::SUCCESS;
    }
}
