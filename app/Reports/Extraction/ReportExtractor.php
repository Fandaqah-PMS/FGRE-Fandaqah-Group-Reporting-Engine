<?php

namespace App\Reports\Extraction;

use App\Models\ReportDocument;

interface ReportExtractor
{
    /**
     * Read the document and return its data in the ExtractionSchema shape.
     *
     * @throws ExtractionFailed when no usable output could be produced
     */
    public function extract(ReportDocument $document): ExtractionResult;
}
