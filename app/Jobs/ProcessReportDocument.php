<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Enums\ReportType;
use App\Models\ExtractionRun;
use App\Models\ReportDocument;
use App\Reports\DocumentHints;
use App\Reports\Extraction\ExtractionFailed;
use App\Reports\Extraction\ExtractorResolver;
use App\Reports\PropertyMatcher;
use App\Reports\ReportDataWriter;
use App\Reports\Validation\ExtractionValidator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Extract → match hotel → validate → store, for one report file.
 * Runs without a logged-in user: the group comes from the matched property.
 */
class ProcessReportDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> seconds */
    public array $backoff = [60, 300];

    public function __construct(public int $documentId) {}

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    public function handle(
        ExtractorResolver $extractors,
        PropertyMatcher $matcher,
        ExtractionValidator $validator,
        ReportDataWriter $writer,
    ): void {
        $document = ReportDocument::withoutGlobalScopes()->with('inboundMessage')->findOrFail($this->documentId);

        if ($document->status->isFinal()) {
            return;
        }

        $extractor = $extractors->for($document);
        $document->markStatus(DocumentStatus::Extracting);

        $run = ExtractionRun::create([
            'report_document_id' => $document->id,
            'extractor' => class_basename($extractor),
            'status' => ExtractionStatus::Running,
        ]);
        $started = hrtime(true);

        try {
            $result = $extractor->extract($document);
        } catch (ExtractionFailed $e) {
            $run->update([
                'status' => ExtractionStatus::Failed,
                'error' => $e->getMessage(),
                'duration_ms' => $this->elapsedMs($started),
            ]);

            if ($e->retryable) {
                throw $e;
            }

            $document->markStatus(DocumentStatus::Failed, $e->getMessage());

            return;
        }

        $output = $result->output;
        $run->update([
            'extractor' => $result->extractor,
            'output' => $output,
            'duration_ms' => $this->elapsedMs($started),
        ]);

        $document->update([
            'report_type' => ReportType::from($output['report_type']),
            'period_start' => $output['period_start'] ?? null,
            'business_date' => $output['business_date'] ?? null,
            'is_provisional' => (bool) ($output['provisional'] ?? false),
        ]);

        $hints = new DocumentHints(
            fromEmail: $document->inboundMessage?->from_email,
            subject: $document->inboundMessage?->subject,
            filename: $document->original_filename,
            propertyId: $document->inboundMessage?->property_id,
        );

        [$property, $reason] = $matcher->match($hints, $output['property_code'] ?? null, $output['property_name'] ?? null);

        // A period report's file name carries two dates; only single-date reports are cross-checked.
        $expectedDate = ($output['period_start'] ?? null) === null ? $hints->statedDate() : null;
        $errors = $validator->validate($output, $expectedDate);
        if ($property === null) {
            array_unshift($errors, $reason);
        }

        $document->update(['property_id' => $property?->id, 'group_id' => $property?->group_id]);

        if ($errors !== []) {
            $run->update(['status' => ExtractionStatus::ValidationFailed, 'validation_errors' => $errors]);
            $document->markStatus(DocumentStatus::NeedsReview, implode("\n", $errors));

            return;
        }

        $writer->write($document, $property, $output);

        $run->update(['status' => ExtractionStatus::Succeeded]);
        $document->markStatus(DocumentStatus::Processed);
    }

    public function failed(?Throwable $e): void
    {
        ReportDocument::withoutGlobalScopes()->find($this->documentId)
            ?->markStatus(DocumentStatus::Failed, $e?->getMessage() ?? 'Processing failed.');
    }

    private function elapsedMs(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
