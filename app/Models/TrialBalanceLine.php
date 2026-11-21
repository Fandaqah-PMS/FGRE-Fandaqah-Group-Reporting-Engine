<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** General-ledger trial balance line. Balances are signed: debit +, credit -. */
class TrialBalanceLine extends Model
{
    use BelongsToCurrentGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'business_date' => 'date',
            'opening_balance' => 'decimal:2',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
