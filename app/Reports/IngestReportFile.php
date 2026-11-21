<?php

namespace App\Reports;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessReportDocument;
use App\Models\InboundMessage;
use App\Models\ReportDocument;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Stores one report file (PDF, or structured JSON from a PMS) and queues it.
 * Used by every inbound source: email, MCP / API pulls, manual upload.
 */
class IngestReportFile
{
    /**
     * @return ReportDocument|null null when this exact file was already ingested
     */
    public function __invoke(string $contents, ?string $filename, ?InboundMessage $message = null): ?ReportDocument
    {
        $mime = $this->detectMime($contents);
        if ($mime === null) {
            throw new InvalidArgumentException("File [{$filename}] is not a PDF or JSON report.");
        }

        $max = (int) config('fgre.inbox.max_attachment_bytes');
        if (strlen($contents) > $max) {
            throw new InvalidArgumentException("File [{$filename}] is larger than {$max} bytes.");
        }

        $sha256 = hash('sha256', $contents);
        if (ReportDocument::withoutGlobalScopes()->where('sha256', $sha256)->exists()) {
            return null;
        }

        $disk = config('fgre.storage.disk');
        $extension = $mime === 'application/pdf' ? 'pdf' : 'json';
        $path = sprintf('%s/%s/%s.%s', config('fgre.storage.path'), now()->format('Y/m'), $sha256, $extension);
        Storage::disk($disk)->put($path, $contents);

        $document = ReportDocument::withoutGlobalScopes()->create([
            'inbound_message_id' => $message?->id,
            'original_filename' => $filename,
            'storage_disk' => $disk,
            'storage_path' => $path,
            'mime_type' => $mime,
            'size_bytes' => strlen($contents),
            'sha256' => $sha256,
            'status' => DocumentStatus::Received,
        ]);

        ProcessReportDocument::dispatch($document->id);

        return $document;
    }

    private function detectMime(string $contents): ?string
    {
        if (str_starts_with($contents, '%PDF-')) {
            return 'application/pdf';
        }

        $trimmed = ltrim($contents, "\xEF\xBB\xBF \t\r\n");
        if (str_starts_with($trimmed, '{') && is_array(json_decode($trimmed, true))) {
            return 'application/json';
        }

        return null;
    }
}
