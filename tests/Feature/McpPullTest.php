<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Ingestion\Mcp\McpReportPuller;
use App\Models\McpConnection;
use App\Models\ReportDocument;
use App\Reports\Extraction\ReportExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesGroups;
use Tests\Fakes\FakeReportExtractor;
use Tests\TestCase;

class McpPullTest extends TestCase
{
    use CreatesGroups, RefreshDatabase;

    private const FLASH_JSON = __DIR__.'/../../docs/samples/fandaqah-pms/fandaqah-sample-manager-flash-20261119-en.json';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** A fake MCP server: answers initialize / tools/call, records the tool arguments. */
    private function fakeServer(array $toolResult, array &$calls): void
    {
        Http::fake(function (Request $request) use ($toolResult, &$calls) {
            $body = $request->data();
            $method = $body['method'] ?? null;

            if ($method === 'notifications/initialized') {
                return Http::response('', 202);
            }
            if ($method === 'tools/call') {
                $calls[] = json_decode(json_encode($body['params']), true);
            }

            $result = match ($method) {
                'initialize' => ['protocolVersion' => '2025-06-18', 'capabilities' => ['tools' => (object) []], 'serverInfo' => ['name' => 'fake-pms']],
                'tools/call' => $toolResult,
                default => [],
            };

            // Answer as an SSE stream, like many Streamable HTTP servers do.
            return Http::response("event: message\ndata: ".json_encode(['jsonrpc' => '2.0', 'id' => $body['id'], 'result' => $result])."\n\n", 200, [
                'Content-Type' => 'text/event-stream',
                'Mcp-Session-Id' => 'session-1',
            ]);
        });
    }

    private function setUpHotel(array $call): array
    {
        $group = $this->group();
        $connection = McpConnection::forGroup($group->id)->create([
            'group_id' => $group->id, 'name' => 'Fandaqah MCP', 'url' => 'https://pms.example/mcp', 'auth_type' => 'bearer', 'secret' => 'token-123',
        ]);
        $property = $this->property($group, [
            'name' => 'Address Hotel', 'name_ar' => 'فندق العنوان', 'code' => 'ADDR', 'pms_property_ref' => '2411',
            'allowed_sender_emails' => [], 'timezone' => 'Asia/Riyadh', 'night_audit_time' => '04:00',
            'mcp_connection_id' => $connection->id,
            'mcp_settings' => ['reports' => ['manager_flash' => $call]],
        ]);

        return [$connection, $property];
    }

    public function test_structured_json_from_an_mcp_tool_is_imported_without_ai(): void
    {
        $calls = [];
        $this->fakeServer(['content' => [], 'structuredContent' => json_decode(file_get_contents(self::FLASH_JSON), true)], $calls);
        [$connection, $property] = $this->setUpHotel([
            'tool' => 'run_report',
            'arguments' => ['report' => 'manager-flash', 'hotel_id' => '{pms_property_ref}', 'date' => '{business_date}'],
        ]);

        // 2026-11-20 10:00 Riyadh: night audit for the 19th is done.
        $stats = app(McpReportPuller::class)->pullAll(CarbonImmutable::parse('2026-11-20 07:00:00', 'UTC'));

        $this->assertSame(['pulled' => 1, 'skipped' => 0, 'failed' => 0], $stats);
        $this->assertSame(['report' => 'manager-flash', 'hotel_id' => '2411', 'date' => '2026-11-19'], $calls[0]['arguments']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer token-123'));

        $document = ReportDocument::withoutGlobalScopes()->sole();
        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
        $this->assertSame($property->id, $document->property_id);
        $this->assertSame('fandaqah_json', $document->extractionRuns()->sole()->extractor);
        $this->assertNull($connection->fresh()->last_error);
    }

    public function test_a_pdf_returned_by_a_tool_goes_to_the_ai_extractor(): void
    {
        $this->app->instance(ReportExtractor::class, new FakeReportExtractor(FakeReportExtractor::flash([
            'property_name' => 'Address Hotel', 'business_date' => '2026-11-19',
        ])));
        $calls = [];
        $this->fakeServer(['content' => [['type' => 'resource', 'resource' => [
            'uri' => 'pms://reports/flash.pdf', 'mimeType' => 'application/pdf', 'blob' => base64_encode("%PDF-1.7\n flash"),
        ]]]], $calls);
        $this->setUpHotel(['tool' => 'get_flash_pdf', 'arguments' => []]);

        app(McpReportPuller::class)->pullAll(CarbonImmutable::parse('2026-11-20 07:00:00', 'UTC'));

        $document = ReportDocument::withoutGlobalScopes()->sole();
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame(DocumentStatus::Processed, $document->status, (string) $document->status_reason);
    }

    public function test_days_already_final_are_not_pulled_again(): void
    {
        $calls = [];
        $sample = json_decode(file_get_contents(self::FLASH_JSON), true);
        $sample['meta']['provisional'] = false;
        $this->fakeServer(['content' => [], 'structuredContent' => $sample], $calls);
        $this->setUpHotel(['tool' => 'run_report', 'arguments' => ['date' => '{business_date}']]);
        $now = CarbonImmutable::parse('2026-11-20 07:00:00', 'UTC');

        app(McpReportPuller::class)->pullAll($now);
        $second = app(McpReportPuller::class)->pullAll($now);

        $this->assertSame(['pulled' => 0, 'skipped' => 1, 'failed' => 0], $second);
        $this->assertCount(1, $calls);
    }

    public function test_business_date_waits_for_night_audit_in_hotel_time(): void
    {
        [, $property] = $this->setUpHotel(['tool' => 'x', 'arguments' => []]);
        $puller = app(McpReportPuller::class);

        // 03:00 in Riyadh on the 20th: the 19th is not closed yet, so the 18th.
        $this->assertSame('2026-11-18', $puller->closedBusinessDate($property, CarbonImmutable::parse('2026-11-20 00:00:00', 'UTC'))->toDateString());
        // 05:00 in Riyadh: the 19th.
        $this->assertSame('2026-11-19', $puller->closedBusinessDate($property, CarbonImmutable::parse('2026-11-20 02:00:00', 'UTC'))->toDateString());
    }
}
