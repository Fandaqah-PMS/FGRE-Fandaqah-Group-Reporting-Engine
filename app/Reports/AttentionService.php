<?php

namespace App\Reports;

use App\Enums\DocumentStatus;
use App\Enums\StatPeriod;
use App\Models\GuestLedgerLine;
use App\Models\LedgerBalance;
use App\Models\Property;
use App\Models\ReportDocument;
use App\Models\TrialBalanceLine;
use App\Reports\Kpi\BudgetService;
use App\Reports\Kpi\GroupKpiService;
use Illuminate\Support\Collection;

/**
 * "Needs attention": what an owner should look at today, most serious first.
 * Thresholds follow common hotel-management practice and are set in config/fgre.php.
 * Each item: level (critical | warning | info), type, hotel, params (for the message).
 */
class AttentionService
{
    private const RANK = ['critical' => 0, 'warning' => 1, 'info' => 2];

    public function __construct(private readonly GroupKpiService $kpis, private readonly BudgetService $budgets) {}

    /**
     * @param  Collection<int, int>  $propertyIds
     * @return list<array<string, mixed>>
     */
    public function items(int $groupId, string $date, Collection $propertyIds): array
    {
        $th = config('fgre.attention');
        $hotels = Property::forGroup($groupId)->whereIn('id', $propertyIds)->get(['id', 'name', 'name_ar'])->keyBy('id');
        $ref = fn (int $id) => ['id' => $id, 'name' => $hotels[$id]->name ?? '', 'name_ar' => $hotels[$id]->name_ar ?? null];
        $items = [];
        $add = function (string $level, string $type, ?int $hotelId, array $params = []) use (&$items, $ref) {
            $items[] = ['level' => $level, 'type' => $type, 'hotel' => $hotelId ? $ref($hotelId) : null, 'params' => $params];
        };

        $day = $this->kpis->forDate($groupId, $date, StatPeriod::Day, $propertyIds);
        foreach ($day['missing'] as $m) {
            $add('critical', 'missing_report', $m['id'], ['date' => $date]);
        }
        foreach ($day['hotels'] as $h) {
            if ($h['is_provisional']) {
                $add('info', 'provisional', $h['property_id']);
            }
        }

        $mtd = collect($this->kpis->forDate($groupId, $date, StatPeriod::MonthToDate, $propertyIds)['hotels'])->keyBy('property_id');
        $mtdLy = collect($this->kpis->forDate($groupId, $date, StatPeriod::MonthToDateLastYear, $propertyIds)['hotels'])->keyBy('property_id');
        $group = $this->kpis->forDate($groupId, $date, StatPeriod::MonthToDate, $propertyIds)['group'];
        $budget = $this->budgets->forHotels($groupId, $date, $propertyIds);

        foreach ($mtd as $id => $h) {
            $ly = $mtdLy->get($id);
            if ($ly && isset($h['occupancy_pct'], $ly['occupancy_pct'])) {
                $pp = (float) $h['occupancy_pct'] - (float) $ly['occupancy_pct'];
                if ($pp <= -$th['occupancy_drop_pp']) {
                    $add('warning', 'occupancy_down', $id, ['value' => round($pp, 1)]);
                }
            }
            if ($ly && isset($h['adr'], $ly['adr']) && (float) $ly['adr'] > 0) {
                $pct = ((float) $h['adr'] / (float) $ly['adr'] - 1) * 100;
                if ($pct <= -$th['adr_drop_pct']) {
                    $add('warning', 'adr_down', $id, ['value' => round($pct, 1)]);
                }
            }
            if (isset($h['revpar'], $group['revpar']) && (float) $group['revpar'] > 0 && $mtd->count() > 1) {
                $index = (float) $h['revpar'] / (float) $group['revpar'] * 100;
                if ($index < 100 - $th['revpar_index_gap']) {
                    $add('info', 'revpar_below_group', $id, ['value' => round($index)]);
                }
            }
            $b = $budget[$id]['mtd'] ?? null;
            if ($b && (float) $b['total_revenue'] > 0 && isset($h['total_revenue'])) {
                $pct = ((float) $h['total_revenue'] / (float) $b['total_revenue'] - 1) * 100;
                if ($pct <= -$th['budget_gap_pct']) {
                    $add($pct <= -2 * $th['budget_gap_pct'] ? 'critical' : 'warning', 'below_budget', $id, ['value' => round($pct, 1)]);
                }
            }
        }

        // Latest general-ledger trial balance per hotel must net to zero.
        $tbDates = TrialBalanceLine::forGroup($groupId)->whereIn('property_id', $propertyIds)
            ->selectRaw('property_id, max(business_date) as d')->groupBy('property_id')->pluck('d', 'property_id');
        foreach ($tbDates as $id => $d) {
            $net = (float) TrialBalanceLine::forGroup($groupId)->where('property_id', $id)->whereDate('business_date', substr((string) $d, 0, 10))->sum('closing_balance');
            if (abs($net) > 0.05) {
                $add('critical', 'tb_unbalanced', (int) $id, ['value' => round($net, 2)]);
            }
        }

        // Accounts receivable (city ledger) growing fast since yesterday.
        $ar = LedgerBalance::forGroup($groupId)->whereIn('property_id', $propertyIds)->where('ledger', 'ar')
            ->orderByDesc('business_date')->get()->unique('property_id');
        foreach ($ar as $row) {
            $open = (float) $row->opening_balance;
            if ($open > 0 && ((float) $row->closing_balance / $open - 1) * 100 >= $th['ar_growth_pct']) {
                $add('warning', 'ar_growing', $row->property_id, ['value' => round(((float) $row->closing_balance / $open - 1) * 100, 1)]);
            }
        }

        // Guest trial balance: folios still owing after check-out, and very large open folios.
        $guestDates = GuestLedgerLine::forGroup($groupId)->whereIn('property_id', $propertyIds)
            ->selectRaw('property_id, max(business_date) as d')->groupBy('property_id')->pluck('d', 'property_id');
        foreach ($guestDates as $id => $d) {
            $folios = GuestLedgerLine::forGroup($groupId)->where('property_id', $id)
                ->whereDate('business_date', substr((string) $d, 0, 10))->get(['status', 'closing_balance']);
            $unpaid = $folios->where('status', 'checked_out')->filter(fn ($f) => (float) $f->closing_balance > 0.005);
            if ($unpaid->isNotEmpty()) {
                $add('warning', 'guest_checked_out_balance', (int) $id, ['count' => $unpaid->count(), 'value' => number_format($unpaid->sum(fn ($f) => (float) $f->closing_balance), 0)]);
            }
            $large = $folios->filter(fn ($f) => (float) $f->closing_balance >= $th['guest_balance_large']);
            if ($large->isNotEmpty()) {
                $add('info', 'guest_large_balance', (int) $id, ['count' => $large->count(), 'value' => number_format((float) $large->max(fn ($f) => (float) $f->closing_balance), 0)]);
            }
        }

        $review = ReportDocument::forGroup($groupId)->whereIn('property_id', $propertyIds)
            ->where('status', DocumentStatus::NeedsReview)->selectRaw('property_id, count(*) as n')->groupBy('property_id')->pluck('n', 'property_id');
        foreach ($review as $id => $n) {
            $add('warning', 'needs_review', (int) $id, ['count' => (int) $n]);
        }

        usort($items, fn ($a, $b) => self::RANK[$a['level']] <=> self::RANK[$b['level']]);

        return $items;
    }
}
