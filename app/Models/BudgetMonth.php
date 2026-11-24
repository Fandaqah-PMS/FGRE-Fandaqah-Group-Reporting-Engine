<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One hotel's budget for one month (rooms sold and departmental revenue). */
class BudgetMonth extends Model
{
    use BelongsToCurrentGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'month' => 'date',
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
