<?php

namespace App\Ingestion\Mcp;

use App\Enums\DocumentStatus;
use App\Enums\InboundSource;
use App\Models\InboundMessage;
use App\Models\McpConnection;
use App\Models\Property;
use App\Models\ReportDocument;
use App\Reports\IngestReportFile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Pulls reports from PMS MCP servers for the hotels set up for it.
 *
 * Per hotel, properties.mcp_settings says which tool returns which report:
 *
 *   {"reports": {
 *      "manager_flash": {"tool": "run_report", "arguments": {"report": "manager-flash", "hotel_id": "{pms_property_ref}", "date": "{business_date}"}},
 *      "trial_balance": {"tool": "run_report", "arguments": {"report": "fin-trial-balance", "hotel_id": "{pms_property_ref}", "from": "{month_start}", "to": "{business_date}"}}
 *   }}
 *
 * Placeholders: {pms_property_ref}, {property_code}, {business_date}, {month_start}, {year_start}.
 * The business date pulled is the last day closed by night audit: yesterday once the hotel's
 * local time passes properties.night_audit_time, otherwise the day before.
 *
 * Whatever the tool returns (structured JSON, a PDF resource, or text) goes through the same
 * pipeline as an emailed report.
 */
class McpReportPuller
{
    public const PLACEHOLDERS = ['pms_property_ref', 'property_code', 'business_date', 'month_start', 'year_start'];

    /** @var callable(McpConnection): McpClient */
    private $clientFactory;

    public function __construct(private readonly IngestReportFile $ingest, ?callable $clientFactory = null)
    {
        $this->clientFactory = $clientFactory ?? fn (McpConnection $c) => new McpClient($c);
    }

    /** @return array{pulled: int, skipped: int, failed: int} */
    public function pullAll(?CarbonImmutable $now = null): array
    {
        $stats = ['pulled' => 0, 'skipped' => 0, 'failed' => 0];

        // Runs from the scheduler with no logged-in user: every group's connections.
        $connections = McpConnection::withoutGlobalScopes()->where('is_active', true)->get();

        foreach ($connections as $connection) {
            $client = ($this->clientFactory)($connection);
            $errors = [];

            $properties = Property::forGroup($connection->group_id)
                ->where('mcp_connection_id', $connection->id)
                ->where('is_active', true)
                ->get();

            foreach ($properties as $property) {
                foreach ($property->mcp_settings['reports'] ?? [] as $reportType => $call) {
                    try {
                        $result = $this->pull($client, $connection, $property, $reportType, $call, $now);
                        $stats[$result]++;
                    } catch (Throwable $e) {
                        $stats['failed']++;
                        $errors[] = "{$property->name} / {$reportType}: {$e->getMessage()}";
                    }
                }
            }

            $connection->forceFill([
                'last_polled_at' => now(),
                'last_error' => $errors === [] ? null : implode("\n", $errors),
            ])->save();
        }

        return $stats;
    }

    /** @return 'pulled'|'skipped' */
    private function pull(McpClient $client, McpConnection $connection, Property $property, string $reportType, array $call, ?CarbonImmutable $now): string
    {
        $date = $this->closedBusinessDate($property, $now);

        $alreadyFinal = ReportDocument::forGroup($property->group_id)
            ->where('property_id', $property->id)
            ->where('report_type', $reportType)
            ->whereDate('business_date', $date)
            ->where('status', DocumentStatus::Processed)
            ->where('is_provisional', false)
            ->exists();
        if ($alreadyFinal) {
            return 'skipped';
        }

        $arguments = $this->fill($call['arguments'] ?? [], $property, $date);
        [$contents, $filename] = $this->payload($client->callTool($call['tool'], $arguments), "{$reportType}_{$date->format('Ymd')}");

        $message = InboundMessage::create([
            'source' => InboundSource::Mcp,
            'property_id' => $property->id,
            'external_id' => (string) Str::uuid(),
            'subject' => "{$connection->name}: {$call['tool']} ({$reportType}) {$property->name} {$date->toDateString()}",
            'received_at' => now(),
            'attachment_count' => 1,
            'status' => 'processed',
        ]);

        // Identical content was already ingested: nothing new from the PMS yet.
        return ($this->ingest)($contents, $filename, $message, allowText: true) === null ? 'skipped' : 'pulled';
    }

    public function closedBusinessDate(Property $property, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $local = ($now ?? CarbonImmutable::now())->setTimezone($property->timezone);
        [$hour, $minute] = array_map('intval', explode(':', $property->night_audit_time ?: '04:00'));
        $auditDone = $local->setTime($hour, $minute);

        return $local->startOfDay()->subDays($local->greaterThanOrEqualTo($auditDone) ? 1 : 2);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function fill(array $arguments, Property $property, CarbonImmutable $date): array
    {
        $values = [
            '{pms_property_ref}' => (string) $property->pms_property_ref,
            '{property_code}' => $property->code,
            '{business_date}' => $date->toDateString(),
            '{month_start}' => $date->startOfMonth()->toDateString(),
            '{year_start}' => $date->startOfYear()->toDateString(),
        ];

        array_walk_recursive($arguments, function (&$value) use ($values) {
            if (is_string($value)) {
                $value = strtr($value, $values);
            }
        });

        return $arguments;
    }

    /**
     * Turn a tool result into file contents: structured JSON, an embedded PDF, or text.
     *
     * @param  array<string, mixed>  $result
     * @return array{0: string, 1: string} [contents, file name]
     */
    private function payload(array $result, string $basename): array
    {
        if (isset($result['structuredContent'])) {
            return [json_encode($result['structuredContent'], JSON_UNESCAPED_UNICODE), "{$basename}.json"];
        }

        $texts = [];
        foreach ($result['content'] ?? [] as $item) {
            $resource = $item['resource'] ?? $item;
            $mime = $resource['mimeType'] ?? null;

            if (isset($resource['blob']) && $mime === 'application/pdf') {
                return [base64_decode($resource['blob'], true) ?: throw new InvalidArgumentException('PDF resource is not valid base64.'), "{$basename}.pdf"];
            }

            if (isset($resource['text'])) {
                $texts[] = $resource['text'];
            }
        }

        if ($texts === []) {
            throw new InvalidArgumentException('MCP tool returned no report data.');
        }

        $text = implode("\n\n", $texts);

        return [$text, is_array(json_decode($text, true)) ? "{$basename}.json" : "{$basename}.txt"];
    }
}
