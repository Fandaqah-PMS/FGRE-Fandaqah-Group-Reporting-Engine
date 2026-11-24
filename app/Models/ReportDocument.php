<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\ReportType;
use App\Tenancy\BelongsToCurrentGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One received report file. group_id stays null until the document is matched
 * to a hotel, so unmatched documents are visible only to platform admins.
 */
class ReportDocument extends Model
{
    use BelongsToCurrentGroup;

    protected $fillable = [
        'inbound_message_id', 'group_id', 'property_id', 'report_type', 'period_start', 'business_date',
        'is_provisional', 'original_filename', 'storage_disk', 'storage_path', 'mime_type', 'size_bytes',
        'sha256', 'status', 'status_reason', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'status' => DocumentStatus::class,
            'period_start' => 'date',
            'business_date' => 'date',
            'is_provisional' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }

    public function inboundMessage(): BelongsTo
    {
        return $this->belongsTo(InboundMessage::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function extractionRuns(): HasMany
    {
        return $this->hasMany(ExtractionRun::class);
    }

    public function contents(): string
    {
        return Storage::disk($this->storage_disk)->get($this->storage_path);
    }

    public function markStatus(DocumentStatus $status, ?string $reason = null): void
    {
        $this->forceFill([
            'status' => $status,
            'status_reason' => $reason,
            'processed_at' => $status->isFinal() ? now() : $this->processed_at,
        ])->save();
    }
}
