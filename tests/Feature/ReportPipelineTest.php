<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\InboundSource;
use App\Enums\ReportType;
use App\Models\DailyPropertyStat;
use App\Models\InboundMessage;
use App\Models\PmsProvider;
use App\Models\Property;
use App\Models\ReportDocument;
use App\Models\RevenueLine;
use App\Models\TrialBalanceLine;
use App\Reports\Extraction\ExtractionFailed;
use App\Reports\Extraction\ReportExtractor;
use App\Reports\IngestReportFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Fakes\FakeReportExtractor;
use Tests\TestCase;

class ReportPipelineTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.7\n% sample report\n";

    private const SENDER = 'reports@fandaqah-pms.example';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function property(array $attributes = []): Property
    {
        $provider = PmsProvider::firstOrCreate(['code' => 'fandaqah'], ['name' => 'Fandaqah PMS']);

        return Property::create(array_merge([
            'tenant_id' => (string) Str::uuid(),
            'pms_provider_id' => $provider->id,
            'name' => 'Fandaqah Riyadh',
            'code' => 'RUH01',
            'allowed_sender_emails' => [self::SENDER],
        ], $attributes));
    }

    private function ingest(array|ExtractionFailed $extractorResponse, string $from = self::SENDER, string $pdf = self::PDF): ?ReportDocument
    {
        $this->app->instance(ReportExtractor::class, new FakeReportExtractor($extractorResponse));

        $message = InboundMessage::create([
            'source' => InboundSource::Email,
            'external_id' => (string) Str::uuid(),
            'from_email' => $from,
        ]);

        $document = app(IngestReportFile::class)($pdf, 'report.pdf', $message);

        return $document?->fresh();
    }

    public function test_manager_flash_is_extracted_validated_and_stored(): void
    {
        $property = $this->property();

        $document = $this->ingest(FakeReportExtractor::flash());

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame(ReportType::ManagerFlash, $document->report_type);
        $this->assertSame($property->id, $document->property_id);
        $this->assertSame($property->tenant_id, $document->tenant_id);

        $day = DailyPropertyStat::where('period', 'day')->sole();
        $this->assertSame(80, $day->rooms_sold);
        $this->assertSame('500.00', $day->adr);
        $this->assertSame(2, DailyPropertyStat::count());   // day + mtd; ytd not printed
        $this->assertSame(4, RevenueLine::count());
    }

    public function test_balanced_trial_balance_is_stored(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::trialBalance());

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame(2, TrialBalanceLine::count());
    }

    public function test_unbalanced_trial_balance_goes_to_review(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::trialBalance(credit: 1490.0));

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertStringContainsString('does not equal total credit', $document->status_reason);
        $this->assertSame(0, TrialBalanceLine::count());
    }

    public function test_misread_occupancy_goes_to_review(): void
    {
        $this->property();
        $flash = FakeReportExtractor::flash();
        $flash['manager_flash']['metrics'][2]['day'] = 60.0;   // printed 80 → misread as 60

        $document = $this->ingest($flash);

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertStringContainsString('occupancy', $document->status_reason);
    }

    public function test_report_naming_a_different_hotel_than_the_sender_goes_to_review(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::flash([
            'property_name' => 'Some Other Hotel', 'property_code' => 'XYZ',
        ]));

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertNull($document->property_id);
    }

    public function test_shared_sender_is_resolved_by_printed_property_code(): void
    {
        $tenant = (string) Str::uuid();
        $this->property(['tenant_id' => $tenant, 'name' => 'Fandaqah Jeddah', 'code' => 'JED01']);
        $riyadh = $this->property(['tenant_id' => $tenant]);

        $document = $this->ingest(FakeReportExtractor::flash());

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame($riyadh->id, $document->property_id);
    }

    public function test_newer_report_for_same_day_replaces_older_data(): void
    {
        $this->property();
        $this->ingest(FakeReportExtractor::flash());

        $corrected = FakeReportExtractor::flash();
        $corrected['manager_flash']['revenue_departments'] = [
            ['department' => 'Rooms', 'category' => 'rooms', 'day' => 40000.0, 'mtd' => null, 'ytd' => null],
        ];
        $this->ingest($corrected, pdf: self::PDF.'% corrected');

        $this->assertSame(1, RevenueLine::count());
        $this->assertSame(2, DailyPropertyStat::count());
    }

    public function test_same_file_is_never_ingested_twice(): void
    {
        $this->property();

        $this->assertNotNull($this->ingest(FakeReportExtractor::flash()));
        $this->assertNull($this->ingest(FakeReportExtractor::flash()));
        $this->assertSame(1, ReportDocument::count());
    }

    public function test_non_pdf_files_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->ingest(FakeReportExtractor::flash(), pdf: 'MZ not a pdf');
    }

    public function test_non_retryable_extraction_failure_marks_document_failed(): void
    {
        $this->property();

        $document = $this->ingest(new ExtractionFailed('Claude declined to read this document.', 'refusal'));

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame('refusal', $document->extractionRuns()->sole()->stop_reason);
    }
}
