<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\InboundSource;
use App\Enums\ReportType;
use App\Models\DailyPropertyStat;
use App\Models\InboundMessage;
use App\Models\LedgerBalance;
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
use Tests\Concerns\CreatesGroups;
use Tests\Fakes\FakeReportExtractor;
use Tests\TestCase;

class ReportPipelineTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private const PDF = "%PDF-1.7\n% sample report\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function ingest(
        array|ExtractionFailed $extractorResponse,
        string $from = self::SENDER,
        string $pdf = self::PDF,
        ?string $subject = null,
        string $filename = 'report.pdf',
    ): ?ReportDocument {
        $this->app->instance(ReportExtractor::class, new FakeReportExtractor($extractorResponse));

        $message = InboundMessage::create([
            'source' => InboundSource::Email,
            'external_id' => (string) Str::uuid(),
            'from_email' => $from,
            'subject' => $subject,
        ]);

        $document = app(IngestReportFile::class)($pdf, $filename, $message);

        return $document === null ? null : ReportDocument::withoutGlobalScopes()->find($document->id);
    }

    public function test_manager_flash_is_extracted_validated_and_stored_with_last_year(): void
    {
        $property = $this->property();

        $document = $this->ingest(FakeReportExtractor::flash());

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame(ReportType::ManagerFlash, $document->report_type);
        $this->assertSame($property->id, $document->property_id);
        $this->assertSame($property->group_id, $document->group_id);

        $day = DailyPropertyStat::where('period', 'day')->sole();
        $this->assertSame(80, $day->rooms_occupied);
        $this->assertSame(2, $day->comp_rooms);
        $this->assertSame('500.00', $day->printed_adr);
        $this->assertSame(['day', 'day_ly', 'mtd'], DailyPropertyStat::orderBy('period')->pluck('period')->map->value->all());
        $this->assertSame(4, RevenueLine::count());
    }

    public function test_provisional_flag_is_kept(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::flash(['provisional' => true]));

        $this->assertTrue($document->is_provisional);
        $this->assertTrue(DailyPropertyStat::where('period', 'day')->sole()->is_provisional);
    }

    public function test_balanced_gl_trial_balance_is_stored_with_its_period(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::trialBalance());

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame('2026-11-01', $document->period_start->toDateString());
        $this->assertSame(2, TrialBalanceLine::count());
        $this->assertSame('-1500.00', TrialBalanceLine::where('account_code', '410101')->sole()->closing_balance);
    }

    public function test_unbalanced_trial_balance_goes_to_review(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::trialBalance(credit: 1490.0));

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertStringContainsString('does not equal total credit', $document->status_reason);
        $this->assertSame(0, TrialBalanceLine::count());
    }

    public function test_front_office_trial_balance_stores_ledger_balances(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::frontOffice());

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame('-9720.07', LedgerBalance::where('ledger', 'guest')->sole()->closing_balance);
        $this->assertSame(4, LedgerBalance::count());
    }

    public function test_front_office_hotel_balance_mismatch_goes_to_review(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::frontOffice(hotelBalance: -19293.62));

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertStringContainsString('Hotel balance', $document->status_reason);
    }

    public function test_misread_occupancy_goes_to_review(): void
    {
        $this->property();
        $flash = FakeReportExtractor::flash();
        $flash['manager_flash']['metrics'][4]['day'] = 60.0;   // printed 80 → misread as 60

        $document = $this->ingest($flash);

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertStringContainsString('occupancy', $document->status_reason);
    }

    public function test_business_date_must_agree_with_the_email_subject(): void
    {
        $this->property();

        $document = $this->ingest(
            FakeReportExtractor::flash(),
            subject: 'Manager Flash – فندقة الرياض – 2026-11-19',
            filename: 'manager_flash_20261119.pdf',
        );

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertStringContainsString('email subject / file name says [2026-11-19]', $document->status_reason);
    }

    public function test_report_naming_a_different_hotel_than_the_sender_goes_to_review(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::flash(['property_name' => 'Some Other Hotel']));

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertNull($document->property_id);
        $this->assertStringContainsString('Add it as an alias', $document->status_reason);
    }

    public function test_shared_sender_is_resolved_by_arabic_name_in_report(): void
    {
        $group = $this->group();
        $this->property($group, ['name' => 'Fandaqah Jeddah', 'name_ar' => 'فندقة جدة', 'code' => 'JED01']);
        $riyadh = $this->property($group);

        $document = $this->ingest(FakeReportExtractor::flash(['property_name' => 'فندقه الرياض']));

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame($riyadh->id, $document->property_id);
    }

    public function test_hotel_is_resolved_from_the_subject_when_the_report_prints_no_name(): void
    {
        $group = $this->group();
        $this->property($group, ['name' => 'Fandaqah Jeddah', 'name_ar' => 'فندقة جدة', 'code' => 'JED01']);
        $riyadh = $this->property($group);

        $document = $this->ingest(
            FakeReportExtractor::flash(['property_name' => null]),
            subject: 'Manager Flash – فندقة الرياض – 2026-11-20',
        );

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame($riyadh->id, $document->property_id);
    }

    public function test_newer_report_for_same_day_replaces_older_data(): void
    {
        $this->property();
        $this->ingest(FakeReportExtractor::flash(['provisional' => true]));

        $final = FakeReportExtractor::flash();
        $final['manager_flash']['revenue_departments'] = [];
        $this->ingest($final, pdf: self::PDF.'% final');

        $this->assertSame(0, RevenueLine::count());
        $this->assertSame(3, DailyPropertyStat::count());
        $this->assertFalse(DailyPropertyStat::where('period', 'day')->sole()->is_provisional);
    }

    public function test_same_file_is_never_ingested_twice(): void
    {
        $this->property();

        $this->assertNotNull($this->ingest(FakeReportExtractor::flash()));
        $this->assertNull($this->ingest(FakeReportExtractor::flash()));
        $this->assertSame(1, ReportDocument::withoutGlobalScopes()->count());
    }

    public function test_files_that_are_not_pdf_or_json_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->ingest(FakeReportExtractor::flash(), pdf: 'MZ not a report');
    }

    public function test_non_retryable_extraction_failure_marks_document_failed(): void
    {
        $this->property();

        $document = $this->ingest(new ExtractionFailed('Claude declined to read this document.', 'refusal'));

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame('refusal', $document->extractionRuns()->sole()->stop_reason);
    }
}
