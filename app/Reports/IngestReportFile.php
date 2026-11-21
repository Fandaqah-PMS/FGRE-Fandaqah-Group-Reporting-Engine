<?php

namespace App\Reports;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessReportDocument;
use App\Models\InboundMessage;
use App\Models\ReportDocument;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Stores one report file and queues it for processing.
 * Used by every inbound source (email, MCP, manual upload).
 */
class IngestReportFile
{
    /**
     * @return ReportDocument|null null when this exact file was already ingested
     */
    public function __invoke(string $contents, ?string $filename, ?InboundMessage $message = null): ?ReportDocument
    {
        if (! str_starts_with($contents, '%PDF-')) {
            throw new InvalidArgumentException("File [{$filename}] is not a PDF.");
        }

        $max = (int) config('fgre.inbox.max_attachment_bytes');
        if (strlen($contents) > $max) {
            throw new InvalidArgumentException("File [{$filename}] is larger than {$max} bytes.");
        }

        $sha256 = hash('sha256', $contents);
        if (ReportDocument::where('sha256', $sha256)->exists()) {
            return null;
        }

        $disk = config('fgre.storage.disk');
        $path = sprintf('%s/%s/%s.pdf', config('fgre.storage.path'), now()->format('Y/m'), $sha256);
        Storage::disk($disk)->put($path, $contents);

        $document = ReportDocument::create([
            'inbound_message_id' => $message?->id,
            'original_filename' => $filename,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($contents),
            'sha256' => $sha256,
            'status' => DocumentStatus::Received,
        ]);

        ProcessReportDocument::dispatch($document->id);

        return $document;
    }
}
