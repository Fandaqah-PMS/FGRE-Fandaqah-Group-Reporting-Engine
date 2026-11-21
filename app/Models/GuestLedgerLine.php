<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One guest or company folio from a guest trial balance. guest_name is personal data. */
class GuestLedgerLine extends Model
{
    use BelongsToCurrentGroup;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'arrival' => 'date',
            'departure' => 'date',
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
