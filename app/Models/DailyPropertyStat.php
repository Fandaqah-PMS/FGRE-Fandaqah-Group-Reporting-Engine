<?php

namespace App\Models;

use App\Enums\StatPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPropertyStat extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'period' => StatPeriod::class,
            'occupancy_pct' => 'decimal:2',
            'adr' => 'decimal:2',
            'revpar' => 'decimal:2',
            'room_revenue' => 'decimal:2',
            'fnb_revenue' => 'decimal:2',
            'other_revenue' => 'decimal:2',
            'total_revenue' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
