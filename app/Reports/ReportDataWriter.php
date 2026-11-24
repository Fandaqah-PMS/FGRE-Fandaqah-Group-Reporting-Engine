<?php

namespace App\Reports;

use App\Enums\ReportType;
use App\Models\DailyPropertyStat;
use App\Models\GuestLedgerLine;
use App\Models\LedgerBalance;
use App\Models\Property;
use App\Models\ReportDocument;
use App\Models\RevenueLine;
use App\Models\TransactionCodeLine;
use App\Models\TrialBalanceLine;
use App\Reports\Extraction\ExtractionSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes validated extraction output into the canonical report tables.
 *
 * A newer report for the same hotel, type and business date replaces the
 * earlier data (a re-sent or corrected report, or the final version of a
 * provisional day). Runs in jobs: the group is always passed explicitly.
 */
class ReportDataWriter
{
    /** @param array<string, mixed> $output */
    public function write(ReportDocument $document, Property $property, array $output): void
    {
        $base = [
            'group_id' => $property->group_id,
            'property_id' => $property->id,
            'report_document_id' => $document->id,
            'business_date' => $output['business_date'],
            'currency' => strtoupper($output['currency'] ?? $property->currency),
        ];

        DB::transaction(fn () => match ($document->report_type) {
            ReportType::ManagerFlash => $this->writeFlash($base, $output['manager_flash'], (bool) ($output['provisional'] ?? false)),
            ReportType::TrialBalance => $this->writeTrialBalance($base, $output['trial_balance'], $output['period_start'] ?? null),
            ReportType::FrontOfficeTrialBalance => $this->writeFrontOffice($base, $output['front_office_trial_balance']),
            ReportType::GuestTrialBalance => $this->writeGuestLedger($base, $output['guest_trial_balance']),
            ReportType::Unknown => throw new \LogicException('Cannot store data for an unknown report type.'),
        });
    }

    /** @param array<string, mixed> $flash */
    private function writeFlash(array $base, array $flash, bool $provisional): void
    {
        $this->clear([DailyPropertyStat::class, RevenueLine::class], $base);

        foreach (ExtractionSchema::PERIODS as $period) {
            $values = [];
            foreach ($flash['metrics'] as $row) {
                $metric = $row['metric'];
                if (! isset(ExtractionSchema::FLASH_METRICS[$metric]) || $row[$period] === null) {
                    continue;
                }

                $column = ExtractionSchema::FLASH_METRICS[$metric][0];
                $values[$column] = in_array($metric, ExtractionSchema::COUNT_METRICS, true)
                    ? (int) round($row[$period])
                    : round((float) $row[$period], 2);
            }

            if ($values !== []) {
                DailyPropertyStat::create([...$base, 'period' => $period, 'is_provisional' => $provisional, ...$values]);
            }

            foreach ($flash['revenue_departments'] ?? [] as $line) {
                if ($line[$period] !== null) {
                    RevenueLine::create([
                        ...$base,
                        'period' => $period,
                        'department' => $line['department'],
                        'category' => $line['category'],
                        'amount' => round((float) $line[$period], 2),
                    ]);
                }
            }
        }
    }

    /** @param array<string, mixed> $tb */
    private function writeTrialBalance(array $base, array $tb, ?string $periodStart): void
    {
        $this->clear([TrialBalanceLine::class], $base);

        $now = now();
        $rows = array_map(fn (array $line) => [
            ...$base,
            'period_start' => $periodStart,
            'account_code' => $line['account_code'],
            'local_account_code' => $line['local_account_code'] ?? null,
            'account_name' => $line['account_name'],
            'account_class' => $line['account_class'] ?? null,
            'opening_balance' => $line['opening_balance'],
            'debit' => $line['debit'],
            'credit' => $line['credit'],
            'closing_balance' => $line['closing_balance'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $tb['lines']);

        foreach (array_chunk($rows, 500) as $chunk) {
            TrialBalanceLine::insert($chunk);
        }
    }

    /** @param array<string, mixed> $fo */
    private function writeFrontOffice(array $base, array $fo): void
    {
        $this->clear([LedgerBalance::class, TransactionCodeLine::class], $base);

        // A PMS may print a ledger in more than one section; keep one row per ledger.
        $ledgers = [];
        foreach ($fo['ledgers'] as $ledger) {
            $key = $ledger['ledger'];
            $ledgers[$key] = [
                'opening_balance' => isset($ledgers[$key]) ? null : $ledger['opening_balance'],
                'closing_balance' => ($ledgers[$key]['closing_balance'] ?? 0) + $ledger['closing_balance'],
            ];
        }

        foreach ($ledgers as $ledger => $balances) {
            LedgerBalance::create([...$base, 'ledger' => $ledger, ...$balances]);
        }

        foreach ($fo['transactions'] as $tx) {
            TransactionCodeLine::create([
                ...$base,
                'code' => $tx['code'],
                'description' => $tx['description'],
                'category' => $tx['category'],
                'amount' => round((float) $tx['amount'], 2),
            ]);
        }
    }

    /**
     * Guest folios, plus the guest ledger total in ledger_balances (so the ledgers page and
     * group totals see it even when no front-office trial balance is sent).
     *
     * @param  array<string, mixed>  $g
     */
    private function writeGuestLedger(array $base, array $g): void
    {
        $this->clear([GuestLedgerLine::class], $base);

        $now = now();
        $rows = array_map(fn (array $l) => [
            ...$base,
            'room' => $l['room'],
            'guest_name' => $l['name'],
            'arrival' => $l['arrival'],
            'departure' => $l['departure'],
            'opening_balance' => round((float) $l['opening_balance'], 2),
            'debit' => round((float) $l['debit'] + (float) $l['transfer_debit'], 2),
            'credit' => round((float) $l['credit'] + (float) $l['transfer_credit'], 2),
            'closing_balance' => round((float) $l['closing_balance'], 2),
            'status' => $l['status'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $g['lines']);
        foreach (array_chunk($rows, 500) as $chunk) {
            GuestLedgerLine::insert($chunk);
        }

        LedgerBalance::forGroup($base['group_id'])->updateOrCreate(
            ['property_id' => $base['property_id'], 'business_date' => $base['business_date'], 'ledger' => 'guest'],
            [...$base, 'opening_balance' => round(array_sum(array_column($rows, 'opening_balance')), 2), 'closing_balance' => round(array_sum(array_column($rows, 'closing_balance')), 2)],
        );
    }

    /** @param list<class-string<Model>> $models */
    private function clear(array $models, array $base): void
    {
        foreach ($models as $model) {
            $model::forGroup($base['group_id'])
                ->where('property_id', $base['property_id'])
                ->whereDate('business_date', $base['business_date'])
                ->delete();
        }
    }
}
