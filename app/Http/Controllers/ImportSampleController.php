<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportTarget;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImportSampleController extends Controller
{
    public function __invoke(string $target): StreamedResponse
    {
        $importTarget = ImportTarget::tryFrom($target);

        abort_if($importTarget === null, 404);

        $headers = $importTarget->headers();
        $sample = $importTarget->sampleRow();

        return response()->streamDownload(function () use ($headers, $sample): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, $headers);
            fputcsv($handle, $sample);
            fclose($handle);
        }, "{$target}-sample.csv", ['Content-Type' => 'text/csv']);
    }
}
