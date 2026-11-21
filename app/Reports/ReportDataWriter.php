<?php

namespace App\Reports;

use App\Enums\ReportType;
use App\Models\DailyPropertyStat;
use App\Models\Property;
use App\Models\ReportDocument;
use App\Models\RevenueLine;
use App\Models\TrialBalanceLine;
use App\Reports\Extraction\ExtractionSchema;
use Illuminate\Support\Facades\DB;

/**
 * Writes validated extraction output into the canonical report tables.
 *
 * A newer report for the same hotel, type and business date replaces the
 * earlier data (e.g. a PMS re-sends a corrected flash report).
 */
class ReportDataWriter
{
    /** @param array<string, mixed> $output */
    public function write(ReportDocument $document, Property $property, array $output): void
    {
        $date = $output['business_date'];
        $currency = strtoupper($output['currency'] ?? $property->currency);

        DB::transaction(function () use ($document, $property, $output, $date, $currency) {
            match ($document->report_type) {
                ReportType::ManagerFlash => $this->writeFlash($document, $property, $output['manager_flash'], $date, $currency),
                ReportType::TrialBalance => $this->writeTrialBalance($document, $property, $output['trial_balance'], $date, $currency),
                ReportType::Unknown => throw new \LogicException('Cannot store data for an unknown report type.'),
            };
        });
    }

    /** @param array<string, mixed> $flash */
    private function writeFlash(ReportDocument $document, Property $property, array $flash, string $date, string $currency): void
    {
        DailyPropertyStat::where('property_id', $property->id)->whereDate('business_date', $date)->delete();
        RevenueLine::where('property_id', $property->id)->whereDate('business_date', $date)->delete();

        $base = [
            'tenant_id' => $property->tenant_id,
            'property_id' => $property->id,
            'report_document_id' => $document->id,
            'business_date' => $date,
            'currency' => $currency,
        ];

        foreach (['day', 'mtd', 'ytd'] as $period) {
            $values = [];
            foreach ($flash['metrics'] as $row) {
                $metric = $row['metric'];
                if (array_key_exists($metric, ExtractionSchema::FLASH_METRICS) && $row[$period] !== null) {
                    $values[$metric] = in_array($metric, ExtractionSchema::COUNT_METRICS, true)
                        ? (int) round($row[$period])
                        : round((float) $row[$period], 2);
                }
            }

            if ($values !== []) {
                DailyPropertyStat::create([...$base, 'period' => $period, ...$values]);
            }

            foreach ($flash['revenue_departments'] as $line) {
                if ($line[$period] !== null) {
                    RevenueLine::create([
                        ...$base,
                        'period' => $period,
                        'department' => $line['department'],
                        'category' => $line['category'],
                        'amount' => $line[$period],
                    ]);
                }
            }
        }
    }

    /** @param array<string, mixed> $tb */
    private function writeTrialBalance(ReportDocument $document, Property $property, array $tb, string $date, string $currency): void
    {
        TrialBalanceLine::where('property_id', $property->id)->whereDate('business_date', $date)->delete();

        $now = now();
        $rows = array_map(fn (array $line) => [
            'tenant_id' => $property->tenant_id,
            'property_id' => $property->id,
            'report_document_id' => $document->id,
            'business_date' => $date,
            'account_code' => $line['account_code'],
            'account_name' => $line['account_name'],
            'account_type' => $line['account_type'],
            'opening_balance' => $line['opening_balance'],
            'debit' => $line['debit'],
            'credit' => $line['credit'],
            'closing_balance' => $line['closing_balance'],
            'currency' => $currency,
            'created_at' => $now,
            'updated_at' => $now,
        ], $tb['lines']);

        foreach (array_chunk($rows, 500) as $chunk) {
            TrialBalanceLine::insert($chunk);
        }
    }
}
