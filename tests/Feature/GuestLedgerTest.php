<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\InboundSource;
use App\Enums\ReportType;
use App\Models\GuestLedgerLine;
use App\Models\InboundMessage;
use App\Models\LedgerBalance;
use App\Models\ReportDocument;
use App\Reports\AttentionService;
use App\Reports\Extraction\ReportExtractor;
use App\Reports\IngestReportFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesGroups;
use Tests\Fakes\FakeReportExtractor;
use Tests\TestCase;

/** Guest trial balance (e.g. Fandaqah EOD-010): folio balances per guest. */
class GuestLedgerTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->withoutVite();
    }

    private function ingest(array $output): ReportDocument
    {
        $this->app->instance(ReportExtractor::class, new FakeReportExtractor($output));
        $message = InboundMessage::create(['source' => InboundSource::Email, 'external_id' => (string) Str::uuid(), 'from_email' => self::SENDER]);
        $document = app(IngestReportFile::class)("%PDF-1.7\n% guest tb\n", 'EOD-010 Guest Trial Balance.pdf', $message);

        return ReportDocument::withoutGlobalScopes()->find($document->id);
    }

    public function test_guest_trial_balance_stores_folios_and_the_guest_ledger_total(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::guestTrialBalance());

        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame(ReportType::GuestTrialBalance, $document->report_type);
        $this->assertSame(4, GuestLedgerLine::count());
        $this->assertSame('998.26', GuestLedgerLine::where('room', '107')->sole()->closing_balance);
        $this->assertSame('-1434.09', GuestLedgerLine::where('room', '230')->sole()->closing_balance);
        $guest = LedgerBalance::where('ledger', 'guest')->sole();
        $this->assertSame('7900.00', $guest->closing_balance);
        $this->assertSame('9334.09', $guest->opening_balance);
    }

    public function test_folio_lines_that_do_not_add_up_to_the_printed_total_go_to_review(): void
    {
        $this->property();

        $document = $this->ingest(FakeReportExtractor::guestTrialBalance(closingTotal: 8900.0));

        $this->assertSame(DocumentStatus::NeedsReview, $document->status);
        $this->assertStringContainsString('closing balance', $document->status_reason);
        $this->assertSame(0, GuestLedgerLine::count());
    }

    public function test_unpaid_check_outs_and_large_folios_need_attention(): void
    {
        config(['fgre.attention.guest_balance_large' => 5000]);
        $hotel = $this->property();
        $this->ingest(FakeReportExtractor::guestTrialBalance());

        $items = collect(app(AttentionService::class)->items($hotel->group_id, '2026-11-20', collect([$hotel->id])));

        $unpaid = $items->firstWhere('type', 'guest_checked_out_balance');
        $this->assertSame(['count' => 1, 'value' => '998'], $unpaid['params']);
        $this->assertSame(1, $items->firstWhere('type', 'guest_large_balance')['params']['count']);   // room 102: 7,575.20
    }

    public function test_ledgers_page_lists_unpaid_check_outs_first(): void
    {
        $hotel = $this->property();
        $this->ingest(FakeReportExtractor::guestTrialBalance());

        $this->actingAs($this->member($hotel->group))->get('/ledgers')->assertInertia(fn (Assert $page) => $page
            ->component('Ledgers')
            ->where('folios.unpaid.count', 1)
            ->where('folios.unpaid.amount', 998.26)
            ->where('folios.credit.amount', -1434.09)
            ->where('folios.list.0.room', '107'));

        $this->actingAs($this->member($hotel->group, 'viewer'))->get('/ledgers')->assertForbidden();
    }
}
