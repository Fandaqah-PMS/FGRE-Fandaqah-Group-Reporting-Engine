<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\InboundSource;
use App\Enums\StatPeriod;
use App\Models\DailyPropertyStat;
use App\Models\InboundMessage;
use App\Models\ReportDocument;
use App\Models\TrialBalanceLine;
use App\Reports\Extraction\FandaqahReportsPlusExtractor;
use App\Reports\IngestReportFile;
use App\Reports\Kpi\GroupKpiService;
use App\Reports\Validation\ExtractionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesGroups;
use Tests\TestCase;

/**
 * Real Fandaqah PMS Reports Plus output (docs/samples/fandaqah-pms, demo hotel
 * "Address Hotel"). Proves the mapper, the checks and the KPI maths against
 * actual PMS numbers.
 */
class FandaqahSamplesTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private const DIR = __DIR__.'/../../docs/samples/fandaqah-pms/';

    private const FLASH = 'fandaqah-sample-manager-flash-20261119-en.json';

    private const GL_TB = 'fandaqah-sample-fin-trial-balance-202611-en.json';

    private function sample(string $file): array
    {
        $mapper = new FandaqahReportsPlusExtractor;

        return $mapper->map($mapper->decode(file_get_contents(self::DIR.$file)));
    }

    public function test_manager_flash_sample_maps_to_all_six_columns(): void
    {
        $output = $this->sample(self::FLASH);

        $this->assertSame('manager_flash', $output['report_type']);
        $this->assertSame('2026-11-19', $output['business_date'], 'report date comes from filters.date, not meta.business_date');
        $this->assertTrue($output['provisional']);
        $this->assertSame('Address Hotel', $output['property_name']);

        $metrics = collect($output['manager_flash']['metrics'])->keyBy('metric');
        $this->assertCount(count(FandaqahReportsPlusExtractor::FLASH_CODES), $metrics);
        $this->assertSame(21318.0, $metrics['total_rooms']['ytd']);
        $this->assertSame(1938.0, $metrics['ooo_rooms']['ytd_ly']);
        $this->assertSame(42941.98, $metrics['room_revenue']['ytd']);
    }

    public function test_manager_flash_sample_passes_every_check(): void
    {
        $this->assertSame([], app(ExtractionValidator::class)->validate($this->sample(self::FLASH), '2026-11-19'));
    }

    public function test_gl_trial_balance_sample_maps_and_passes_every_check(): void
    {
        $output = $this->sample(self::GL_TB);

        $this->assertSame('trial_balance', $output['report_type']);
        $this->assertSame('2026-11-01', $output['period_start']);
        $this->assertSame('2026-11-19', $output['business_date']);
        $this->assertCount(20, $output['trial_balance']['lines']);
        $this->assertSame([], app(ExtractionValidator::class)->validate($output));
    }

    public function test_samples_flow_through_the_pipeline_and_give_headline_kpis(): void
    {
        Storage::fake('local');
        $group = $this->group();
        $property = $this->property($group, ['name' => 'Address Hotel', 'name_ar' => 'فندق العنوان', 'code' => 'ADDR', 'pms_property_ref' => '2411']);

        foreach ([self::FLASH, self::GL_TB] as $file) {
            $message = InboundMessage::create([
                'source' => InboundSource::Api, 'external_id' => (string) Str::uuid(), 'from_email' => self::SENDER,
            ]);
            app(IngestReportFile::class)(file_get_contents(self::DIR.$file), $file, $message);
        }

        $documents = ReportDocument::withoutGlobalScopes()->get();
        foreach ($documents as $document) {
            $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
            $this->assertSame('fandaqah_json', $document->extractionRuns()->sole()->extractor);
        }

        $this->assertSame(6, DailyPropertyStat::forGroup($group->id)->count());
        $this->assertSame(20, TrialBalanceLine::forGroup($group->id)->count());

        $ytd = app(GroupKpiService::class)->forDate($group->id, '2026-11-19', StatPeriod::YearToDate, [$property->id]);

        // USALI: (79 - 0 - 0) / 21318 × 100 = 0.3706 → 0.37
        $this->assertSame('0.37', $ytd['group']['occupancy_pct']);
        $this->assertSame('0.40', $ytd['group']['occupancy_excl_ooo_pct']);
        // 42941.98 / 79 = 543.569… → 543.57 (matches the printed ADR)
        $this->assertSame('543.57', $ytd['group']['adr']);
        // 42941.98 / 21318 = 2.0144 → 2.01
        $this->assertSame('2.01', $ytd['group']['revpar']);
        $this->assertTrue($ytd['group']['is_provisional']);

        // Day: nothing sold, so ADR has no denominator.
        $day = app(GroupKpiService::class)->forDate($group->id, '2026-11-19', StatPeriod::Day, [$property->id]);
        $this->assertSame('0.00', $day['group']['occupancy_pct']);
        $this->assertNull($day['group']['adr']);
    }
}
