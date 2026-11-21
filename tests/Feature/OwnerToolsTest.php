<?php

namespace Tests\Feature;

use App\Enums\InboundSource;
use App\Mail\MorningFlashMail;
use App\Models\BudgetMonth;
use App\Models\InboundMessage;
use App\Reports\AttentionService;
use App\Reports\Extraction\ReportExtractor;
use App\Reports\IngestReportFile;
use App\Reports\Kpi\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesGroups;
use Tests\Fakes\FakeReportExtractor;
use Tests\TestCase;

class OwnerToolsTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
    }

    private function ingestFlash(array $output, string $pdf = "%PDF-1.7\n"): void
    {
        $this->app->instance(ReportExtractor::class, new FakeReportExtractor($output));
        $message = InboundMessage::create(['source' => InboundSource::Email, 'external_id' => (string) Str::uuid(), 'from_email' => self::SENDER]);
        app(IngestReportFile::class)($pdf, 'flash.pdf', $message);
    }

    public function test_budget_to_date_spreads_the_month_evenly(): void
    {
        $group = $this->group();
        $hotel = $this->property($group, ['total_rooms' => 100]);
        BudgetMonth::forGroup($group->id)->create([
            'group_id' => $group->id, 'property_id' => $hotel->id, 'month' => '2026-11-01',
            'rooms_sold' => 2400, 'room_revenue' => 1200000, 'fnb_revenue' => 300000, 'other_revenue' => 0, 'total_revenue' => 1500000,
        ]);

        $b = app(BudgetService::class)->forHotels($group->id, '2026-11-15', [$hotel->id])[$hotel->id];

        // November has 30 days: MTD = 15/30 of the month, rooms available = 100 × 15.
        $this->assertEquals(1200, $b['mtd']['rooms_occupied']);
        $this->assertEquals(600000, $b['mtd']['room_revenue']);
        $this->assertSame('80.00', $b['mtd']['occupancy_pct']);    // 1200 / 1500
        $this->assertSame('500.00', $b['mtd']['adr']);
        $this->assertEquals(80, $b['day']['rooms_occupied']);       // 2400 / 30
    }

    public function test_budget_upload_saves_valid_files_and_rejects_bad_rows(): void
    {
        $group = $this->group();
        $hotel = $this->property($group, ['total_rooms' => 100]);
        $owner = $this->member($group);
        $csv = fn (string $rows) => UploadedFile::fake()->createWithContent('budget.csv', "hotel_code,month,rooms_sold,room_revenue,fnb_revenue,other_revenue,total_revenue\n{$rows}");

        $this->actingAs($owner)->post('/budgets/import', ['file' => $csv("RUH01,2026-12,2500,1250000,200000,50000,1500000\nNOPE,2026-12,1,1,0,0,1")])
            ->assertSessionHas('budget_errors');
        $this->assertSame(0, BudgetMonth::forGroup($group->id)->count(), 'one bad row: nothing is saved');

        $this->actingAs($owner)->post('/budgets/import', ['file' => $csv('RUH01,2026-12,2500,1250000,200000,50000,1500000')])
            ->assertSessionHas('status');
        $this->assertSame(2500, BudgetMonth::forGroup($group->id)->where('property_id', $hotel->id)->sole()->rooms_sold);

        $this->actingAs($this->member($group, 'viewer'))->post('/budgets/import', ['file' => $csv('RUH01,2026-12,1,1,0,0,1')])->assertForbidden();
    }

    public function test_needs_attention_flags_missing_reports_and_budget_shortfall(): void
    {
        $group = $this->group();
        $riyadh = $this->property($group, ['total_rooms' => 100]);
        $jeddah = $this->property($group, ['code' => 'JED', 'name' => 'Fandaqah Jeddah', 'name_ar' => 'فندقة جدة']);
        $this->ingestFlash(FakeReportExtractor::flash());
        BudgetMonth::forGroup($group->id)->create([
            'group_id' => $group->id, 'property_id' => $riyadh->id, 'month' => '2026-11-01',
            'rooms_sold' => 2700, 'room_revenue' => 3000000, 'fnb_revenue' => 0, 'other_revenue' => 0, 'total_revenue' => 3000000,
        ]);

        $items = collect(app(AttentionService::class)->items($group->id, '2026-11-20', collect([$riyadh->id, $jeddah->id])));

        $this->assertTrue($items->contains(fn ($i) => $i['type'] === 'missing_report' && $i['hotel']['id'] === $jeddah->id && $i['level'] === 'critical'));
        // MTD 900,000 vs budget 3,000,000 × 20/30 = 2,000,000 → −55 %
        $this->assertTrue($items->contains(fn ($i) => $i['type'] === 'below_budget' && $i['hotel']['id'] === $riyadh->id && $i['level'] === 'critical'));
        $this->assertSame('critical', $items->first()['level'], 'most serious first');
    }

    public function test_morning_email_goes_to_owners_once_per_business_date(): void
    {
        Mail::fake();
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash(FakeReportExtractor::flash());
        $owner = $this->member($group, 'owner');
        $viewer = $this->member($group, 'viewer');
        $optedOut = $this->member($group, 'finance');
        $optedOut->forceFill(['morning_email' => false])->save();

        $this->artisan('fgre:send-morning-flash')->assertSuccessful();
        $this->artisan('fgre:send-morning-flash')->assertSuccessful();

        Mail::assertSent(MorningFlashMail::class, 1);
        Mail::assertSent(MorningFlashMail::class, fn ($m) => $m->hasTo($owner->email) && $m->report['date'] === '2026-11-20');
        Mail::assertNotSent(MorningFlashMail::class, fn ($m) => $m->hasTo($viewer->email) || $m->hasTo($optedOut->email));
    }

    public function test_group_budget_only_counts_hotels_that_reported(): void
    {
        $group = $this->group();
        $riyadh = $this->property($group, ['total_rooms' => 100]);
        $jeddah = $this->property($group, ['code' => 'JED', 'name' => 'Fandaqah Jeddah', 'name_ar' => 'فندقة جدة', 'total_rooms' => 200]);
        foreach ([$riyadh, $jeddah] as $hotel) {
            BudgetMonth::forGroup($group->id)->create([
                'group_id' => $group->id, 'property_id' => $hotel->id, 'month' => '2026-11-01',
                'rooms_sold' => 1500, 'room_revenue' => 750000, 'fnb_revenue' => 0, 'other_revenue' => 0, 'total_revenue' => 750000,
            ]);
        }
        $this->ingestFlash(FakeReportExtractor::flash());   // only Riyadh reports

        $this->actingAs($this->member($group))->get('/manager-flash?view=compare&basis=budget')->assertInertia(fn (Assert $page) => $page
            ->where('budget.group.day.total_rooms', '100')    // Riyadh only, not Riyadh + Jeddah
            ->where('budget.group.day.room_revenue', '25000.00'));
    }

    public function test_compare_view_supports_hotel_sets_and_regions(): void
    {
        $group = $this->group();
        $riyadh = $this->property($group, ['region' => 'Central']);
        $jeddah = $this->property($group, ['code' => 'JED', 'name' => 'Fandaqah Jeddah', 'name_ar' => 'فندقة جدة', 'region' => 'Western']);
        $this->ingestFlash(FakeReportExtractor::flash());
        $this->ingestFlash(FakeReportExtractor::flash(['property_name' => 'Fandaqah Jeddah']), "%PDF-1.7\n% jeddah");
        $owner = $this->member($group);

        $this->actingAs($owner)->get('/manager-flash?view=compare')->assertInertia(fn (Assert $page) => $page
            ->has('hotels', 2)->has('regions', 2)->where('regions.0.key', 'Central'));

        $this->actingAs($owner)->get("/manager-flash?view=compare&hotels={$jeddah->id}")->assertInertia(fn (Assert $page) => $page
            ->has('hotels', 1)->where('hotels.0.id', $jeddah->id)->where('selection', [$jeddah->id])->where('columns.day.hotel_count', 1));

        $this->actingAs($owner)->get('/group-report')->assertRedirect('/manager-flash?view=compare');
        $this->assertNotNull($riyadh);
    }
}
