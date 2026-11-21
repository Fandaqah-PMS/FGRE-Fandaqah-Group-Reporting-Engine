<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ReportDocument extends Model
{
    protected $fillable = [
        'inbound_message_id', 'tenant_id', 'property_id', 'report_type', 'business_date',
        'original_filename', 'storage_disk', 'storage_path', 'mime_type', 'size_bytes',
        'sha256', 'status', 'status_reason', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'status' => DocumentStatus::class,
            'business_date' => 'date',
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
