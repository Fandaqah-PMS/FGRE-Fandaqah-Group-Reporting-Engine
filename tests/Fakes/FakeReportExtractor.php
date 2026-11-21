<?php

namespace Tests\Fakes;

use App\Models\ReportDocument;
use App\Reports\Extraction\ExtractionFailed;
use App\Reports\Extraction\ExtractionResult;
use App\Reports\Extraction\ReportExtractor;

class FakeReportExtractor implements ReportExtractor
{
    /** @param array<string, mixed>|ExtractionFailed $response */
    public function __construct(private array|ExtractionFailed $response) {}

    public function extract(ReportDocument $document): ExtractionResult
    {
        if ($this->response instanceof ExtractionFailed) {
            throw $this->response;
        }

        return new ExtractionResult($this->response, 'fake-model', 'end_turn', 1000, 500);
    }

    /** A balanced, internally consistent manager flash output. */
    public static function flash(array $overrides = []): array
    {
        return array_replace([
            'report_type' => 'manager_flash',
            'property_name' => 'Fandaqah Riyadh',
            'property_code' => 'RUH01',
            'business_date' => '2026-11-20',
            'currency' => 'SAR',
            'confidence' => 'high',
            'issues' => [],
            'manager_flash' => [
                'metrics' => [
                    ['metric' => 'rooms_available', 'label_as_printed' => 'Available Rooms', 'day' => 100, 'mtd' => 2000, 'ytd' => null],
                    ['metric' => 'rooms_sold', 'label_as_printed' => 'Rooms Sold', 'day' => 80, 'mtd' => 1500, 'ytd' => null],
                    ['metric' => 'occupancy_pct', 'label_as_printed' => 'Occupancy %', 'day' => 80.0, 'mtd' => 75.0, 'ytd' => null],
                    ['metric' => 'room_revenue', 'label_as_printed' => 'Room Revenue', 'day' => 40000.0, 'mtd' => 690000.0, 'ytd' => null],
                    ['metric' => 'adr', 'label_as_printed' => 'ADR', 'day' => 500.0, 'mtd' => 460.0, 'ytd' => null],
                    ['metric' => 'revpar', 'label_as_printed' => 'RevPAR', 'day' => 400.0, 'mtd' => 345.0, 'ytd' => null],
                    ['metric' => 'total_revenue', 'label_as_printed' => 'Total Revenue', 'day' => 52000.0, 'mtd' => 900000.0, 'ytd' => null],
                ],
                'revenue_departments' => [
                    ['department' => 'Rooms', 'category' => 'rooms', 'day' => 40000.0, 'mtd' => 690000.0, 'ytd' => null],
                    ['department' => 'Restaurant', 'category' => 'fnb', 'day' => 12000.0, 'mtd' => 210000.0, 'ytd' => null],
                ],
            ],
            'trial_balance' => null,
        ], $overrides);
    }

    /** A trial balance output; balanced unless $credit is changed. */
    public static function trialBalance(float $credit = 1500.0): array
    {
        return [
            'report_type' => 'trial_balance',
            'property_name' => 'Fandaqah Riyadh',
            'property_code' => 'RUH01',
            'business_date' => '2026-11-20',
            'currency' => 'SAR',
            'confidence' => 'high',
            'issues' => [],
            'manager_flash' => null,
            'trial_balance' => [
                'lines' => [
                    ['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'opening_balance' => 1000.0, 'debit' => 1500.0, 'credit' => 0.0, 'closing_balance' => 2500.0],
                    ['account_code' => '4000', 'account_name' => 'Room Revenue', 'account_type' => 'Revenue', 'opening_balance' => 0.0, 'debit' => 0.0, 'credit' => $credit, 'closing_balance' => $credit],
                ],
                'printed_total_debit' => 1500.0,
                'printed_total_credit' => $credit,
            ],
        ];
    }
}
