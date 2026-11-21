<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\ExtractionStatus;
use App\Enums\ReportType;
use App\Models\ExtractionRun;
use App\Models\ReportDocument;
use App\Reports\Extraction\ExtractionFailed;
use App\Reports\Extraction\ReportExtractor;
use App\Reports\PropertyMatcher;
use App\Reports\ReportDataWriter;
use App\Reports\Validation\ExtractionValidator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Extract → match hotel → validate → store, for one report PDF.
 */
class ProcessReportDocument implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> seconds */
    public array $backoff = [60, 300];

    public function __construct(public int $documentId) {}

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    public function handle(
        ReportExtractor $extractor,
        PropertyMatcher $matcher,
        ExtractionValidator $validator,
        ReportDataWriter $writer,
    ): void {
        $document = ReportDocument::with('inboundMessage')->findOrFail($this->documentId);

        if ($document->status->isFinal()) {
            return;
        }

        $document->markStatus(DocumentStatus::Extracting);

        $run = ExtractionRun::create([
            'report_document_id' => $document->id,
            'model' => config('fgre.ai.model'),
            'prompt_version' => config('fgre.ai.prompt_version'),
            'status' => ExtractionStatus::Running,
        ]);
        $started = hrtime(true);

        try {
            $result = $extractor->extract($document);
        } catch (ExtractionFailed $e) {
            $run->update([
                'status' => ExtractionStatus::Failed,
                'error' => $e->getMessage(),
                'stop_reason' => $e->stopReason,
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
            'model' => $result->model,
            'output' => $output,
            'stop_reason' => $result->stopReason,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'duration_ms' => $this->elapsedMs($started),
        ]);

        $document->update([
            'report_type' => ReportType::from($output['report_type']),
            'business_date' => $output['business_date'],
        ]);

        [$property, $reason] = $matcher->match(
            $document->inboundMessage?->from_email,
            $output['property_code'] ?? null,
            $output['property_name'] ?? null,
        );

        $errors = $validator->validate($output);
        if ($property === null) {
            array_unshift($errors, $reason);
        }

        if ($errors !== []) {
            $run->update(['status' => ExtractionStatus::ValidationFailed, 'validation_errors' => $errors]);
            $document->update(['property_id' => $property?->id, 'tenant_id' => $property?->tenant_id]);
            $document->markStatus(DocumentStatus::NeedsReview, implode("\n", $errors));

            return;
        }

        $document->update(['property_id' => $property->id, 'tenant_id' => $property->tenant_id]);
        $writer->write($document, $property, $output);

        $run->update(['status' => ExtractionStatus::Succeeded]);
        $document->markStatus(DocumentStatus::Processed);
    }

    public function failed(?Throwable $e): void
    {
        ReportDocument::find($this->documentId)
            ?->markStatus(DocumentStatus::Failed, $e?->getMessage() ?? 'Processing failed.');
    }

    private function elapsedMs(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
