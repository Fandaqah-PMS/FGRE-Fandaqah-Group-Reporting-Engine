<?php

namespace App\Models;

use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's email subscription: the group report daily, weekly or monthly,
 * as charts, tables or both, for all their hotels or a chosen set.
 */
class ReportSubscription extends Model
{
    use BelongsToCurrentGroup;

    public const FREQUENCIES = ['daily', 'weekly', 'monthly'];

    public const CONTENTS = ['charts', 'tables', 'both'];

    protected $guarded = ['id'];

    protected $attributes = [
        'report' => 'manager_flash',
        'content' => 'both',
        'send_time' => '08:00',
        'include_attention' => true,
        'locale' => 'ar',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'hotels' => 'array',
            'weekday' => 'integer',
            'month_day' => 'integer',
            'include_attention' => 'boolean',
            'is_active' => 'boolean',
            'last_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wantsCharts(): bool
    {
        return $this->content !== 'tables';
    }

    public function wantsTables(): bool
    {
        return $this->content !== 'charts';
    }
}
