<?php

namespace App\Models;

use App\Enums\StatPeriod;
use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Parts of the KPIs for one hotel, date and period. Never read printed_*
 * columns for dashboards: compute KPIs with App\Reports\Kpi\KpiCalculator.
 */
class DailyPropertyStat extends Model
{
    use BelongsToCurrentGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'period' => StatPeriod::class,
            'is_provisional' => 'boolean',
            'room_revenue' => 'decimal:2',
            'fnb_revenue' => 'decimal:2',
            'other_revenue' => 'decimal:2',
            'total_revenue' => 'decimal:2',
            'printed_occupancy_pct' => 'decimal:2',
            'printed_adr' => 'decimal:2',
            'printed_revpar' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
