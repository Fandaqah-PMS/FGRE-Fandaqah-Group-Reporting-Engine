<?php

namespace App\Reports\Extraction;

use App\Models\ReportDocument;
use InvalidArgumentException;

/**
 * Picks the extractor for a document. No AI anywhere: PDFs (and plain text) are read by
 * layout readers, Fandaqah Reports Plus JSON is mapped directly.
 */
class ExtractorResolver
{
    public function __construct(
        private readonly ReportExtractor $pdfExtractor,
        private readonly FandaqahReportsPlusExtractor $fandaqahJson,
    ) {}

    public function for(ReportDocument $document): ReportExtractor
    {
        return match ($document->mime_type) {
            'application/pdf', 'text/plain' => $this->pdfExtractor,
            'application/json' => $this->fandaqahJson,
            default => throw new InvalidArgumentException("No extractor for [{$document->mime_type}]."),
        };
    }
}
