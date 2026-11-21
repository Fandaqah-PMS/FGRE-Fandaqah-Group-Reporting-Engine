<?php

namespace Tests\Feature;

use App\Enums\InboundSource;
use App\Models\InboundMessage;
use App\Models\ReportDocument;
use App\Reports\Extraction\ReportExtractor;
use App\Reports\IngestReportFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesGroups;
use Tests\Fakes\FakeReportExtractor;
use Tests\TestCase;

class WebPagesTest extends TestCase
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

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    public function test_users_sign_in_with_email_and_password(): void
    {
        $user = $this->member($this->group());

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_dashboard_shows_group_kpis_with_last_year(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash(FakeReportExtractor::flash());

        $this->actingAs($this->member($group))
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('filters.date', '2026-11-20')
                ->where('group.hotel_count', 1)
                // (80 - 2 comp) / 100 sellable = 78 %; ADR 40000 / 78 = 512.82
                ->where('group.occupancy_pct', '78.00')
                ->where('group.adr', '512.82')
                ->where('groupLy.occupancy_pct', '70.00')
                ->has('hotels', 1)
                ->has('trend', 1));
    }

    public function test_viewer_cannot_open_financials_or_hotels_outside_their_list(): void
    {
        $group = $this->group();
        $allowed = $this->property($group, ['code' => 'A']);
        $other = $this->property($group, ['code' => 'B', 'name' => 'Other Hotel', 'name_ar' => 'فندق آخر']);
        $viewer = $this->member($group, 'viewer', allProperties: false);
        $viewer->restrictedProperties()->attach($allowed->id);

        $this->actingAs($viewer)->get('/trial-balance')->assertForbidden();
        $this->actingAs($viewer)->get('/ledgers')->assertForbidden();
        $this->actingAs($viewer)->get("/hotels/{$other->id}")->assertNotFound();
        $this->actingAs($viewer)->post('/scope', ['hotel' => $other->id])->assertNotFound();
        $this->actingAs($viewer)->get("/hotels/{$allowed->id}")->assertRedirect('/');
    }

    public function test_hotels_of_another_group_are_not_found(): void
    {
        $foreign = $this->property($this->group('Other group'));

        $this->actingAs($this->member($this->group()))->get("/hotels/{$foreign->id}")->assertNotFound();
    }

    public function test_switching_to_one_hotel_scopes_every_page(): void
    {
        $group = $this->group();
        $riyadh = $this->property($group);
        $jeddah = $this->property($group, ['code' => 'JED', 'name' => 'Fandaqah Jeddah', 'name_ar' => 'فندقة جدة']);
        $this->ingestFlash(FakeReportExtractor::flash());
        $this->ingestFlash(FakeReportExtractor::flash(['property_name' => 'Fandaqah Jeddah']), '%PDF-1.7
% jeddah');
        $owner = $this->member($group);

        $this->actingAs($owner)->get('/')->assertInertia(fn (Assert $page) => $page->where('group.hotel_count', 2)->where('scope.current', null));

        $this->actingAs($owner)->post('/scope', ['hotel' => $jeddah->id])->assertRedirect();

        $this->actingAs($owner)->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('scope.current', $jeddah->id)
            ->where('group.hotel_count', 1)
            ->where('heatmap', null));
        $this->actingAs($owner)->get('/manager-flash')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('ManagerFlash')
            ->has('hotels', 1)
            ->where('hotels.0.id', $jeddah->id)
            ->where('columns.day.occupancy_pct', '78.00'));

        $this->actingAs($owner)->post('/scope', ['hotel' => 'all']);
        $this->actingAs($owner)->get('/manager-flash')->assertInertia(fn (Assert $page) => $page->has('hotels', 2)
            ->where('columns.day.rooms_occupied', '160'));
        $this->assertNotNull($riyadh);
    }

    public function test_report_pages_render(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash(FakeReportExtractor::flash());
        $owner = $this->member($group);

        foreach (['/' => 'Dashboard', '/group-report' => 'GroupReport', '/manager-flash' => 'ManagerFlash', '/trial-balance' => 'TrialBalance', '/ledgers' => 'Ledgers', '/documents' => 'Documents'] as $url => $component) {
            $this->actingAs($owner)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
        }
        $this->actingAs($owner)->get('/financials')->assertRedirect('/trial-balance');
    }

    public function test_only_reviewers_can_rerun_a_document(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash(FakeReportExtractor::flash());
        $document = ReportDocument::withoutGlobalScopes()->sole();

        $this->actingAs($this->member($group, 'viewer'))->post("/documents/{$document->id}/reprocess")->assertForbidden();
        $this->actingAs($this->member($group, 'finance'))->post("/documents/{$document->id}/reprocess")->assertRedirect();
    }

    public function test_unmatched_documents_are_invisible_to_group_users(): void
    {
        $group = $this->group();
        $this->property($group);
        $this->ingestFlash(FakeReportExtractor::flash(['property_name' => 'Unknown Hotel']));
        $document = ReportDocument::withoutGlobalScopes()->sole();

        $this->assertNull($document->group_id);
        $this->actingAs($this->member($group))->post("/documents/{$document->id}/reprocess")->assertNotFound();
    }

    public function test_switching_language_is_remembered(): void
    {
        $user = $this->member($this->group());

        $this->actingAs($user)->post('/locale', ['locale' => 'en'])->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);
    }
}
