<?php

namespace App\Reports\Extraction;

use App\Models\ReportDocument;
use InvalidArgumentException;

/**
 * Picks the extractor for a document. Structured data the system knows
 * (Fandaqah Reports Plus JSON) needs no AI; everything else is read by the AI.
 */
class ExtractorResolver
{
    public function __construct(
        private readonly ReportExtractor $aiExtractor,
        private readonly FandaqahReportsPlusExtractor $fandaqahJson,
    ) {}

    public function for(ReportDocument $document): ReportExtractor
    {
        return match ($document->mime_type) {
            'application/pdf', 'text/plain' => $this->aiExtractor,
            'application/json' => $this->isReportsPlus($document) ? $this->fandaqahJson : $this->aiExtractor,
            default => throw new InvalidArgumentException("No extractor for [{$document->mime_type}]."),
        };
    }

    private function isReportsPlus(ReportDocument $document): bool
    {
        $data = json_decode(ltrim($document->contents(), "\xEF\xBB\xBF"), true);

        return is_array($data) && isset($data['meta']['key'], $data['sections']);
    }
}
