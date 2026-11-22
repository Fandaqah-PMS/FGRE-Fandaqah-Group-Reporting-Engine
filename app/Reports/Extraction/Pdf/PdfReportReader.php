<?php

namespace App\Reports\Extraction\Pdf;

/**
 * Reads one report layout (e.g. Opera / Fandaqah "Manager - Flash") from PDF text into the
 * canonical ExtractionSchema shape. No guessing: a value that is not found stays null, and
 * the validator decides whether the report can be stored.
 */
interface PdfReportReader
{
    /** Short key stored on the extraction run, e.g. "manager_flash". */
    public function key(): string;

    public function matches(PdfText $text): bool;

    /** @return array<string, mixed> data in the ExtractionSchema shape */
    public function read(PdfText $text): array;
}
