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

        return new ExtractionResult($this->response, 'fake');
    }

    private static function header(string $type, array $overrides = []): array
    {
        return array_replace([
            'report_type' => $type,
            'report_title_as_printed' => null,
            'property_name' => 'Fandaqah Riyadh',
            'property_code' => null,
            'period_start' => null,
            'business_date' => '2026-11-20',
            'provisional' => false,
            'currency' => 'SAR',
            'confidence' => 'high',
            'issues' => [],
            'manager_flash' => null,
            'trial_balance' => null,
            'front_office_trial_balance' => null,
            'guest_trial_balance' => null,
        ], $overrides);
    }

    private static function row(string $metric, ?float $day, ?float $mtd = null, ?float $dayLy = null): array
    {
        return ['metric' => $metric, 'label_as_printed' => $metric, 'day' => $day, 'mtd' => $mtd, 'ytd' => null, 'day_ly' => $dayLy, 'mtd_ly' => null, 'ytd_ly' => null];
    }

    /** An internally consistent manager flash (100 rooms, 80 occupied, 2 comp). */
    public static function flash(array $overrides = []): array
    {
        return self::header('manager_flash', [
            'manager_flash' => [
                'metrics' => [
                    self::row('total_rooms', 100, 2000, 100),
                    self::row('rooms_occupied', 80, 1500, 70),
                    self::row('comp_rooms', 2, 10, 0),
                    self::row('ooo_rooms', 0, 0, 0),
                    self::row('occupancy_pct', 80.0, 75.0, 70.0),
                    self::row('room_revenue', 40000.0, 690000.0, 31500.0),
                    self::row('adr', 500.0, 460.0, 450.0),
                    self::row('fnb_revenue', 12000.0, 210000.0, 9000.0),
                    self::row('other_revenue', 0.0, 0.0, 0.0),
                    self::row('total_revenue', 52000.0, 900000.0, 40500.0),
                ],
                'revenue_departments' => [
                    ['department' => 'Rooms', 'category' => 'rooms', 'day' => 40000.0, 'mtd' => 690000.0, 'ytd' => null, 'day_ly' => null, 'mtd_ly' => null, 'ytd_ly' => null],
                    ['department' => 'Restaurant', 'category' => 'fnb', 'day' => 12000.0, 'mtd' => 210000.0, 'ytd' => null, 'day_ly' => null, 'mtd_ly' => null, 'ytd_ly' => null],
                ],
            ],
            ...$overrides,
        ]);
    }

    /** A general-ledger trial balance; balanced unless $credit is changed. */
    public static function trialBalance(float $credit = 1500.0): array
    {
        return self::header('trial_balance', [
            'period_start' => '2026-11-01',
            'trial_balance' => [
                'lines' => [
                    ['account_code' => '111101', 'local_account_code' => '111101', 'account_name' => 'Cash', 'account_class' => null, 'opening_balance' => null, 'debit' => 1500.0, 'credit' => 0.0, 'closing_balance' => 1500.0],
                    ['account_code' => '410101', 'local_account_code' => '410101', 'account_name' => 'Room Revenue', 'account_class' => null, 'opening_balance' => null, 'debit' => 0.0, 'credit' => $credit, 'closing_balance' => -$credit],
                ],
                'printed_total_debit' => 1500.0,
                'printed_total_credit' => $credit,
            ],
        ]);
    }

    /** A guest trial balance (Golden Tulip style): in house, checked out with balance, a credit folio. */
    public static function guestTrialBalance(float $closingTotal = 7900.0): array
    {
        $line = fn (string $room, string $status, float $open, float $debit, float $credit) => [
            'room' => $room, 'name' => "Guest {$room}", 'arrival' => '2026-11-18', 'departure' => '2026-11-22',
            'opening_balance' => $open, 'debit' => $debit, 'transfer_debit' => 0.0, 'credit' => $credit, 'transfer_credit' => 0.0,
            'closing_balance' => round($open + $debit - $credit, 2), 'status' => $status,
        ];

        return self::header('guest_trial_balance', [
            'guest_trial_balance' => [
                'lines' => [
                    $line('102', 'in_house', 7575.20, 0.0, 0.0),
                    $line('107', 'checked_out', 998.26, 0.0, 0.0),
                    $line('230', 'in_house', 0.0, 0.0, 1434.09),
                    $line('9003', 'in_house', 760.63, 0.0, 0.0),
                ],
                'printed_totals' => ['opening_balance' => 9334.09, 'debit' => 0.0, 'transfer_debit' => 0.0, 'credit' => 1434.09, 'transfer_credit' => 0.0, 'closing_balance' => $closingTotal],
            ],
        ]);
    }

    /** The Fandaqah FIN-040 sample (19.11.26): no transactions, ledger balances only. */
    public static function frontOffice(float $hotelBalance = -19239.62): array
    {
        $ledger = fn (string $key, string $label, float $balance) => [
            'ledger' => $key, 'label_as_printed' => $label, 'opening_balance' => $balance,
            'movements' => [['label' => 'Charges', 'amount' => 0.0], ['label' => 'Payments', 'amount' => 0.0]],
            'closing_balance' => $balance,
        ];

        return self::header('front_office_trial_balance', [
            'front_office_trial_balance' => [
                'transactions' => [],
                'category_totals' => [
                    ['category' => 'revenue', 'printed_total' => 0.0],
                    ['category' => 'payment', 'printed_total' => 0.0],
                ],
                'ledgers' => [
                    $ledger('guest', 'Guest Ledger', -9720.07),
                    $ledger('ar', 'AR Ledger', 6680.45),
                    $ledger('deposit', 'Deposit Ledger', -16200.00),
                    $ledger('package', 'Package Ledger', 0.0),
                ],
                'printed_hotel_balance' => $hotelBalance,
            ],
        ]);
    }
}
