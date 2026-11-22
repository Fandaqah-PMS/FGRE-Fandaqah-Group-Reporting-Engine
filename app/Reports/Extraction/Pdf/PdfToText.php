<?php

namespace App\Reports\Extraction\Pdf;

use App\Reports\Extraction\ExtractionFailed;
use Illuminate\Support\Facades\Process;
use Throwable;

/**
 * Runs the free xpdf `pdftotext` tool (https://www.xpdfreader.com) on a report PDF.
 * Path in FGRE_PDFTOTEXT (config fgre.pdf.pdftotext). Nothing leaves the server.
 */
class PdfToText
{
    public function read(string $pdf): PdfText
    {
        $path = tempnam(sys_get_temp_dir(), 'fgre-pdf-');
        file_put_contents($path, $pdf);

        try {
            return new PdfText($this->run(['-table', '-enc', 'UTF-8'], $path), $this->run(['-layout', '-enc', 'UTF-8'], $path));
        } finally {
            @unlink($path);
        }
    }

    /** @param list<string> $options */
    private function run(array $options, string $path): string
    {
        $binary = (string) config('fgre.pdf.pdftotext', 'pdftotext');

        try {
            $result = Process::timeout((int) config('fgre.pdf.timeout', 60))->run([$binary, ...$options, $path, '-']);
        } catch (Throwable $e) {
            throw new ExtractionFailed("The PDF text tool could not be started ({$binary}). Install xpdf pdftotext and set FGRE_PDFTOTEXT. ".$e->getMessage(), retryable: true);
        }

        if (! $result->successful()) {
            $error = trim($result->errorOutput()) ?: 'exit code '.$result->exitCode();
            if (str_contains($error, '-table') || preg_match('/unrecognized option|unknown option|invalid option/i', $error)) {
                throw new ExtractionFailed("The installed pdftotext ({$binary}) has no -table mode: it is the Poppler version. FGRE needs the xpdf version from https://www.xpdfreader.com (set FGRE_PDFTOTEXT to its path).", retryable: true);
            }
            if (in_array($result->exitCode(), [127, 9009], true) || str_contains($error, 'not recognized') || str_contains($error, 'not found')) {
                throw new ExtractionFailed("The PDF text tool is not installed ({$binary}). Install xpdf pdftotext and set FGRE_PDFTOTEXT.", retryable: true);
            }

            throw new ExtractionFailed('The PDF could not be read (damaged or password protected): '.$error);
        }

        return $result->output();
    }
}
