<?php

namespace App\Reports\Extraction;

use App\Models\ReportDocument;

/**
 * Maps Fandaqah PMS "Reports Plus" JSON (POST /nova-vendor/pms-reports/reports/{key}/run)
 * into the canonical extraction shape: the PMS already sends structured data.
 *
 * Rules from the Fandaqah reference (docs/fandaqah-pms-reference.md §5.5):
 * - key rows by `_code` / `account_number`, never by label (labels follow the locale);
 * - the report date is meta.filters.date (or period_from / period_to), NOT meta.business_date,
 *   which is the hotel's current business date;
 * - meta.provisional = true means the day is not closed by Night Audit.
 */
class FandaqahReportsPlusExtractor implements ReportExtractor
{
    /** Reports Plus manager-flash `_code` => canonical metric. */
    public const FLASH_CODES = [
        'total_rooms' => 'total_rooms',
        'rooms_occupied' => 'rooms_occupied',
        'ooo_rooms' => 'ooo_rooms',
        'oos_rooms' => 'oos_rooms',
        'comp_rooms' => 'comp_rooms',
        'house_rooms' => 'house_use_rooms',
        'day_use_rooms' => 'day_use_rooms',
        'room_revenue' => 'room_revenue',
        'fnb_revenue' => 'fnb_revenue',
        'other_revenue' => 'other_revenue',
        'total_revenue' => 'total_revenue',
        'inh_persons' => 'persons_in_house',
        'arr_rooms' => 'arrival_rooms',
        'dep_rooms' => 'departure_rooms',
        'noshow_rooms' => 'no_show_rooms',
        'cxl_today' => 'cancellations',
        'pct_occ' => 'occupancy_pct',
        'adr' => 'adr',
    ];

    /** Reports Plus column => canonical period. */
    public const COLUMNS = [
        'd_ty' => 'day', 'm_ty' => 'mtd', 'y_ty' => 'ytd',
        'd_ly' => 'day_ly', 'm_ly' => 'mtd_ly', 'y_ly' => 'ytd_ly',
    ];

    public const GL_TRIAL_BALANCE_KEYS = ['fin-trial-balance', 'fin-acc-period-trial-balance'];

    public function extract(ReportDocument $document): ExtractionResult
    {
        return new ExtractionResult($this->map($this->decode($document->contents())), 'fandaqah_json');
    }

    /** @return array<string, mixed> */
    public function decode(string $json): array
    {
        $data = json_decode(ltrim($json, "\xEF\xBB\xBF"), true);
        if (! is_array($data) || ! isset($data['meta']['key'])) {
            throw new ExtractionFailed('File is not Fandaqah Reports Plus JSON (meta.key missing).');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function map(array $data): array
    {
        $meta = $data['meta'];
        $key = $meta['key'];
        $filters = $meta['filters'] ?? [];

        $header = [
            'report_title_as_printed' => $meta['title_en'] ?? $meta['title_ar'] ?? null,
            'property_name' => $meta['hotel']['en_name'] ?? $meta['hotel']['name'] ?? null,
            'property_code' => isset($meta['hotel']['id']) ? (string) $meta['hotel']['id'] : null,
            'period_start' => null,
            'business_date' => $filters['date'] ?? null,
            'provisional' => (bool) ($meta['provisional'] ?? false),
            'currency' => $meta['hotel']['currency'] ?? null,
            'confidence' => 'high',
            'issues' => [],
            'manager_flash' => null,
            'trial_balance' => null,
            'front_office_trial_balance' => null,
            'guest_trial_balance' => null,
        ];

        $rows = collect($data['sections'] ?? [])->flatMap(fn (array $s) => $s['rows'] ?? [])->all();

        if ($key === 'manager-flash') {
            return [...$header, 'report_type' => 'manager_flash', 'manager_flash' => $this->flash($rows)];
        }

        if (in_array($key, self::GL_TRIAL_BALANCE_KEYS, true)) {
            return [
                ...$header,
                'report_type' => 'trial_balance',
                'period_start' => $filters['period_from'] ?? null,
                'business_date' => $filters['period_to'] ?? null,
                'trial_balance' => $this->trialBalance($rows, $data['totals'] ?? null),
            ];
        }

        throw new ExtractionFailed("Fandaqah report [{$key}] is not supported yet.");
    }

    /** @param list<array<string, mixed>> $rows */
    private function flash(array $rows): array
    {
        $metrics = [];
        foreach ($rows as $row) {
            $metric = self::FLASH_CODES[$row['_code'] ?? ''] ?? null;
            if ($metric === null) {
                continue;
            }

            $entry = ['metric' => $metric, 'label_as_printed' => (string) ($row['label'] ?? $row['_code'])];
            foreach (self::COLUMNS as $column => $period) {
                $entry[$period] = isset($row[$column]) ? (float) $row[$column] : null;
            }
            $metrics[] = $entry;
        }

        return ['metrics' => $metrics, 'revenue_departments' => []];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals
     */
    private function trialBalance(array $rows, ?array $totals): array
    {
        $lines = [];
        foreach ($rows as $row) {
            if (($row['_type'] ?? 'data') !== 'data' || ! isset($row['account_number'])) {
                continue;
            }

            $local = $row['facility_ledger_id'] ?? null;
            $lines[] = [
                'account_code' => (string) $row['account_number'],
                'local_account_code' => $local === null ? null : (string) $local,
                'account_name' => (string) ($row['account_name'] ?? ''),
                'account_class' => $row['account_class'] ?? $row['root_type'] ?? null,
                'opening_balance' => null,
                'debit' => (float) ($row['debit'] ?? 0),
                'credit' => (float) ($row['credit'] ?? 0),
                'closing_balance' => round((float) ($row['balance_debit'] ?? 0) - (float) ($row['balance_credit'] ?? 0), 2),
            ];
        }

        return [
            'lines' => $lines,
            'printed_total_debit' => isset($totals['debit']) ? (float) $totals['debit'] : null,
            'printed_total_credit' => isset($totals['credit']) ? (float) $totals['credit'] : null,
        ];
    }
}
