<?php

namespace App\Models;

use App\Enums\InboundSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InboundMessage extends Model
{
    protected $fillable = [
        'source', 'external_id', 'from_email', 'subject', 'received_at',
        'attachment_count', 'status', 'status_reason',
    ];

    protected function casts(): array
    {
        return [
            'source' => InboundSource::class,
            'received_at' => 'datetime',
        ];
    }

    public function reportDocuments(): HasMany
    {
        return $this->hasMany(ReportDocument::class);
    }
}
