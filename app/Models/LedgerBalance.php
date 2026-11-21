<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Front-office ledger balance (guest / AR / deposit / package) for one business date. */
class LedgerBalance extends Model
{
    use BelongsToCurrentGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
